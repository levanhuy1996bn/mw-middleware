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
                $resolvedProductId = $this->resolveExistingProductId($payload);
                $oldMediaIds       = [];
                $targetProductId   = $resolvedProductId;

                [$input, $newMediaInputs] = $this->setProductInput($payload);
                if ($resolvedProductId) {
                    // Exists -> Update
                    $input['id'] = $resolvedProductId;
                    $response    = $this->requestQuery($this->graphQLQueryHelper->getProductUpdateMutation(),
                        ['input' => $input]);
                    $errors      = $response['data']['productUpdate']['userErrors'] ?? [];
                    if ( ! empty($errors)) {
                        $this->logger->warning('ProductUpdate userErrors (create routed)', ['errors' => $errors]);
                        $this->createEventTriggeredFile('PRODUCTS_CREATE_update_errors', json_encode($errors));
                    }

                    // Collect existing media ids from update response
                    $productMedia = $response['data']['productUpdate']['product']['media']['nodes'] ?? [];
                    foreach ($productMedia as $mediaItem) {
                        if (isset($mediaItem['id'])) {
                            $oldMediaIds[] = $mediaItem['id'];
                        }
                    }
                } else {
                    // Not exists -> Create
                    $response = $this->requestQuery($this->graphQLQueryHelper->getProductCreateMutation(),
                        ['input' => $input]);
                    $errors   = $response['data']['productCreate']['userErrors'] ?? [];
                    if ( ! empty($errors)) {
                        $this->logger->warning('ProductCreate userErrors', ['errors' => $errors]);
                        $this->createEventTriggeredFile('PRODUCTS_CREATE_errors', json_encode($errors));
                    }

                    // Collect media from created product (if any default media exists)
                    $productMedia = $response['data']['productCreate']['product']['media']['nodes'] ?? [];
                    foreach ($productMedia as $mediaItem) {
                        if (isset($mediaItem['id'])) {
                            $oldMediaIds[] = $mediaItem['id'];
                        }
                    }
                    // Use created product id as target for media creation
                    $targetProductId = $response['data']['productCreate']['product']['id'] ?? null;
                }

                // Remove old media via mutation before creating new media
                if (count($oldMediaIds) > 0 && $targetProductId) {
                    try {
                        $deleteResp   = $this->requestQuery($this->graphQLQueryHelper->getProductDeleteMediaMutation(),
                            ['mediaIds' => $oldMediaIds, 'productId' => $targetProductId]);
                        $deleteErrors = $deleteResp['data']['productDeleteMedia']['userErrors'] ?? [];
                        if ( ! empty($deleteErrors)) {
                            $this->logger->warning('productDeleteMedia userErrors', ['errors' => $deleteErrors]);
                        }
                    } catch (\Throwable $e) {
                        $this->logger->error('Exception during productDeleteMedia: ' . $e->getMessage());
                    }
                }

                // Create new media if provided
                if (count($newMediaInputs) > 0 && $targetProductId) {
                    try {
                        $createResp   = $this->requestQuery($this->graphQLQueryHelper->getProductCreateMediaMutation(),
                            [
                                'productId' => $targetProductId,
                                'media'     => $newMediaInputs,
                            ]);
                        $createErrors = $createResp['data']['productCreateMedia']['mediaUserErrors'] ?? [];
                        if ( ! empty($createErrors)) {
                            $this->logger->warning('ProductCreateMedia mediaUserErrors', ['errors' => $createErrors]);
                        }
                    } catch (\Throwable $e) {
                        $this->logger->error('Exception during productCreateMedia: ' . $e->getMessage());
                    }
                }
            } else {
                $this->logger->info('Shopify webhook topic ignored', ['topic' => $topic]);
            }

//             Mark event processed successfully
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

    private function setProductInput(array $payload): array
    {
        $input    = [];
        $newMedia = [];
        if (isset($payload['title'])) {
            $input['title'] = (string)$payload['title'];
        }
        if (array_key_exists('body_html', $payload)) {
            $input['descriptionHtml'] = $payload['body_html'] ?? null;
        }
        if (array_key_exists('status', $payload) && is_string($payload['status'])) {
            $input['status'] = strtoupper($payload['status']);
        }
        if (array_key_exists('product_type', $payload)) {
            $input['productType'] = $payload['product_type'];
        }
        if (array_key_exists('vendor', $payload)) {
            $input['vendor'] = $payload['vendor'];
        }
        if (array_key_exists('category', $payload)) {
            $input['category'] = $payload['category']['id'] ?? null;
        }

        if (array_key_exists('media', $payload) && count($payload['media']) > 0) {
            foreach ($payload['media'] as $media) {
                $newMedia[] = [
                    'alt'              => $media['alt'] ?? null,
                    'mediaContentType' => $media['media_content_type'] ?? null,
                    'originalSource'   => $media['preview_image']['src'] ?? null,
                ];
            }
        }


        //category, media
        // variants
        // metafields: brandCollection, shippingClass, eligibleForDiscount
        //metafields: pillowType, foundationType, mattressType, sleepPosition, comfortLevel, benefits

        return [$input, $newMedia];
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
