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

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setShopifySDK(GraphQLQueryHelper $graphQLQueryHelper, ShopifySDK $shopifySDK, LoggerInterface $logger, Filesystem $filesystem)
    {
        $this->shopifySDK = $shopifySDK;
        $this->graphQLQueryHelper = $graphQLQueryHelper;
        $this->logger = $logger;
        $this->filesystem = $filesystem;
    }

    public function consume(RemoteEvent $event): void
    {
        $payload = $event->getPayload();
        $eventId = (string) $event->getId(); // format: <topic>.<domain>.<webhook-id>
        $eventName = $event->getName();

        $topic = $this->extractTopicFromEventId($eventId);

        $this->logger->info('Received Shopify webhook', [
            'name' => $eventName,
            'event_id' => $eventId,
            'topic' => $topic,
        ]);

        // Ghi dấu vết theo eventId và theo topic để phục vụ test/quan sát
        $this->createEventTriggeredFile($eventId, microtime(true) . ' ||| ' . ($payload['id'] ?? ''));
        if ($topic !== null) {
            $this->createEventTriggeredFile($topic);
        }

        try {
            switch ($topic) {
                case ShopifyWebhookParser::EVENT_TOPICS['PRODUCTS_CREATE']:
                    $input = $this->mapProductCreateInput($payload);
                    $response = $this->requestQuery(
                        $this->graphQLQueryHelper->getProductCreateMutation(),
                        ['input' => $input]
                    );

                    $errors = $response['data']['productCreate']['userErrors'] ?? [];
                    if (!empty($errors)) {
                        $this->logger->warning('ProductCreate userErrors', ['errors' => $errors]);
                        $this->createEventTriggeredFile('PRODUCTS_CREATE_errors', json_encode($errors));
                    }
                    break;

                case ShopifyWebhookParser::EVENT_TOPICS['PRODUCTS_UPDATE']:
                    $input = $this->mapProductUpdateInput($payload);

                    // Nếu không có id GraphQL, không thể update an toàn
                    if (empty($input['id'])) {
                        $this->logger->warning('Skipping ProductUpdate: missing admin_graphql_api_id in payload');
                        break;
                    }

                    $response = $this->requestQuery(
                        $this->graphQLQueryHelper->getProductUpdateMutation(),
                        ['input' => $input]
                    );

                    $errors = $response['data']['productUpdate']['userErrors'] ?? [];
                    if (!empty($errors)) {
                        $this->logger->warning('ProductUpdate userErrors', ['errors' => $errors]);
                        $this->createEventTriggeredFile('PRODUCTS_UPDATE_errors', json_encode($errors));
                    }
                    break;

                default:
                    // Không xử lý các topic khác nhưng vẫn acknowledge
                    $this->logger->info('Shopify webhook topic ignored', ['topic' => $topic]);
                    break;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error processing Shopify webhook', [
                'event_id' => $eventId,
                'topic' => $topic,
                'exception' => $e,
            ]);
        }
    }

    private function extractTopicFromEventId(string $eventId): ?string
    {
        // eventId format: <topic>.<domain>.<webhook-id>
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
            $input['title'] = (string) $payload['title'];
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
            $input['status'] = strtoupper($payload['status']); // ACTIVE|DRAFT|ARCHIVED
        }

        if (array_key_exists('tags', $payload)) {
            // REST trả tags dạng string "tag1, tag2"; GraphQL cần [String]
            $input['tags'] = $this->normalizeTags($payload['tags']);
        }

        if (array_key_exists('template_suffix', $payload)) {
            $input['templateSuffix'] = $payload['template_suffix'];
        }

        // Bỏ qua các trường không thuộc ProductInput để tránh userErrors
        return $input;
    }

    private function mapProductUpdateInput(array $payload): array
    {
        // Trường bắt buộc: id là Admin GraphQL GID
        $input = [];

        if (!empty($payload['admin_graphql_api_id'])) {
            $input['id'] = $payload['admin_graphql_api_id'];
        }

        if (isset($payload['title'])) {
            $input['title'] = (string) $payload['title'];
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
            $url = str_replace('/2022-04/', '/2022-07/', $url);
        }

        return $this->shopifySDK->GraphQL()->post($query, $url, false, $variables);
    }

    private function createEventTriggeredFile($name = null, $line = null)
    {
        try {
            $filepath = $this->getEventTriggeredFilePath($name);
            if ($this->filesystem->exists($filepath)) {
                $this->filesystem->appendToFile($filepath, ($line ?? '').\PHP_EOL);
            } else {
                $this->filesystem->dumpFile($filepath, ($line ?? '').\PHP_EOL);
            }
        } catch (\Exception $e) {
            // no-op
        }
    }

    private function getEventTriggeredFilePath($name = null)
    {
        return __DIR__.'/../../var/'.$this->getEventTriggeredFileName($name);
    }

    private function getEventTriggeredFileName($name = null)
    {
        $name = preg_replace('`[^a-zA-Z0-9_-]+`', '_', ''.$name);
        return 'webhook_shopify_'.$name.'_event_triggered.txt';
    }
}
