<?php

namespace App\EventSubscriber;

use Endertech\EcommerceMiddleware\Contracts\Exception\DataSourceExceptionHandlerInterface;
use Endertech\EcommerceMiddleware\Contracts\Logger\LoggableInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\VariantInterface;
use Endertech\EcommerceMiddleware\Contracts\Variant\DataSourceInterface;
use Endertech\EcommerceMiddleware\Core\Traits\Connector\GraphQLFormatTrait;
use Endertech\EcommerceMiddlewareEvents\Core\Data\Variant\DataSource\UpdateVariantWithDetailsEvent as DataSourceUpdateVariantWithDetailsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InventorySubscriber implements EventSubscriberInterface
{
    use GraphQLFormatTrait;

    const LOG_VARIANT_INVENTORY_POLICY_UPDATE_ERROR = 'sleep_outfitters.variant_inventory_policy_update_error';
    const LOG_VARIANT_INVENTORY_POLICY_UPDATED = 'sleep_outfitters.variant_inventory_policy_updated';

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            DataSourceUpdateVariantWithDetailsEvent::class => 'updateShopifyProductVariantInventoryPolicy',
        ];
    }

    // SOU-50: In Variant Update, do not synchronize inventory, BUT do manage inventory policy based upon STORIS Purchase Status Code.
    public function updateShopifyProductVariantInventoryPolicy(DataSourceUpdateVariantWithDetailsEvent $event)
    {
        $dataSource = $event->getDataObject();
        if (!$dataSource instanceof DataSourceInterface) {
            return;
        }

        $variant = $event->getVariant();
        if (!$variant instanceof VariantInterface || !$variant->getSku() || !$variant->getDataSourceVariantId()) {
            return;
        }

        $logContext = $event->getLogContext();

        $logContext['sku'] = $variant->getSku();

        $inventoryPolicy = 'deny';

        $obsoleteStatusForContinue = [
            'A',
            'SPC',
            'DNS',
        ];

        $obsoleteStatus = $this->getObsoleteStatusFromVariant($variant);
        if (in_array($obsoleteStatus, $obsoleteStatusForContinue)) {
            $inventoryPolicy = 'continue';
        }

        /*
        if ($inventoryPolicy
            && 'deny' != $inventoryPolicy
            && $this->isVariantDropShipStatusRequired($variant, $dataSource, $logContext)
        ) {
            $inventoryPolicy = 'deny';

            $logContext['InventorySubscriber_updateShopifyProductVariantInventoryPolicy_isVariantDropShipStatusRequired'] = true;
        }
        */

        $updateInventoryPolicyRequest = [];

        if ($inventoryPolicy) {
            $updateInventoryPolicyRequest['inventory_policy'] = $inventoryPolicy;
        }

        $logContext['InventorySubscriber_updateShopifyProductVariantInventoryPolicy_updateInventoryPolicyRequest'] = $updateInventoryPolicyRequest;

        if (!$updateInventoryPolicyRequest) {
            $event->setEventLogContext($logContext);

            return;
        }

        $skipRequest = false;
        if (isset($logContext['dataSourceProductVariantUpdateResponse'])
            && isset($logContext['dataSourceProductVariantUpdateResponse']['id'])
            && $variant->getDataSourceVariantId()
            && $variant->getDataSourceVariantId() == $logContext['dataSourceProductVariantUpdateResponse']['id']
            && isset($logContext['dataSourceProductVariantUpdateResponse']['inventory_policy'])
            && isset($updateInventoryPolicyRequest['inventory_policy'])
            && $updateInventoryPolicyRequest['inventory_policy']
            && $updateInventoryPolicyRequest['inventory_policy'] == $logContext['dataSourceProductVariantUpdateResponse']['inventory_policy']
        ) {
            // At this point, there was a recent API call that updated the variant and returned the current data.
            // In this current data, there is already an inventory_policy value that is the same as what will be sent.
            // So, this request can be skipped entirely since the value isn't changing.
            $skipRequest = true;
        }
        // also check for the GraphQL API data
        if (isset($logContext['dataSourceProductVariantUpdateResponse'])
            && isset($logContext['dataSourceProductVariantUpdateResponse']['id'])
            && $variant->getDataSourceVariantId()
            && $variant->getDataSourceVariantId() == $this->normalizeMiddlewareId($logContext['dataSourceProductVariantUpdateResponse']['id'])
            && isset($logContext['dataSourceProductVariantUpdateResponse']['inventoryPolicy'])
            && isset($updateInventoryPolicyRequest['inventory_policy'])
            && $updateInventoryPolicyRequest['inventory_policy']
            && $updateInventoryPolicyRequest['inventory_policy'] == strtolower(''.$logContext['dataSourceProductVariantUpdateResponse']['inventoryPolicy'])
        ) {
            // At this point, there was a recent API call that updated the variant and returned the current data.
            // In this current data, there is already an inventoryPolicy value that is the same as what will be sent.
            // So, this request can be skipped entirely since the value isn't changing.
            $skipRequest = true;
        }

        try {
            if ($skipRequest) {
                $logContext['InventorySubscriber_updateShopifyProductVariantInventoryPolicy_updateInventoryPolicy_skipped'] = $updateInventoryPolicyRequest;
            } else {
                $response = $dataSource->getDataSourceApiConnector()->updateProductVariant($variant->getDataSourceProductId(), $variant->getDataSourceVariantId(), $updateInventoryPolicyRequest);

                $logContext['InventorySubscriber_updateShopifyProductVariantInventoryPolicy_updateInventoryPolicyResponse'] = $response;
            }

            if ($dataSource instanceof LoggableInterface
                || is_callable([$dataSource, 'logInfo'])
            ) {
                $logContext['updatedInventoryPolicy'] = $updateInventoryPolicyRequest['inventory_policy'] ?? 'unknown';

                $dataSource->logInfo(
                    static::LOG_VARIANT_INVENTORY_POLICY_UPDATED,
                    'Successfully Updated Inventory Policy for Variant: {sku} to {updatedInventoryPolicy}',
                    $logContext
                );
            }
        } catch (\Exception $e) {
            $event->setEventLogContext($logContext);

            if ($dataSource instanceof DataSourceExceptionHandlerInterface
                || is_callable([$dataSource, 'createSourceExceptionFromPreviousException'])
            ) {
                throw $dataSource->createSourceExceptionFromPreviousException($e, static::LOG_VARIANT_INVENTORY_POLICY_UPDATE_ERROR, 'API Error Updating Inventory Policy for Variant :: {sku}', $logContext, null, $variant);
            }
        }

        $event->setEventLogContext($logContext);
    }

    private function getObsoleteStatusFromVariant(VariantInterface $variant)
    {
        $obsoleteStatus = null;
        $destResponse = $variant->getDataDestinationJson();

        // test for STORIS API v2 data structure and presence of key. obsoleteStatus value can be null, so must check for array key.
        if (isset($destResponse['data'])
            && isset($destResponse['data']['products'])
            && isset($destResponse['data']['products'][0])
            && isset($destResponse['data']['products'][0]['purchaseType'])
            && is_array($destResponse['data']['products'][0]['purchaseType'])
            && array_key_exists('obsoleteStatus', $destResponse['data']['products'][0]['purchaseType'])
        ) {
            $obsoleteStatus = $destResponse['data']['products'][0]['purchaseType']['obsoleteStatus'];
        }

        return $obsoleteStatus;
    }
}
