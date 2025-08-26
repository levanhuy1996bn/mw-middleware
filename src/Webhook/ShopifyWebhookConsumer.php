<?php

namespace App\Webhook;

use App\Helper\GraphQLQueryHelper;
use PHPShopify\ShopifySDK;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[AsRemoteEventConsumer(name: ShopifyWebhookParser::WEBHOOK_NAME)]
class ShopifyWebhookConsumer implements ConsumerInterface
{
    private ShopifySDK $shopifySDK;
    private GraphQLQueryHelper $graphQLQueryHelper;
    private LoggerInterface $logger;
    private Filesystem $filesystem;

    public function __construct(
        GraphQLQueryHelper $graphQLQueryHelper,
        ShopifySDK $shopifySDK,
        LoggerInterface $logger,
        Filesystem $filesystem
    ) {
        $this->shopifySDK         = $shopifySDK;
        $this->graphQLQueryHelper = $graphQLQueryHelper;
        $this->logger             = $logger;
        $this->filesystem         = $filesystem;
    }

    public function consume(RemoteEvent $event): void
    {
        $payload   = $event->getPayload();
        $eventId   = $event->getId();
        $eventName = $event->getName();

        $topic = $this->extractTopicFromEventId($eventId);

        // Idempotency: skip if this event was already processed successfully
        $doneMarkerPath = $this->getEventTriggeredFilePath($eventId . '_done');
        if ($this->filesystem->exists($doneMarkerPath)) {
            $this->logger->info('Duplicate Shopify webhook ignored (already processed)',
                ['event_id' => $eventId, 'topic' => $topic]);

            return;
        }

        $this->logger->info('Received Shopify webhook', [
            'name'     => $eventName,
            'event_id' => $eventId,
            'topic'    => $topic,
        ]);

        // Trace by eventId and topic for observability/tests
        $this->createEventTriggeredFile($eventId, microtime(true) . ' ||| ' . ($payload['id'] ?? ''));
        if ($topic !== null) {
            $this->createEventTriggeredFile($topic);
        }

        try {
            $isCreate = ($topic === ShopifyWebhookParser::EVENT_TOPICS['PRODUCTS_CREATE']);
            $isUpdate = ($topic === ShopifyWebhookParser::EVENT_TOPICS['PRODUCTS_UPDATE']);

            if ($isCreate || $isUpdate) {
                // Resolve an existing product by id or handle
                $resolvedId = $this->resolveExistingProductId($payload);

                if ($isCreate) {
                    if ($resolvedId) {
                        // Exists -> Update
                        $input       = $this->mapProductUpdateInput($payload);
                        $input['id'] = $resolvedId;
                        $response    = $this->requestQuery($this->graphQLQueryHelper->getProductUpdateMutation(),
                            ['input' => $input]);
                        $errors      = $response['data']['productUpdate']['userErrors'] ?? [];
                        if ( ! empty($errors)) {
                            $this->logger->warning('ProductUpdate userErrors (create routed)', ['errors' => $errors]);
                            $this->createEventTriggeredFile('PRODUCTS_CREATE_update_errors', json_encode($errors));
                        }
                    } else {
                        // Not exists -> Create
                        $input    = $this->mapProductCreateInput($payload);
                        $response = $this->requestQuery($this->graphQLQueryHelper->getProductCreateMutation(),
                            ['input' => $input]);
                        $errors   = $response['data']['productCreate']['userErrors'] ?? [];
                        if ( ! empty($errors)) {
                            $this->logger->warning('ProductCreate userErrors', ['errors' => $errors]);
                            $this->createEventTriggeredFile('PRODUCTS_CREATE_errors', json_encode($errors));
                        }
                    }
                } else { // Update topic
                    if ($resolvedId) {
                        // Exists -> Update
                        $input       = $this->mapProductUpdateInput($payload);
                        $input['id'] = $resolvedId;
                        $response    = $this->requestQuery($this->graphQLQueryHelper->getProductUpdateMutation(),
                            ['input' => $input]);
                        $errors      = $response['data']['productUpdate']['userErrors'] ?? [];
                        if ( ! empty($errors)) {
                            $this->logger->warning('ProductUpdate userErrors', ['errors' => $errors]);
                            $this->createEventTriggeredFile('PRODUCTS_UPDATE_errors', json_encode($errors));
                        }
                    } else {
                        // Not exists -> Create
                        $input    = $this->mapProductCreateInput($payload);
                        $response = $this->requestQuery($this->graphQLQueryHelper->getProductCreateMutation(),
                            ['input' => $input]);
                        $errors   = $response['data']['productCreate']['userErrors'] ?? [];
                        if ( ! empty($errors)) {
                            $this->logger->warning('ProductCreate userErrors (update routed)', ['errors' => $errors]);
                            $this->createEventTriggeredFile('PRODUCTS_UPDATE_create_errors', json_encode($errors));
                        }
                    }
                }

                // Variants handling removed per requirements


            } else {
                $this->logger->info('Shopify webhook topic ignored', ['topic' => $topic]);
            }

            // Mark event processed successfully
            $this->createEventTriggeredFile($eventId . '_done');
        } catch (\Throwable $e) {
            $this->logger->error('Error processing Shopify webhook' . $e->getMessage(),
                ['event_id' => $eventId, 'topic' => $topic, 'exception' => $e]);
        }
    }

