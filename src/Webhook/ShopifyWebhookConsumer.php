<?php

namespace App\Webhook;

use App\Helper\GraphQLQueryHelper;
use PHPShopify\ShopifySDK;
use Psr\Log\LoggerInterface;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[AsRemoteEventConsumer(name: ShopifyWebhookParser::WEBHOOK_NAME)]
class ShopifyWebhookConsumer implements ConsumerInterface
{
    private ShopifySDK $shopifySDK;
    private GraphQLQueryHelper $graphQLQueryHelper;
    private LoggerInterface $logger;

    public function __construct(
        GraphQLQueryHelper $graphQLQueryHelper,
        ShopifySDK $shopifySDK,
        LoggerInterface $logger
    ) {
        $this->shopifySDK         = $shopifySDK;
        $this->graphQLQueryHelper = $graphQLQueryHelper;
        $this->logger             = $logger;
    }

    public function consume(RemoteEvent $event): void
    {
        $payload   = $event->getPayload();
        $eventId   = $event->getId();
        $eventName = $event->getName();

        $topic = $this->extractTopicFromEventId($eventId);

        $this->logger->info('Received Shopify webhook', [
            'name'     => $eventName,
            'event_id' => $eventId,
            'topic'    => $topic,
        ]);

        try {
            $isCreate = ($topic === ShopifyWebhookParser::EVENT_TOPICS['PRODUCTS_CREATE']);
            $isUpdate = ($topic === ShopifyWebhookParser::EVENT_TOPICS['PRODUCTS_UPDATE']);

            if ($isCreate || $isUpdate) {
                $this->logger->info(sprintf('Processing Shopify product webhook: %s', $topic), [
                    'event_id' => $eventId,
                    'topic' => $topic,
                ]);

                // Resolve an existing product by id or handle
                $resolvedProductId = $this->resolveExistingProductId($payload);
                $oldMediaIds       = [];
                $targetProductId   = $resolvedProductId;

                [$input, $newMediaInputs] = $this->setProductInput($payload);

                $this->logger->debug('Product input prepared for mutation', [
                    'eventId' => $eventId,
                    'topic' => $topic,
                    'input' => $input,
                    'newMediaInputsCount' => count($newMediaInputs),
                ]);
                if ($resolvedProductId) {
                    // Exists -> Update
                    $input['id'] = $resolvedProductId;
                    $this->logger->info('Existing product resolved for update.', [
                        'eventId' => $eventId,
                        'topic' => $topic,
                        'resolvedProductId' => $resolvedProductId,
                        'productHandle' => $payload['handle'],
                    ]);
                    $response    = $this->requestQuery($this->graphQLQueryHelper->getProductUpdateMutation(),
                        ['input' => $input]);
                    $this->logger->info('Shopify productUpdate mutation response received.', [
                        'event_id' => $eventId,
                        'topic' => $topic,
                        'response' => $response ?? [],
                    ]);
                    $errors      = $response['data']['productUpdate']['userErrors'] ?? [];
                    if ( ! empty($errors)) {
                        $this->logger->warning('ProductUpdate userErrors', [
                            'eventId' => $eventId,
                            'topic' => $topic,
                            'errors' => $errors,
                            'productId' => $resolvedProductId,
                        ]);
                    }

                    // Collect existing media ids from update response
                    $productMedia = $response['data']['productUpdate']['product']['media']['nodes'] ?? [];
                    if (!empty($productMedia)) {
                        $this->logger->debug('Collected existing media for update', [
                            'eventId' => $eventId,
                            'topic' => $topic,
                            'mediaCount' => count($productMedia),
                        ]);

                        foreach ($productMedia as $mediaItem) {
                            if (isset($mediaItem['id'])) {
                                $oldMediaIds[] = $mediaItem['id'];
                            }
                        }
                    }
                } else {
                    // Not exists -> Create
                    $this->logger->info('No existing product found, proceeding with product creation.', [
                        'eventId' => $eventId,
                        'topic' => $topic,
                        'productHandle' => $payload['handle'],
                    ]);
                    $response = $this->requestQuery($this->graphQLQueryHelper->getProductCreateMutation(),
                        ['input' => $input]);
                    $this->logger->info('Shopify productCreate mutation response received.', [
                        'eventId' => $eventId,
                        'topic' => $topic,
                        'response' => $response ?? [],
                    ]);
                    $errors   = $response['data']['productCreate']['userErrors'] ?? [];
                    if ( ! empty($errors)) {
                        $this->logger->warning('ProductCreate userErrors', [
                            'eventId' => $eventId,
                            'topic' => $topic,
                            'errors' => $errors,
                        ]);
                    }
                    // Use created product id as target for media creation
                    $targetProductId = $response['data']['productCreate']['product']['id'] ?? null;
                    if (!$targetProductId) {
                        $this->logger->error('Failed to retrieve target product ID after creation.', [
                            'eventId' => $eventId,
                            'topic' => $topic,
                            'response' => $response,
                        ]);
                    }
                }

                // Remove old media via mutation before creating new media
                if (count($oldMediaIds) > 0 && $targetProductId) {
                    $this->logger->info(sprintf('Attempting to delete %d old media items for product %s.', count($oldMediaIds), $targetProductId), [
                        'eventId' => $eventId,
                        'topic' => $topic,
                        'productId' => $targetProductId,
                        'oldMediaIds' => $oldMediaIds,
                    ]);
                    try {
                        $deleteResp   = $this->requestQuery($this->graphQLQueryHelper->getProductDeleteMediaMutation(),
                            ['mediaIds' => $oldMediaIds, 'productId' => $targetProductId]);
                        $deleteErrors = $deleteResp['data']['productDeleteMedia']['userErrors'] ?? [];
                        if ( ! empty($deleteErrors)) {
                            $this->logger->warning('productDeleteMedia userErrors', [
                                'eventId' => $eventId,
                                'topic' => $topic,
                                'errors' => $deleteErrors,
                                'productId' => $targetProductId,
                                'mediaIdsAttempted' => $oldMediaIds,
                            ]);
                        } else {
                            $this->logger->info('Successfully deleted old media items.', [
                                'eventId' => $eventId,
                                'topic' => $topic,
                                'productId' => $targetProductId,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        $this->logger->error('Exception during productDeleteMedia: ' . $e->getMessage(), [
                            'eventId' => $eventId,
                            'topic' => $topic,
                            'productId' => $targetProductId,
                            'oldMediaIds' => $oldMediaIds,
                        ]);
                    }
                } else {
                    $this->logger->debug('No old media items to delete for product.', [
                        'eventId' => $eventId,
                        'topic' => $topic,
                        'productId' => $targetProductId,
                    ]);
                }

                // Create new media if provided
                if (count($newMediaInputs) > 0 && $targetProductId) {
                    $this->logger->info(sprintf('Attempting to create %d new media items for product %s.', count($newMediaInputs), $targetProductId), [
                        'eventId' => $eventId,
                        'topic' => $topic,
                        'productId' => $targetProductId,
                        'newMediaInputsCount' => count($newMediaInputs),
                    ]);
                    try {
                        $createResp = $this->requestQuery($this->graphQLQueryHelper->getProductCreateMediaMutation(),
                            [
                                'productId' => $targetProductId,
                                'media'     => $newMediaInputs,
                            ]);
                        $createErrors = $createResp['data']['productCreateMedia']['mediaUserErrors'] ?? [];
                        if ( ! empty($createErrors)) {
                            $this->logger->warning('ProductCreateMedia mediaUserErrors', [
                                'eventId' => $eventId,
                                'topic' => $topic,
                                'errors' => $createErrors,
                                'productId' => $targetProductId,
                                'newMediaInputsCount' => count($newMediaInputs),
                            ]);
                        } else {
                            $this->logger->info('Successfully created new media items.', [
                                'eventId' => $eventId,
                                'topic' => $topic,
                                'productId' => $targetProductId,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        $this->logger->error('Exception during productCreateMedia: ' . $e->getMessage(), [
                            'eventId' => $eventId,
                            'topic' => $topic,
                            'exception' => $e,
                            'productId' => $targetProductId,
                            'newMediaInputsCount' => count($newMediaInputs),
                        ]);
                    }
                } else {
                    $this->logger->debug('No new media items to create for product.', [
                        'eventId' => $eventId,
                        'topic' => $topic,
                        'productId' => $targetProductId,
                    ]);
                }
            } else {
                $this->logger->info('Shopify webhook topic ignored', ['topic' => $topic, 'eventId' => $eventId]);
            }

            // Mark event processing completed
            $this->logger->info('Shopify webhook processing completed', ['eventId' => $eventId, 'topic' => $topic]);
        } catch (\Throwable $e) {
            $this->logger->error('Error processing Shopify webhook: ' . $e->getMessage(),
                ['eventId' => $eventId, 'topic' => $topic, 'exception' => $e, 'payload' => $payload]);
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
            $input['category'] = $payload['category']['admin_graphql_api_id'] ?? null;
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
