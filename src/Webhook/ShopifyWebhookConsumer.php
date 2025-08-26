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

    public function __construct(GraphQLQueryHelper $graphQLQueryHelper, ShopifySDK $shopifySDK, LoggerInterface $logger, Filesystem $filesystem)
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

        $topic = $this->extractTopicFromEventId($eventId);

        $this->logger->info('Received Shopify webhook', [
            'name' => $eventName,
            'event_id' => $eventId,
            'topic' => $topic,
        ]);

        // Trace by eventId and topic for observability/tests
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

                    $createdProductId = $response['data']['productCreate']['product']['id'] ?? null;

                    $calledOptionsCreate = false;
                    if ($createdProductId && $this->shouldCreateVariants($payload)) {
                        $calledOptionsCreate = $this->ensureProductOptions($createdProductId, $payload);
                    }

                    if ($createdProductId && $this->shouldCreateVariants($payload) && !$calledOptionsCreate) {
                        $existingVariants = $this->fetchExistingVariants($createdProductId);
                        $numOptions = $this->fetchNumProductOptions($createdProductId);
                        $normalized = $this->normalizeVariantsOptionsCount($payload['variants'], $numOptions);
                        $variantsInput = $this->mapVariantsForBulkCreate($this->filterNewVariants($normalized, $existingVariants));
                        if (!empty($variantsInput)) {
                            $bulkResp = $this->requestQuery(
                                $this->graphQLQueryHelper->getProductVariantsBulkCreateMutation(),
                                [ 'productId' => $createdProductId, 'variants' => $variantsInput ]
                            );
                            $bulkErrors = $bulkResp['data']['productVariantsBulkCreate']['userErrors'] ?? [];
                            if (!empty($bulkErrors)) {
                                $this->logger->warning('VariantsBulkCreate userErrors', ['errors' => $bulkErrors]);
                                $this->createEventTriggeredFile('PRODUCTS_CREATE_VARIANTS_errors', json_encode($bulkErrors));
                            }
                        }
                    }

                    // Media
                    if ($createdProductId) {
                        $existingMediaUrls = $this->fetchExistingMediaPreviewUrls($createdProductId);
                        $mediaInput = $this->mapMediaCreateInput($payload, $existingMediaUrls);
                        if (!empty($mediaInput)) {
                            $mediaResp = $this->requestQuery(
                                $this->graphQLQueryHelper->getProductCreateMediaMutation(),
                                [ 'productId' => $createdProductId, 'media' => $mediaInput ]
                            );
                            $mediaErrors = $mediaResp['data']['productCreateMedia']['mediaUserErrors'] ?? [];
                            if (!empty($mediaErrors)) {
                                $this->logger->warning('ProductCreateMedia userErrors', ['errors' => $mediaErrors]);
                                $this->createEventTriggeredFile('PRODUCTS_CREATE_MEDIA_errors', json_encode($mediaErrors));
                            }
                        }
                    }
                    break;

                case ShopifyWebhookParser::EVENT_TOPICS['PRODUCTS_UPDATE']:
                    $input = $this->mapProductUpdateInput($payload);
                    if (empty($input['id'])) { $this->logger->warning('Skipping ProductUpdate: missing admin_graphql_api_id in payload'); break; }

                    $response = $this->requestQuery(
                        $this->graphQLQueryHelper->getProductUpdateMutation(),
                        ['input' => $input]
                    );

                    $errors = $response['data']['productUpdate']['userErrors'] ?? [];
                    if (!empty($errors)) { $this->logger->warning('ProductUpdate userErrors', ['errors' => $errors]); $this->createEventTriggeredFile('PRODUCTS_UPDATE_errors', json_encode($errors)); }

                    if (!empty($payload['variants']) && is_array($payload['variants'])) {
                        $productId = $input['id'];
                        $existingVariants = $this->fetchExistingVariants($productId);
                        $payload['variants'] = $this->injectVariantIdsFromSku($payload['variants'], $existingVariants);

                        $updateInput = $this->mapVariantsForBulkUpdate($payload['variants']);
                        if (!empty($updateInput)) {
                            $bulkResp = $this->requestQuery(
                                $this->graphQLQueryHelper->getProductVariantsBulkUpdateMutation(),
                                [ 'productId' => $productId, 'variants' => $updateInput ]
                            );
                            $bulkErrors = $bulkResp['data']['productVariantsBulkUpdate']['userErrors'] ?? [];
                            if (!empty($bulkErrors)) { $this->logger->warning('VariantsBulkUpdate userErrors', ['errors' => $bulkErrors]); $this->createEventTriggeredFile('PRODUCTS_UPDATE_VARIANTS_errors', json_encode($bulkErrors)); }
                        }

                        $newVariants = $this->filterNewVariants($payload['variants'], $existingVariants);
                        if (!empty($newVariants)) {
                            $this->ensureProductOptions($productId, $payload);
                            $numOptions = $this->fetchNumProductOptions($productId);
                            $normalized = $this->normalizeVariantsOptionsCount($newVariants, $numOptions);
                            $createInput = $this->mapVariantsForBulkCreate($normalized);
                            if (!empty($createInput)) {
                                $bulkResp = $this->requestQuery(
                                    $this->graphQLQueryHelper->getProductVariantsBulkCreateMutation(),
                                    [ 'productId' => $productId, 'variants' => $createInput ]
                                );
                                $bulkErrors = $bulkResp['data']['productVariantsBulkCreate']['userErrors'] ?? [];
                                if (!empty($bulkErrors)) { $this->logger->warning('VariantsBulkCreate userErrors (update)', ['errors' => $bulkErrors]); $this->createEventTriggeredFile('PRODUCTS_UPDATE_VARIANTS_CREATE_errors', json_encode($bulkErrors)); }
                            }
                        }
                    }

                    $existingMediaUrls = $this->fetchExistingMediaPreviewUrls($input['id']);
                    $mediaInputUpdate = $this->mapMediaCreateInput($payload, $existingMediaUrls);
                    if (!empty($mediaInputUpdate)) {
                        $mediaResp = $this->requestQuery(
                            $this->graphQLQueryHelper->getProductCreateMediaMutation(),
                            [ 'productId' => $input['id'], 'media' => $mediaInputUpdate ]
                        );
                        $mediaErrors = $mediaResp['data']['productCreateMedia']['mediaUserErrors'] ?? [];
                        if (!empty($mediaErrors)) { $this->logger->warning('ProductCreateMedia userErrors (update)', ['errors' => $mediaErrors]); $this->createEventTriggeredFile('PRODUCTS_UPDATE_MEDIA_errors', json_encode($mediaErrors)); }
                    }
                    break;

                default:
                    $this->logger->info('Shopify webhook topic ignored', ['topic' => $topic]);
                    break;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error processing Shopify webhook' . $e->getMessage(), [ 'event_id' => $eventId, 'topic' => $topic, 'exception' => $e ]);
        }
    }

    private function fetchNumProductOptions(string $productId): int
    {
        try {
            $resp = $this->requestQuery($this->graphQLQueryHelper->getProductOptionsQuery(), ['productId' => $productId]);
            $names = $resp['data']['product']['options'] ?? [];
            return is_array($names) ? count($names) : 0;
        } catch (\Throwable $e) { return 0; }
    }

    private function normalizeVariantsOptionsCount(array $variants, int $numOptions): array
    {
        if ($numOptions <= 0) { return []; }
        $result = [];
        foreach ($variants as $v) {
            $opts = [];
            for ($i = 1; $i <= $numOptions; $i++) {
                $val = $v['option'.$i] ?? null;
                if ($val === null || $val === '') { $opts = []; break; }
                $opts[] = (string) $val;
            }
            if (empty($opts)) { continue; }
            $v['__normalized_options'] = $opts;
            $result[] = $v;
        }
        return $result;
    }

    private function extractTopicFromEventId(string $eventId): ?string
    { $firstDot = strpos($eventId, '.'); if ($firstDot === false) { return null; } return substr($eventId, 0, $firstDot); }

    private function mapProductCreateInput(array $payload): array
    {
        $input = [];
        if (isset($payload['title'])) { $input['title'] = (string) $payload['title']; }
        if (array_key_exists('body_html', $payload)) { $input['descriptionHtml'] = $payload['body_html'] ?? null; }
        if (array_key_exists('vendor', $payload)) { $input['vendor'] = $payload['vendor']; }
        if (array_key_exists('product_type', $payload)) { $input['productType'] = $payload['product_type']; }
        if (array_key_exists('status', $payload) && is_string($payload['status'])) { $input['status'] = strtoupper($payload['status']); }
        if (array_key_exists('tags', $payload)) { $input['tags'] = $this->normalizeTags($payload['tags']); }
        if (array_key_exists('template_suffix', $payload)) { $input['templateSuffix'] = $payload['template_suffix']; }
        return $input;
    }

    private function mapProductUpdateInput(array $payload): array
    {
        $input = [];
        if (!empty($payload['admin_graphql_api_id'])) { $input['id'] = $payload['admin_graphql_api_id']; }
        if (isset($payload['title'])) { $input['title'] = (string) $payload['title']; }
        if (array_key_exists('body_html', $payload)) { $input['descriptionHtml'] = $payload['body_html'] ?? null; }
        if (array_key_exists('vendor', $payload)) { $input['vendor'] = $payload['vendor']; }
        if (array_key_exists('product_type', $payload)) { $input['productType'] = $payload['product_type']; }
        if (array_key_exists('status', $payload) && is_string($payload['status'])) { $input['status'] = strtoupper($payload['status']); }
        if (array_key_exists('tags', $payload)) { $input['tags'] = $this->normalizeTags($payload['tags']); }
        if (array_key_exists('template_suffix', $payload)) { $input['templateSuffix'] = $payload['template_suffix']; }
        return $input;
    }

    private function shouldCreateVariants(array $payload): bool
    { return !empty($payload['variants']) && is_array($payload['variants']); }

    private function mapVariantsForBulkCreate(array $variants): array
    {
        $result = [];
        foreach ($variants as $v) {
            $node = [];
            if (isset($v['sku'])) { $node['sku'] = (string) $v['sku']; }
            if (isset($v['price'])) { $node['price'] = (string) $v['price']; }
            if (isset($v['compare_at_price'])) { $node['compareAtPrice'] = (string) $v['compare_at_price']; }
            if (isset($v['barcode'])) { $node['barcode'] = (string) $v['barcode']; }
            if (array_key_exists('taxable', $v)) { $node['taxable'] = (bool) $v['taxable']; }
            if (array_key_exists('requires_shipping', $v)) { $node['requiresShipping'] = (bool) $v['requires_shipping']; }
            if (isset($v['tax_code'])) { $node['taxCode'] = (string) $v['tax_code']; }
            // Removed unsupported 'options' field from ProductVariantsBulkInput
            if (!empty($node)) { $result[] = $node; }
        }
        return $result;
    }

    private function mapVariantsForBulkUpdate(array $variants): array
    {
        $result = [];
        foreach ($variants as $v) {
            if (empty($v['admin_graphql_api_id'])) { continue; }
            $node = ['id' => $v['admin_graphql_api_id']];
            if (isset($v['sku'])) { $node['sku'] = (string) $v['sku']; }
            if (isset($v['price'])) { $node['price'] = (string) $v['price']; }
            if (isset($v['compare_at_price'])) { $node['compareAtPrice'] = (string) $v['compare_at_price']; }
            if (isset($v['barcode'])) { $node['barcode'] = (string) $v['barcode']; }
            if (array_key_exists('taxable', $v)) { $node['taxable'] = (bool) $v['taxable']; }
            if (array_key_exists('requires_shipping', $v)) { $node['requiresShipping'] = (bool) $v['requires_shipping']; }
            if (isset($v['tax_code'])) { $node['taxCode'] = (string) $v['tax_code']; }
            $result[] = $node;
        }
        return $result;
    }

    private function ensureProductOptions(string $productId, array $payload): bool
    {
        try {
            $optionNames = [];
            if (!empty($payload['options']) && is_array($payload['options'])) {
                foreach ($payload['options'] as $opt) { if (!empty($opt['name'])) { $optionNames[] = (string) $opt['name']; } }
            } else {
                for ($i = 1; $i <= 3; $i++) {
                    $hasAny = false;
                    if (!empty($payload['variants']) && is_array($payload['variants'])) {
                        foreach ($payload['variants'] as $v) { if (!empty($v['option'.$i])) { $hasAny = true; break; } }
                    }
                    if ($hasAny) { $optionNames[] = 'Option'.($i); }
                }
            }
            if (empty($optionNames)) { return false; }

            $optionsInput = [];
            foreach ($optionNames as $idx => $name) {
                $values = [];
                if (!empty($payload['variants'])) { foreach ($payload['variants'] as $v) { $val = $v['option'.($idx+1)] ?? null; if ($val !== null && $val !== '') { $values[] = (string) $val; } } }
                $values = array_values(array_unique($values));
                $optionsInput[] = [ 'name' => $name, 'values' => $values ];
            }

            $resp = $this->requestQuery(
                $this->graphQLQueryHelper->getProductOptionsCreateMutation(),
                [ 'productId' => $productId, 'options' => $optionsInput, 'variantStrategy' => 'CREATE' ]
            );
            $errors = $resp['data']['productOptionsCreate']['userErrors'] ?? [];
            if (!empty($errors)) { $this->logger->notice('productOptionsCreate userErrors', ['errors' => $errors]); }
            // If Shopify created all combinations, we should not bulk create more now
            return empty($errors);
        } catch (\Throwable $e) {
            $this->logger->notice('ensureProductOptions error (ignored)', ['exception' => $e]);
            return false;
        }
    }

    private function fetchExistingVariants(string $productId): array
    {
        $resp = $this->requestQuery($this->graphQLQueryHelper->getProductVariantsForQuery(), ['productId' => $productId]);
        $nodes = $resp['data']['product']['variants']['nodes'] ?? [];
        $bySku = [];
        foreach ($nodes as $node) {
            $sku = $node['sku'] ?? null;
            if ($sku) { $bySku[$sku] = $node; }
        }
        return $bySku;
    }

    private function injectVariantIdsFromSku(array $variants, array $existingBySku): array
    {
        foreach ($variants as &$v) {
            if (empty($v['admin_graphql_api_id']) && !empty($v['sku'])) {
                $sku = (string) $v['sku'];
                if (!empty($existingBySku[$sku]['id'])) { $v['admin_graphql_api_id'] = $existingBySku[$sku]['id']; }
            }
        }
        unset($v);
        return $variants;
    }

    private function filterNewVariants(array $variants, array $existingBySku): array
    {
        $result = [];
        foreach ($variants as $v) {
            $sku = $v['sku'] ?? null;
            if ($sku && isset($existingBySku[$sku])) { continue; }
            $result[] = $v;
        }
        return $result;
    }

    private function fetchExistingMediaPreviewUrls(string $productId): array
    {
        $resp = $this->requestQuery($this->graphQLQueryHelper->getProductMediaForQuery(), ['productId' => $productId]);
        $nodes = $resp['data']['product']['media']['nodes'] ?? [];
        $urls = [];
        foreach ($nodes as $node) {
            $url = null;
            if (isset($node['image']['url'])) { $url = $node['image']['url']; }
            if ($url) { $urls[$url] = true; }
        }
        return $urls;
    }

    private function mapMediaCreateInput(array $payload, array $existingUrls = []): array
    {
        $newMedia = [];
        if (array_key_exists('media', $payload) && is_array($payload['media']) && count($payload['media']) > 0) {
            foreach ($payload['media'] as $media) {
                $src = $media['preview_image']['src'] ?? null;
                if ($src && isset($existingUrls[$src])) { continue; }
                $newMedia[] = [ 'alt' => $media['alt'] ?? null, 'mediaContentType' => 'IMAGE', 'originalSource' => $src ];
            }
        }
        return $newMedia;
    }

    private function normalizeTags($tags): array
    { if (is_array($tags)) { return array_values(array_filter(array_map('strval', $tags), static fn($t) => $t !== '')); } if (is_string($tags)) { $parts = array_map('trim', explode(',', $tags)); return array_values(array_filter($parts, static fn($t) => $t !== '')); } return []; }

    private function requestQuery($query, $variables)
    { $url = $this->shopifySDK->GraphQL()->generateUrl(); if (is_string($url)) { $url = str_replace('/2022-04/', '/2022-07/', $url); } return $this->shopifySDK->GraphQL()->post($query, $url, false, $variables); }

    private function createEventTriggeredFile($name = null, $line = null)
    { try { $filepath = $this->getEventTriggeredFilePath($name); if ($this->filesystem->exists($filepath)) { $this->filesystem->appendToFile($filepath, ($line ?? '').\PHP_EOL); } else { $this->filesystem->dumpFile($filepath, ($line ?? '').\PHP_EOL); } } catch (\Exception $e) { /* no-op */ } }

    private function getEventTriggeredFilePath($name = null)
    { return __DIR__.'/../../var/'.$this->getEventTriggeredFileName($name); }

    private function getEventTriggeredFileName($name = null)
    { $name = preg_replace('`[^a-zA-Z0-9_-]+`', '_', ''.$name); return 'webhook_shopify_'.$name.'_event_triggered.txt'; }
}
