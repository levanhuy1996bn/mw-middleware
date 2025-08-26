<?php

namespace App\EcommerceMiddleware\Driver\Shopify\Data\Product;

use Endertech\EcommerceMiddleware\Contracts\Model\ProductInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\VariantInterface;
use Endertech\EcommerceMiddleware\Driver\Shopify\Data\Product\ShopifySource;

class AppProductShopifySource extends ShopifySource
{
    protected function createInsertProductVariantRequest($productInsertDetails, $productData, $productPriceData, $sku, $productIndex = null, &$logContext = null)
    {
        // disable insert
        return [];
    }

    protected function createInsertProductRequest($sourceVariant, $defaultTags, $productInsertDetails, $productData, $productPriceData, $sku, $productIndex = null, &$logContext = null)
    {
        // disable insert
        return [];
    }

    protected function createProductPublishRequest(ProductInterface $product, VariantInterface $variant, $productWithVariant, $productIndex = null, &$logContext = null)
    {
        // disable publish changes
        return [];
    }

    protected function createProductUnpublishRequest(ProductInterface $product, VariantInterface $variant, $productWithVariant, $productIndex = null, &$logContext = null)
    {
        // disable publish changes
        return [];
    }

    protected function createUpdateProductTagsRequest($updatedTags, $previousTags, $productInventoryLocationNamesWithQuantity, $productUpdateDetails, $productVariants, ProductInterface $product, $productIndex = null, &$logContext = null)
    {
        // disable tag updates
        return [];
    }

    protected function createGetMinWebStockAvailabilityMetaFieldRequest(ProductInterface $product, $productIndex = null, &$logContext = null)
    {
        // disable minimum_web_stock_availability metafield
        return [];
    }

    protected function createProductMinWebStockAvailabilityMetaFieldCreateRequest(ProductInterface $product, $minWebStockAvailability, $productIndex = null, &$logContext = null)
    {
        // disable minimum_web_stock_availability metafield
        return [];
    }

    protected function createProductMinWebStockAvailabilityMetaFieldUpdateRequest(ProductInterface $product, $minWebStockAvailability, $productIndex = null, &$logContext = null)
    {
        // disable minimum_web_stock_availability metafield
        return [];
    }
}
