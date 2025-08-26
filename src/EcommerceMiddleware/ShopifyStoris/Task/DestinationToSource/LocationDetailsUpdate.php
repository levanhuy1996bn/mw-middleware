<?php

namespace App\EcommerceMiddleware\ShopifyStoris\Task\DestinationToSource;

use Doctrine\Persistence\ObjectManager;
use Endertech\EcommerceMiddleware\Contracts\Location\LocationVerificationInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\ConfigurationInterface;
use Endertech\EcommerceMiddleware\Core\Data\Location\LocationVerificationMiddleware;
use Endertech\EcommerceMiddleware\Core\Task\Data\Variant\AbstractVariantTask;
use Endertech\EcommerceMiddlewareEvents\Core\Data\Logger\TaskLogger\LogEvent;
use Endertech\EcommerceMiddlewareEvents\Core\Traits\EventDispatcherAwareNullTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LocationDetailsUpdate extends AbstractVariantTask
{
    use EventDispatcherAwareNullTrait;

    private $previousLocationVerificationRateLimit = '10000';

    public function restoreConfigurationSettingsCallback(LogEvent $event)
    {
        $logContext = $event->getLogContext();
        $key = $logContext['messageKey'] ?? null;

        if (LocationVerificationMiddleware::LOG_LOCATION_VERIFICATION_SOURCE_LOCATIONS_RETRIEVED === $key) {
            $this->restoreConfigurationSettings(true);
        }
    }

    protected function doStartTask()
    {
        parent::doStartTask();

        if ($this->getDataMiddleware() instanceof LocationVerificationInterface) {
            $this->updateConfigurationSettings();

            $listener = [$this, 'restoreConfigurationSettingsCallback'];

            if ($this->getEventDispatcher() instanceof EventDispatcherInterface) {
                $this->getEventDispatcher()->addListener(LogEvent::class, $listener);
            }

            // @see \App\EventSubscriber\MetafieldLocationDetailsSubscriber::updateLocations()
            $this->getDataMiddleware()->verifyLocations();

            if ($this->getEventDispatcher() instanceof EventDispatcherInterface) {
                $this->getEventDispatcher()->removeListener(LogEvent::class, $listener);
            }

            $this->restoreConfigurationSettings();
        }
    }

    protected function doRetrieveResults()
    {
        return [];
    }

    protected function doProcessResult($result, $resultIndex = null)
    {
        // do nothing
    }

    private function updateConfigurationSettings()
    {
        $this->previousLocationVerificationRateLimit = ''.$this->getLocationVerificationRateLimit();
        $this->setLocationVerificationRateLimit('1');
        $this->setUpdateLocationDetails('1');
        $this->setUpdateRetailWarehouseLocations('1');
    }

    private function restoreConfigurationSettings($partialRestore = false)
    {
        $this->setLocationVerificationRateLimit($this->previousLocationVerificationRateLimit);

        if ($partialRestore) {
            return;
        }

        $this->setUpdateLocationDetails('');
        $this->setUpdateRetailWarehouseLocations('');
    }

    private function getLocationVerificationRateLimit()
    {
        $objectManager = $this->getObjectManager();

        if ($objectManager instanceof ObjectManager) {
            return $objectManager->getRepository(ConfigurationInterface::class)->getLocationVerificationRateLimit();
        }

        return null;
    }

    private function setLocationVerificationRateLimit($value)
    {
        $objectManager = $this->getObjectManager();

        if ($objectManager instanceof ObjectManager) {
            $configurationRepository = $objectManager->getRepository(ConfigurationInterface::class);

            $existingConfigLocationVerificationRateLimit = $configurationRepository->findOneBy(['slug' => 'location-verification-rate-limit']);
            if ($existingConfigLocationVerificationRateLimit instanceof ConfigurationInterface) {
                $existingConfigLocationVerificationRateLimit->setValue($value);

                $objectManager->persist($existingConfigLocationVerificationRateLimit);
                $objectManager->flush($existingConfigLocationVerificationRateLimit);
            }
        }
    }

    private function setUpdateLocationDetails($value)
    {
        $objectManager = $this->getObjectManager();

        if ($objectManager instanceof ObjectManager) {
            $configurationRepository = $objectManager->getRepository(ConfigurationInterface::class);

            $existingConfigSleepOutfittersUpdateLocationDetails = $configurationRepository->findOneBy(['slug' => 'sleep-outfitters-update-location-details']);
            if ($existingConfigSleepOutfittersUpdateLocationDetails instanceof ConfigurationInterface) {
                $existingConfigSleepOutfittersUpdateLocationDetails->setValue($value);

                $objectManager->persist($existingConfigSleepOutfittersUpdateLocationDetails);
                $objectManager->flush($existingConfigSleepOutfittersUpdateLocationDetails);
            }
        }
    }

    private function setUpdateRetailWarehouseLocations($value)
    {
        $objectManager = $this->getObjectManager();

        if ($objectManager instanceof ObjectManager) {
            $configurationRepository = $objectManager->getRepository(ConfigurationInterface::class);

            $existingConfigSleepOutfittersUpdateRetailWarehouseLocations = $configurationRepository->findOneBy(['slug' => 'sleep-outfitters-update-retail-warehouse-locations']);
            if ($existingConfigSleepOutfittersUpdateRetailWarehouseLocations instanceof ConfigurationInterface) {
                $existingConfigSleepOutfittersUpdateRetailWarehouseLocations->setValue($value);

                $objectManager->persist($existingConfigSleepOutfittersUpdateRetailWarehouseLocations);
                $objectManager->flush($existingConfigSleepOutfittersUpdateRetailWarehouseLocations);
            }
        }
    }
}
