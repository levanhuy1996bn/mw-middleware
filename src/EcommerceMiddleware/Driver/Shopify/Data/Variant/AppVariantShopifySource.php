<?php

namespace App\EcommerceMiddleware\Driver\Shopify\Data\Variant;

use Endertech\EcommerceMiddleware\Contracts\Model\VariantInterface;
use Endertech\EcommerceMiddleware\Driver\Shopify\Data\Variant\ShopifySource;

class AppVariantShopifySource extends ShopifySource
{
    protected function createInventoryLevelUpdateRequest($quantity, $inventoryLocation, VariantInterface $variant, $dataSourceInventoryLocation, $inventoryLocationIndex = null, &$logContext = null)
    {
        // SOU-50 disable all inventory updates
        return [];
    }
}
