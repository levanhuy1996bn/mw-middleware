<?php

namespace App\EventSubscriber;

use Doctrine\Persistence\ObjectManager;
use Endertech\EcommerceMiddleware\Contracts\Connector\WithMetafieldsInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\ConfigurationInterface;
use Endertech\EcommerceMiddleware\Contracts\Store\DoctrineAwareInterface;
use Endertech\EcommerceMiddleware\Contracts\Variant\DataMiddlewareInterface;
use Endertech\EcommerceMiddleware\Core\Data\Location\LocationVerificationMiddleware;
use Endertech\EcommerceMiddleware\Core\Traits\DataMiddlewareAwareTrait;
use Endertech\EcommerceMiddlewareEvents\Core\Data\Logger\TaskLogger\LogEvent;
use Endertech\EcommerceMiddlewareReport\Contracts\Store\ReportMetafieldStoreInterface;
use Endertech\EcommerceMiddlewareReport\Metafield\Data\Variant\ReportMetafieldDataMiddlewareDecorator as VariantReportMetafieldDataMiddlewareDecorator;
use Endertech\EcommerceMiddlewareReport\Metafield\Traits\Store\ReportMetafieldStoreAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MetafieldRetailWarehouseSubscriber implements EventSubscriberInterface
{
    use DataMiddlewareAwareTrait;
    use ReportMetafieldStoreAwareTrait;

    const LOG_RETAIL_WAREHOUSE_UPDATED = 'sleep_outfitters.retail_warehouse_updated';

    public function __construct(DataMiddlewareInterface $dataMiddleware, ReportMetafieldStoreInterface $reportMetafieldStore)
    {
        $this->setDataMiddleware($dataMiddleware);
        $this->setReportMetafieldStore($reportMetafieldStore);
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            LogEvent::class => 'updateRetailWarehouses',
        ];
    }

    public function updateRetailWarehouses(LogEvent $event)
    {
        $logContext = $event->getLogContext();
        $key = $logContext['messageKey'] ?? null;

        if (LocationVerificationMiddleware::LOG_LOCATION_VERIFICATION_DESTINATION_LOCATIONS_RETRIEVED === $key) {
            /** @var DataMiddlewareInterface $dataMiddleware */
            $dataMiddleware = $this->getDataMiddleware();
            if ($this->isUpdateRetailWarehouseLocations($dataMiddleware)) {
                $this->disableUpdateRetailWarehouseLocations($dataMiddleware);

                $this->updateRetailWarehouseLocations($dataMiddleware, $logContext);

                if ($event->getDataObject() instanceof LoggerInterface) {
                    $logContext['messageKey'] = static::LOG_RETAIL_WAREHOUSE_UPDATED;
                    $event->getDataObject()->info('Updated Retail Warehouses, Updated: {retailWarehouseUpdatedCount}, Error: {retailWarehouseErrorCount}, Total: {retailWarehouseTotalCount}', $logContext);
                }
            }
        }
    }

    private function isUpdateRetailWarehouseLocations(DataMiddlewareInterface $dataMiddleware)
    {
        $objectManager = $dataMiddleware->getObjectManager();

        if ($objectManager instanceof ObjectManager) {
            $configurationRepository = $objectManager->getRepository(ConfigurationInterface::class);
            $configClass = $configurationRepository->getClassName();

            $existingConfigSleepOutfittersUpdateRetailWarehouseLocations = $configurationRepository->findOneBy(['slug' => 'sleep-outfitters-update-retail-warehouse-locations']);
            if (!$existingConfigSleepOutfittersUpdateRetailWarehouseLocations instanceof ConfigurationInterface) {
                /** @var ConfigurationInterface $configSleepOutfittersUpdateRetailWarehouseLocations */
                $configSleepOutfittersUpdateRetailWarehouseLocations = new $configClass();
                $configSleepOutfittersUpdateRetailWarehouseLocations->setName('Sleep Outfitters Update Retail Warehouse Locations');
                $configSleepOutfittersUpdateRetailWarehouseLocations->setValue('');

                $objectManager->persist($configSleepOutfittersUpdateRetailWarehouseLocations);
                $objectManager->flush($configSleepOutfittersUpdateRetailWarehouseLocations);
            } elseif ($existingConfigSleepOutfittersUpdateRetailWarehouseLocations->getValue()) {
                return true;
            }
        }

        return false;
    }

    private function disableUpdateRetailWarehouseLocations(DataMiddlewareInterface $dataMiddleware)
    {
        $objectManager = $dataMiddleware->getObjectManager();

        if ($objectManager instanceof ObjectManager) {
            $configurationRepository = $objectManager->getRepository(ConfigurationInterface::class);

            $existingConfigSleepOutfittersUpdateRetailWarehouseLocations = $configurationRepository->findOneBy(['slug' => 'sleep-outfitters-update-retail-warehouse-locations']);
            if ($existingConfigSleepOutfittersUpdateRetailWarehouseLocations instanceof ConfigurationInterface) {
                $existingConfigSleepOutfittersUpdateRetailWarehouseLocations->setValue('');

                $objectManager->persist($existingConfigSleepOutfittersUpdateRetailWarehouseLocations);
                $objectManager->flush($existingConfigSleepOutfittersUpdateRetailWarehouseLocations);
            }
        }
    }

    private function updateRetailWarehouseLocations(DataMiddlewareInterface $dataMiddleware, &$logContext = null)
    {
        $key = $logContext['messageKey'] ?? null;
        if (LocationVerificationMiddleware::LOG_LOCATION_VERIFICATION_DESTINATION_LOCATIONS_RETRIEVED !== $key) {
            return;
        }

        // We'll want to store this data so the frontend can determine whether an item is available now at a location (has inventory) or within 48 hours (has inventory at its warehouse).
        // Each location can have a different warehouse which is determined by the "regionCode". 10 = 9610, 20 = 9620, 30 = 9630. If regionCode = "CL" or "US", skip.
        // To make this future compatible, let's basically take any numeric regionCode and prefix it with 96 before storing into Shopify metafield. This way, if they add new warehouses, we don't have to modify code.
        // The template logic will be responsible for determining whether the current location has inventory, and if not, looking up the associated warehouse location, then echoing the appropriate message.
        // The plan is to set up an additional meta field at the Shop level to maintain the relationship between retail location and warehouse.

        $dataDestinationLocations = $logContext['dataDestinationLocations']['data']['locations'] ?? [];

        $storisLocations = [];
        foreach ($dataDestinationLocations as $location) {
            $locationId = $location['id'] ?? null;
            $regionCode = $location['regionCode'] ?? null;
            if ($locationId && is_numeric($regionCode) && $regionCode > 0 && $regionCode < 100) {
                $storisLocations[''.$locationId] = '96'.$regionCode;
            }
        }

        $logContext['MetafieldRetailWarehouseSubscriber_updateRetailWarehouseLocations_locations'] = $storisLocations;
        $logContext['retailWarehouseTotalCount'] = count($storisLocations);

        $updatedCount = 0;
        $errorCount = 0;
        $loopIndex = 0;

        foreach ($storisLocations as $storisLocationId => $retailWarehouse) {
            try {
                $params = $this->createRetailWarehouseRequest($dataMiddleware, $retailWarehouse, $storisLocationId);

                //$logContext['MetafieldRetailWarehouseSubscriber_updateRetailWarehouseLocations_requests'][$storisLocationId] = $params;

                $response = $this->updateRetailWarehouseMetafield($dataMiddleware, $params, $storisLocationId);

                //$logContext['MetafieldRetailWarehouseSubscriber_updateRetailWarehouseLocations_responses'][$storisLocationId] = $response;

                ++$updatedCount;
            } catch (\Exception $e) {
                $logContext['MetafieldRetailWarehouseSubscriber_updateRetailWarehouseLocations_exceptions'][$storisLocationId] = $e->getMessage();

                ++$errorCount;
            }

            // prevent too many logs stored in memory
            $dataMiddleware->sendEntityChangesToDatabase($loopIndex);

            ++$loopIndex;
        }

        $logContext['retailWarehouseUpdatedCount'] = $updatedCount;
        $logContext['retailWarehouseErrorCount'] = $errorCount;
    }

    private function createRetailWarehouseRequest(DataMiddlewareInterface $dataMiddleware, $retailWarehouse, $storisLocationId)
    {
        $metafieldId = null;

        if ($this->getReportMetafieldStore() instanceof DoctrineAwareInterface
            && $dataMiddleware->getObjectManager()
        ) {
            $this->getReportMetafieldStore()->setObjectManager($dataMiddleware->getObjectManager());
            $this->getReportMetafieldStore()->setDoctrine($dataMiddleware->getDoctrine());
        }

        if ($this->getReportMetafieldStore()) {
            $metafieldId = $this->getReportMetafieldStore()->getReportMetafieldId('retail_warehouse', $storisLocationId, null, ['allowLocationIdFromMetafieldKey' => true]);
        }

        if ($metafieldId) {
            // edit metafield
            $request['id'] = $metafieldId; // Metafield ID from DataSource (Shopify)
            $request['namespace'] = 'retail_warehouse';
            $request['key'] = (string) $storisLocationId; // Location ID from DataDestination (Storis)
            $request['value'] = (int) $retailWarehouse;
            $request['type'] = 'integer';
        } else {
            // create metafield
            $request['namespace'] = 'retail_warehouse';
            $request['key'] = (string) $storisLocationId; // Location ID from DataDestination (Storis)
            $request['value'] = (int) $retailWarehouse;
            $request['type'] = 'integer';
        }

        return $request;
    }

    private function updateRetailWarehouseMetafield(DataMiddlewareInterface $dataMiddleware, $params = [], $storisLocationId = null)
    {
        if (!$dataMiddleware->getDataSource()->getDataSourceApiConnector() instanceof WithMetafieldsInterface) {
            return null;
        }

        if ($this->getReportMetafieldStore() instanceof DoctrineAwareInterface
            && $dataMiddleware->getObjectManager()
        ) {
            $this->getReportMetafieldStore()->setObjectManager($dataMiddleware->getObjectManager());
            $this->getReportMetafieldStore()->setDoctrine($dataMiddleware->getDoctrine());
        }

        $dataMiddleware = new VariantReportMetafieldDataMiddlewareDecorator($dataMiddleware);
        $dataMiddleware->setReportMetafieldStore($this->getReportMetafieldStore());

        $logContext = [];

        $response = $dataMiddleware->updateReportMetafieldWithDetails($params, [
            'options' => [
                'inventoryLocationIndex' => $storisLocationId,
            ],
        ], null, $logContext);

        $dataMiddleware->storeReportMetafieldWithDetails($response, $params, [
            'options' => [
                'inventoryLocationIndex' => $storisLocationId,
            ],
        ], null, $logContext);

        return $response;
    }
}