    private function extractTopicFromEventId(string $eventId): ?string
    {
        $firstDot = strpos($eventId, '.');
        if ($firstDot === false) {
            return null;
        }

        return substr($eventId, 0, $firstDot);
    }

    private function mapProductCreateInput(array $payload): array
    {
        $input = [];
        if (isset($payload['title'])) {
            $input['title'] = (string)$payload['title'];
        }
        if (array_key_exists('body_html', $payload)) {
            $input['descriptionHtml'] = $payload['body_html'] ?? null;
        }
        if (array_key_exists('vendor', $payload)) {
            $input['vendor'] = $payload['vendor'];
        }
        if (array_key_exists('product_type', $payload)) {
            $input['productType'] = $payload['product_type'];
        }
        if (array_key_exists('status', $payload) && is_string($payload['status'])) {
            $input['status'] = strtoupper($payload['status']);
        }
        if (array_key_exists('tags', $payload)) {
            $input['tags'] = $this->normalizeTags($payload['tags']);
        }
        if (array_key_exists('template_suffix', $payload)) {
            $input['templateSuffix'] = $payload['template_suffix'];
        }

        return $input;
    }

    private function mapProductUpdateInput(array $payload): array
    {
        $input = [];
        if ( ! empty($payload['admin_graphql_api_id'])) {
            $input['id'] = $payload['admin_graphql_api_id'];
        }
        if (isset($payload['title'])) {
            $input['title'] = (string)$payload['title'];
        }
        if (array_key_exists('body_html', $payload)) {
            $input['descriptionHtml'] = $payload['body_html'] ?? null;
        }
        if (array_key_exists('vendor', $payload)) {
            $input['vendor'] = $payload['vendor'];
        }
        if (array_key_exists('product_type', $payload)) {
            $input['productType'] = $payload['product_type'];
        }
        if (array_key_exists('status', $payload) && is_string($payload['status'])) {
            $input['status'] = strtoupper($payload['status']);
        }
        if (array_key_exists('tags', $payload)) {
            $input['tags'] = $this->normalizeTags($payload['tags']);
        }
        if (array_key_exists('template_suffix', $payload)) {
            $input['templateSuffix'] = $payload['template_suffix'];
        }

        return $input;
    }

    private function normalizeTags($tags): array
    {
        if (is_array($tags)) {
            return array_values(array_filter(array_map('strval', $tags), static fn($t) => $t !== ''));
        }
        if (is_string($tags)) {
            $parts = array_map('trim', explode(',', $tags));

            return array_values(array_filter($parts, static fn($t) => $t !== ''));
        }

        return [];
    }

    private function requestQuery($query, $variables)
    {
        $url = $this->shopifySDK->GraphQL()->generateUrl();
        if (is_string($url)) {
            $url = str_replace('/2022-04/', '/2022-10/', $url);
        }

        return $this->shopifySDK->GraphQL()->post($query, $url, false, $variables);
    }

    private function createEventTriggeredFile($name = null, $line = null)
    {
        try {
            $filepath = $this->getEventTriggeredFilePath($name);
            if ($this->filesystem->exists($filepath)) {
                $this->filesystem->appendToFile($filepath, ($line ?? '') . \PHP_EOL);
            } else {
                $this->filesystem->dumpFile($filepath, ($line ?? '') . \PHP_EOL);
            }
        } catch (\Exception $e) { /* no-op */
        }
    }

    private function getEventTriggeredFilePath($name = null)
    {
        return __DIR__ . '/../../var/' . $this->getEventTriggeredFileName($name);
    }

    private function getEventTriggeredFileName($name = null)
    {
        $name = preg_replace('`[^a-zA-Z0-9_-]+`', '_', '' . $name);

        return 'webhook_shopify_' . $name . '_event_triggered.txt';
    }

    private function resolveExistingProductId(array $payload): ?string
    {
        // Try lookup by handle
        $handle = $payload['handle'] ?? null;
        if (is_string($handle) && $handle !== '') {
            try {
                $resp = $this->requestQuery($this->graphQLQueryHelper->getProductIdByHandleQuery(),
                    ['handle' => $handle]);
                $id   = $resp['data']['productByHandle']['id'] ?? null;
                if (is_string($id) && $id !== '') {
                    return $id;
                }
            } catch (\Throwable $e) { /* ignore and treat as not found */
            }
        }

        return null;
    }
}
