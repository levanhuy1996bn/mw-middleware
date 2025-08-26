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
        $eventId = $event->getId();
        $eventName = $event->getName();

        $this->logger->info(sprintf('Received Shopify webhook event: "%s"', $eventName), [
            'webhook_id' => $event->getId(),
        ]);
        $this->createEventTriggeredFile($eventId, microtime(true) . ' ||| ' . ($payload['id'] ?? ''));

        // Create a file named after the topic (e.g., orders/create -> orders_create) for visibility/tests
        $topicNameOnly = explode('.', (string) $eventId)[0] ?? null;
        if ($topicNameOnly) {
            $this->createEventTriggeredFile($topicNameOnly);
        }

        try {
            // Product Create
            if (stripos($eventId, ShopifyWebhookParser::EVENT_TOPICS['PRODUCTS_CREATE']) !== false) {
                [$productData, $newMedia] = $this->prepareProductData($payload, true);
                $this->createEventTriggeredFile('PRODUCTS_CREATE_errors', json_encode($productData));
                $response = $this->requestQuery(
                    $this->graphQLQueryHelper->getProductCreateMutation(),
                    ['input' => $productData,
                     'media' => $newMedia,
                    ]
                );

                $errors = $response['data']['productCreate']['userErrors'] ?? [];

                if (count($errors)) {
                    $this->createEventTriggeredFile('PRODUCTS_CREATE_errors', json_encode($errors));
                } else {
                    $this->createEventTriggeredFile($eventId, microtime(true) . ' ||| ' . ($payload['id'] ?? ''));
                }
            }

            // Product Update
            if (stripos($eventId, ShopifyWebhookParser::EVENT_TOPICS['PRODUCTS_UPDATE']) !== false) {
                [$productData, $newMedia] = $this->prepareProductData($payload, false);
                $response = $this->requestQuery(
                    $this->graphQLQueryHelper->getProductUpdateMutation(),
                    [
                        'input' => $productData,
                        'media' => $newMedia,
                    ]
                );

                $errors = $response['data']['productUpdate']['userErrors'] ?? [];

                if (count($errors)) {
                    $this->createEventTriggeredFile('PRODUCTS_UPDATE_errors', json_encode($errors));
                } else {
                    $this->createEventTriggeredFile($eventId, microtime(true) . ' ||| ' . ($payload['id'] ?? ''));
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error processing Shopify webhook event "%s": %s', $eventName, $e->getMessage()), [
                'exception' => $e,
                'payload' => $payload,
            ]);
        }
    }

    private function prepareProductData(array $payload, $isCreated): array
    {
        if (array_key_exists('body_html', $payload)) {
            $payload['descriptionHtml'] = $payload['body_html'];
            unset($payload['body_html']);
        } else {
            $payload['descriptionHtml'] = null;
        }

        if (array_key_exists('product_type', $payload)) {
            $payload['productType'] = $payload['product_type'];
            unset($payload['product_type']);
        }

        if (array_key_exists('status', $payload)) {
            $payload['status'] = strtoupper($payload['status']);
        }

        if (array_key_exists('admin_graphql_api_id', $payload)) {
            if(!$isCreated) {
                $payload['product']['id'] = $payload['admin_graphql_api_id'];
            }
            unset($payload['admin_graphql_api_id']);
        }

        if (array_key_exists('updated_at', $payload)) {
            unset($payload['updated_at']);
        }

        if (array_key_exists('image', $payload)) {
            unset($payload['image']);
        }

        if (array_key_exists('id', $payload)) {
            unset($payload['id']);
        }

        if (array_key_exists('created_at', $payload)) {
            unset($payload['created_at']);
        }

        if (array_key_exists('published_at', $payload)) {
            unset($payload['published_at']);
        }

        if (array_key_exists('template_suffix', $payload)) {
            $payload['templateSuffix'] = $payload['template_suffix'];
            unset($payload['template_suffix']);
        }

        if (array_key_exists('published_scope', $payload)) {
            unset($payload['published_scope']);
        }

        if (array_key_exists('images', $payload)) {
            unset($payload['images']);
        }

        if (array_key_exists('has_variants_that_requires_components', $payload)) {
            unset($payload['has_variants_that_requires_components']);
        }

        if (array_key_exists('variant_gids', $payload)) {
            unset($payload['variant_gids']);
        }

        if (array_key_exists('variants', $payload)) {
            unset($payload['variants']);
        }

        if (array_key_exists('options', $payload)) {
            unset($payload['options']);
        }

        $newMedia = [];

        if(array_key_exists('media', $payload) && count($payload['media']) > 0) {
            foreach ($payload['media'] as $media) {
                $newMedia[] = [
                    'alt' => $media['alt'] ?? null,
                    'mediaContentType' => $media['media_content_type'] ?? null,
                    'originalSource' => $media['preview_image']['src'] ?? null,
                ];
            }

            unset($payload['media']);
        }

        $payload['category'] = null;
//        $payload['category'] = $payload['category']['admin_graphql_api_id'] ?? null;

        return [$payload, $newMedia];
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
            // do nothing
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
