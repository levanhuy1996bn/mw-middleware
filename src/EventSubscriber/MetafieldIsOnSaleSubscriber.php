<?php

namespace App\EventSubscriber;

use Endertech\EcommerceMiddleware\Contracts\Connector\WithMetafieldsInterface;
use Endertech\EcommerceMiddleware\Contracts\DataMiddlewareInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\VariantInterface;
use Endertech\EcommerceMiddleware\Contracts\Product\DataMiddlewareInterface as ProductDataMiddlewareInterface;
use Endertech\EcommerceMiddleware\Contracts\Product\MappedProductPriceInterface;
use Endertech\EcommerceMiddleware\Contracts\Store\DoctrineAwareInterface;
use Endertech\EcommerceMiddleware\Contracts\Variant\DataMiddlewareInterface as VariantDataMiddlewareInterface;
use Endertech\EcommerceMiddlewareEvents\Core\Data\Product\DataMiddleware\HandleSuccessfulProductUpdateEvent;
use Endertech\EcommerceMiddlewareEvents\Core\Data\Variant\DataMiddleware\HandleSuccessfulVariantUpdateEvent;
use Endertech\EcommerceMiddlewareReport\Contracts\ReportMetafield\DataMiddlewareInterface as ReportMetafieldDataMiddlewareInterface;
use Endertech\EcommerceMiddlewareReport\Contracts\Store\ReportMetafieldStoreInterface;
use Endertech\EcommerceMiddlewareReport\Metafield\Data\Product\ReportMetafieldDataMiddlewareDecorator as ProductReportMetafieldDataMiddlewareDecorator;
use Endertech\EcommerceMiddlewareReport\Metafield\Data\Variant\ReportMetafieldDataMiddlewareDecorator as VariantReportMetafieldDataMiddlewareDecorator;
use Endertech\EcommerceMiddlewareReport\Metafield\Traits\Store\ReportMetafieldStoreAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MetafieldIsOnSaleSubscriber implements EventSubscriberInterface
{
    use ReportMetafieldStoreAwareTrait;

    const LOG_PRODUCT_IS_ON_SALE_STATUS_UPDATED = 'sleep_outfitters.product_is_on_sale_status_updated';

    public function __construct(ReportMetafieldStoreInterface $reportMetafieldStore)
    {
        $this->setReportMetafieldStore($reportMetafieldStore);
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            HandleSuccessfulProductUpdateEvent::class => 'updateProductIsOnSaleStatusFromProductUpdate',
            HandleSuccessfulVariantUpdateEvent::class => 'updateProductIsOnSaleStatusFromVariantUpdate',
        ];
    }

    // SOU-77: In Variant Update, allow Price to Sync and copy "isOnSale" metafield logic.
    // In Variant Update, let Compare At Price == Standard Price, and set isOnSale metafield to true if ANY variant has currentPrice < standardPrice, else, if NO variant has currentPrice < standardPrice, set isOnSale = false
    public function updateProductIsOnSaleStatusFromProductUpdate(HandleSuccessfulProductUpdateEvent $event)
    {
        /** @var DataMiddlewareInterface $dataMiddleware */
        $dataMiddleware = $event->getDataMiddleware();
        $product = $event->getProduct();
        $logContext = $event->getLogContext();

        if (!$product->getDataSourceProductId() || !$dataMiddleware instanceof ProductDataMiddlewareInterface) {
            return;
        }

        $startedAsProductIsOnSale = false;

        $isOnSale = false;

        /*
        $updateRequest = $this->createIsOnSaleRequest($dataMiddleware, $isOnSale, null, $product, $product->getDataSourceProductId(), true);

        try {
            $checkExistingRequest = $updateRequest;
            unset($checkExistingRequest['value']);

            $logContext['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_checkExistingRequest'] = $checkExistingRequest;

            $checkExisting = $this->getIsOnSaleMetafield($dataMiddleware, $product->getDataSourceProductId(), $checkExistingRequest);

            $logContext['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_checkExistingResponse'] = $checkExisting;

            if (count($checkExisting) > 0) {
                // there should only be one existing metafield
                $firstMetafield = reset($checkExisting);

                $firstMetafieldValue = $firstMetafield['value'] ?? null;
                if (in_array($firstMetafieldValue, [true, 'true', 1, '1'], true)) {
                    $startedAsProductIsOnSale = true;
                }

                $firstMetafieldId = $firstMetafield['id'] ?? null;
                if ($firstMetafieldId) {
                    $updateRequest['id'] = $firstMetafieldId;
                    unset($updateRequest['namespace']);
                    unset($updateRequest['key']);
                }
            }
        } catch (\Exception $e) {
            $logContext['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_checkExistingException'] = $e->getMessage();
        }

        if (!$startedAsProductIsOnSale) {
            // no need to update the is_on_sale status, allow VariantUpdate task to do that
            $event->setEventLogContext($logContext);

            return;
        }
        */

        $checkPrices = [];
        $variantPriceRequests = [];

        $productVariants = $event->getProductVariants();

        foreach ($productVariants as $productIndex => $variant) {
            $variantId = null;
            $priceRequest = [];

            if ($variant instanceof VariantInterface) {
                try {
                    $variantId = $variant->getId();

                    $dataDestination = $dataMiddleware->getDataDestination();
                    $dataMiddleware->setDataDestination(null); // skip hasProductCategoryNoPrice() logic in mapProductDetails()
                    [$productInsertDetails, $productData, $productPriceData] = $dataMiddleware->mapProductDetails($variant->getDataDestinationJson(), $variant->getSku(), $productIndex, $logContext);
                    $dataMiddleware->setDataDestination($dataDestination);

                    if ($productPriceData instanceof MappedProductPriceInterface) {
                        $priceRequest = $productPriceData->createDestinationProductPrice();
                    }
                } catch (\Exception $e) {
                    $logContext['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_variantPriceRequest_exceptions'][$variantId] = $e->getMessage();
                }
            }

            $variantPriceRequests[$variantId] = $priceRequest;

            if (!isset($priceRequest['price']) || !isset($priceRequest['compare_at_price'])) {
                continue;
            }

            // shopify 'price' is mapped from storis data.products.0.inventoryPrices.0.currentSellingPrice
            // shopify 'compare_at_price' is mapped from storis data.products.0.inventoryPrices.0.standardSellingPrice
            // compare the shopify values since they are exactly the same as the storis values as per the mapping above.
            if ($priceRequest['price'] < $priceRequest['compare_at_price']) {
                // currentPrice < standardPrice triggered
                $checkPrices[$variantId] = true;
            } else {
                $checkPrices[$variantId] = false;
            }
        }

        $logContext['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_variantPriceRequests'] = $variantPriceRequests;
        $logContext['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_checkPrices'] = $checkPrices;

        if (in_array(true, $checkPrices, true)) {
            $isOnSale = true;
        }

        $logContext['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_isOnSale'][$product->getId()] = $isOnSale;

        if (count($checkPrices) > 0
            && count($checkPrices) == count($productVariants)
            //&& !in_array(true, $checkPrices, true)
        ) {
            try {
                //$params = $updateRequest;
                $params = $this->createIsOnSaleRequest($dataMiddleware, $isOnSale, null, $product, $product->getDataSourceProductId());

                if (!$params) {
                    $logContext['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_skipped'][$product->getId()] = $isOnSale;
                } else {
                    $logContext['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_requests'][$product->getId()] = $params;

                    $response = $this->updateIsOnSaleMetafield($dataMiddleware, $product->getDataSourceProductId(), $params, null, $product);

                    $logContext['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_responses'][$product->getId()] = $response;
                }
            } catch (\Exception $e) {
                $logContext['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_exceptions'][$product->getId()] = $e->getMessage();
            }

            $logContext['dataSourceProductId'] = $product->getDataSourceProductId();
            $logContext['formattedProductIsOnSaleStatus'] = $isOnSale ? 'true' : 'false';

            $dataMiddleware->logInfo(static::LOG_PRODUCT_IS_ON_SALE_STATUS_UPDATED, 'Updated Product {dataSourceProductId} Is On Sale Status to {formattedProductIsOnSaleStatus}', $logContext);
        }

        $event->setEventLogContext($logContext);
    }

    // SOU-77: In Variant Update, allow Price to Sync and copy "isOnSale" metafield logic.
    // In Variant Update, let Compare At Price == Standard Price, and set isOnSale metafield to true if ANY variant has currentPrice < standardPrice, else, if NO variant has currentPrice < standardPrice, set isOnSale = false
    public function updateProductIsOnSaleStatusFromVariantUpdate(HandleSuccessfulVariantUpdateEvent $event)
    {
        /** @var DataMiddlewareInterface $dataMiddleware */
        $dataMiddleware = $event->getDataMiddleware();
        $variant = $event->getVariant();
        $logContext = $event->getLogContext();

        if (!$variant->getDataSourceProductId() || !$variant->getDataSourceVariantId() || !$dataMiddleware instanceof VariantDataMiddlewareInterface) {
            return;
        }

        // @see \Endertech\EcommerceMiddleware\Core\Data\Variant\AbstractVariantSource::updateVariantWithDetails()
        if (!isset($logContext['formattedProductInfoPrices'])
            || !isset($logContext['dataSourceProductVariantUpdateRequest'])
            || !isset($logContext['dataSourceProductVariantUpdateResponse'])
        ) {
            return;
        }

        $priceRequest = $logContext['dataSourceProductVariantUpdateRequest'];
        if (!isset($priceRequest['price']) || !isset($priceRequest['compare_at_price'])) {
            return;
        }

        // shopify 'price' is mapped from storis data.products.0.inventoryPrices.0.currentSellingPrice
        // shopify 'compare_at_price' is mapped from storis data.products.0.inventoryPrices.0.standardSellingPrice
        // compare the shopify values since they are exactly the same as the storis values as per the mapping above.
        if ($priceRequest['price'] < $priceRequest['compare_at_price']) {
            // currentPrice < standardPrice triggered, update Shopify Product metafield
            $isOnSale = true;

            try {
                $params = $this->createIsOnSaleRequest($dataMiddleware, $isOnSale, $variant, null, $variant->getDataSourceProductId());

                if (!$params) {
                    $logContext['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromVariantUpdate_skipped'][$variant->getId()] = $isOnSale;
                } else {
                    $logContext['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromVariantUpdate_requests'][$variant->getId()] = $params;

                    $response = $this->updateIsOnSaleMetafield($dataMiddleware, $variant->getDataSourceProductId(), $params, $variant);

                    $logContext['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromVariantUpdate_responses'][$variant->getId()] = $response;
                }
            } catch (\Exception $e) {
                $logContext['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromVariantUpdate_exceptions'][$variant->getId()] = $e->getMessage();
            }

            $logContext['sku'] = $variant->getSku();
            $logContext['dataSourceProductId'] = $variant->getDataSourceProductId();
            $logContext['formattedProductIsOnSaleStatus'] = $isOnSale ? 'true' : 'false';

            $dataMiddleware->logInfo(static::LOG_PRODUCT_IS_ON_SALE_STATUS_UPDATED, 'Updated Product {dataSourceProductId} Is On Sale Status to {formattedProductIsOnSaleStatus} using SKU {sku} prices', $logContext);

            $event->setEventLogContext($logContext);
        }
    }

    private function createIsOnSaleRequest(DataMiddlewareInterface $dataMiddleware, $isOnSale, $variant = null, $product = null, $productId = null, $forceCreateRequest = false)
    {
        $value = (bool) $isOnSale;

        $request['namespace'] = 'mw_marketing';
        $request['key'] = 'is_on_sale';
        $request['value'] = $value ? 'true' : 'false';
        $request['type'] = 'boolean';

        if (!$forceCreateRequest) {
            /*
            // 2024-07-03 it seems like this caching is having an issue, so disabling for now
            $dataMiddleware = $this->getReportMetafieldDataMiddleware($dataMiddleware);
            if ($dataMiddleware instanceof ReportMetafieldDataMiddlewareInterface) {
                $updatedRequest = $dataMiddleware->generateReportMetafieldUpdateRequest([
                    'metafield' => $request,
                    'options' => [
                        'reportMetafieldCacheSkipRequest' => true,
                        'reportMetafieldCacheTimeInterval' => '-2 hours',
                    ],
                ]);
                if (!$updatedRequest) {
                    return [];
                }
                $request += $updatedRequest;
            }
            */
        }

        return $request;
    }

    private function updateIsOnSaleMetafield(DataMiddlewareInterface $dataMiddleware, $productId, $params = [], $variant = null, $product = null)
    {
        if (!$productId || !$dataMiddleware->getDataSource()->getDataSourceApiConnector() instanceof WithMetafieldsInterface) {
            return null;
        }

        $dataMiddleware = $this->getReportMetafieldDataMiddleware($dataMiddleware);

        if (!$dataMiddleware instanceof ReportMetafieldDataMiddlewareInterface) {
            return null;
        }

        $logContext = [];

        $response = $dataMiddleware->updateReportMetafieldWithDetails($params, [
            //'variant' => $variant,
            'product' => $product,
            'productId' => $productId,
            'options' => [
                'fallbackMetafieldOwnerType' => 'product',
                'fallbackMetafieldOwnerId' => $productId,
                'fallbackMetafieldOwnerProductId' > $productId,
            ],
        ], null, $logContext);

        // reset any request owner data to allow the options to be used below
        $logContext['dataSourceMetafieldCreateRequestOwner'] = $logContext['dataSourceMetafieldUpdateRequestOwner'] = null;

        $dataMiddleware->storeReportMetafieldWithDetails($response, $params, [
            //'variant' => $variant,
            'product' => $product,
            'productId' => $productId,
            'options' => [
                'fallbackMetafieldOwnerType' => 'product',
                'fallbackMetafieldOwnerId' => $productId,
                'fallbackMetafieldOwnerProductId' > $productId,
            ],
        ], null, $logContext);

        return $response;
    }

    private function getIsOnSaleMetafield(DataMiddlewareInterface $dataMiddleware, $productId, $params = [])
    {
        if (!$dataMiddleware->getDataSource()->getDataSourceApiConnector() instanceof WithMetafieldsInterface) {
            return [];
        }

        return $dataMiddleware->getDataSource()->getDataSourceApiConnector()->getMetafield(null, $params, ['productId' => $productId]);
    }

    private function getReportMetafieldDataMiddleware(DataMiddlewareInterface $dataMiddleware)
    {
        if ($this->getReportMetafieldStore() instanceof DoctrineAwareInterface
            && $dataMiddleware instanceof DoctrineAwareInterface
            && $dataMiddleware->getObjectManager()
        ) {
            $this->getReportMetafieldStore()->setObjectManager($dataMiddleware->getObjectManager());
            $this->getReportMetafieldStore()->setDoctrine($dataMiddleware->getDoctrine());
        }

        if ($dataMiddleware instanceof ProductDataMiddlewareInterface) {
            $dataMiddleware = new ProductReportMetafieldDataMiddlewareDecorator($dataMiddleware);
            $dataMiddleware->setReportMetafieldStore($this->getReportMetafieldStore());
        }

        if ($dataMiddleware instanceof VariantDataMiddlewareInterface) {
            $dataMiddleware = new VariantReportMetafieldDataMiddlewareDecorator($dataMiddleware);
            $dataMiddleware->setReportMetafieldStore($this->getReportMetafieldStore());
        }

        if (!$dataMiddleware instanceof ReportMetafieldDataMiddlewareInterface) {
            return null;
        }

        return $dataMiddleware;
    }
}
