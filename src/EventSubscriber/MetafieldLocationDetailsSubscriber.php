<?php

namespace App\EventSubscriber;

use App\Location\YextLocation;
use Doctrine\Persistence\ObjectManager;
use Endertech\EcommerceMiddleware\Contracts\Connector\WithMetafieldsInterface;
use Endertech\EcommerceMiddleware\Contracts\Connector\WithSendRequestInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\ConfigurationInterface;
use Endertech\EcommerceMiddleware\Contracts\Store\DoctrineAwareInterface;
use Endertech\EcommerceMiddleware\Contracts\Variant\DataMiddlewareInterface;
use Endertech\EcommerceMiddleware\Core\Data\Location\LocationVerificationMiddleware;
use Endertech\EcommerceMiddleware\Core\Traits\DataMiddlewareAwareTrait;
use Endertech\EcommerceMiddlewareEvents\Core\Data\Logger\TaskLogger\LogEvent;
use Endertech\EcommerceMiddlewareReport\Contracts\Store\ReportMetafieldStoreAwareInterface;
use Endertech\EcommerceMiddlewareReport\Contracts\Store\ReportMetafieldStoreInterface;
use Endertech\EcommerceMiddlewareReport\Metafield\Data\Variant\ReportMetafieldDataMiddlewareDecorator as VariantReportMetafieldDataMiddlewareDecorator;
use Endertech\EcommerceMiddlewareReport\Metafield\Traits\Store\ReportMetafieldStoreAwareTrait;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class MetafieldLocationDetailsSubscriber implements EventSubscriberInterface, ReportMetafieldStoreAwareInterface
{
    use DataMiddlewareAwareTrait;
    use ReportMetafieldStoreAwareTrait;

    const LOG_LOCATION_DETAILS_UPDATED = 'sleep_outfitters.location_details_updated';

    /**
     * @var Provider
     */
    private $googleMapsGeocoder;

    /**
     * @var SluggerInterface
     */
    private $slugger;

    /**
     * @var YextLocation
     */
    private $yextLocation;

    public function __construct(DataMiddlewareInterface $dataMiddleware, ReportMetafieldStoreInterface $reportMetafieldStore, Provider $googleMapsGeocoder, SluggerInterface $slugger, YextLocation $yextLocation)
    {
        $this->setDataMiddleware($dataMiddleware);
        $this->setReportMetafieldStore($reportMetafieldStore);
        $this->googleMapsGeocoder = $googleMapsGeocoder;
        $this->slugger = $slugger;
        $this->yextLocation = $yextLocation;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            LogEvent::class => 'updateLocations',
        ];
    }

    public function getGoogleMapsGeocoder()
    {
        return $this->googleMapsGeocoder;
    }

    public function getYextLocation()
    {
        return $this->yextLocation;
    }

    public function updateLocations(LogEvent $event)
    {
        $logContext = $event->getLogContext();
        $key = $logContext['messageKey'] ?? null;

        if (LocationVerificationMiddleware::LOG_LOCATION_VERIFICATION_DESTINATION_LOCATIONS_RETRIEVED === $key) {
            /** @var DataMiddlewareInterface $dataMiddleware */
            $dataMiddleware = $this->getDataMiddleware();
            if ($this->isUpdateLocationDetails($dataMiddleware)) {
                $this->disableUpdateLocationDetails($dataMiddleware);

                $this->updateLocationDetails($dataMiddleware, $logContext);

                if ($event->getDataObject() instanceof LoggerInterface) {
                    $logContext['messageKey'] = static::LOG_LOCATION_DETAILS_UPDATED;
                    $event->getDataObject()->info('Updated Location Details, Updated: {locationDetailsUpdatedCount}, Deleted: {locationDetailsDeletedCount}, Error: {locationDetailsErrorCount}, Total: {locationDetailsTotalCount}', $logContext);
                }
            }
        }
    }

    // SOU-51: In Location Sync, update logic to reflect new SOU stores.
    public static function checkIsAllowedLocationId($locationId)
    {
        if (is_numeric($locationId)
            && (
                // SOU-215: Update location sync to remove BedPros stores from SOU Store Locator
                //($locationId >= 5000 && $locationId <= 5499)
                ($locationId >= 5000 && $locationId <= 5199)
                || 5564 == $locationId
                // SOU-101: In Middleware, ensure that locations > 9000 do not get set as Shopify Metafields.
                //|| $locationId >= 9000
            )
        ) {
            return true;
        }

        return false;
    }

    public static function checkIsNonPhysicalLocation($locationDetails = [], $storisLocationId = null, $dataDestinationLocations = [])
    {
        /*
        In Middleware Location Sync, exclude non-physical locations.

        Exclude any location where locationType IS NOT == 2
        Of the remaining, exclude any:
            with District Code:
                22
                21
                24
                23
                US
            or Region Code:
                CL
                US
        If existing location with such value is present in meta data, delete it.
        */
        $allowedLocationTypes = [
            '2',
        ];
        $excludedDistrictCodes = [
            '22',
            '21',
            '24',
            '23',
            'US',
        ];
        $excludedRegionCodes = [
            'CL',
            'US',
        ];

        foreach ($dataDestinationLocations as $location) {
            $locationId = $location['id'] ?? null;
            if ($locationId && $locationId == $storisLocationId) {
                $locationType = $location['locationType'] ?? null;
                if (is_numeric($locationType)) {
                    if (in_array($locationType, $allowedLocationTypes)) {
                        // included
                    } else {
                        // excluded
                        return true;
                    }
                }
                $districtCode = $location['districtCode'] ?? null;
                if (in_array($districtCode, $excludedDistrictCodes)) {
                    // excluded
                    return true;
                }
                $regionCode = $location['regionCode'] ?? null;
                if (in_array($regionCode, $excludedRegionCodes)) {
                    // excluded
                    return true;
                }
            }
        }

        return false;
    }

    public static function checkIsTestLocation($locationDetails = [], $storisLocationId = null, $dataDestinationLocations = [])
    {
        // In Middleware Location Sync, exclude location if name includes " Test ".
        $locationName = ($locationDetails['name'] ?? '') ?: '';
        if (false !== strpos($locationName, ' Test ')
            || false !== strpos($locationName, 'Test ')
        ) {
            return true;
        }

        return false;
    }

    private function isUpdateLocationDetails(DataMiddlewareInterface $dataMiddleware)
    {
        $objectManager = $dataMiddleware->getObjectManager();

        if ($objectManager instanceof ObjectManager) {
            $configurationRepository = $objectManager->getRepository(ConfigurationInterface::class);
            $configClass = $configurationRepository->getClassName();

            $existingConfigSleepOutfittersUpdateLocationDetails = $configurationRepository->findOneBy(['slug' => 'sleep-outfitters-update-location-details']);
            if (!$existingConfigSleepOutfittersUpdateLocationDetails instanceof ConfigurationInterface) {
                /** @var ConfigurationInterface $configSleepOutfittersUpdateLocationDetails */
                $configSleepOutfittersUpdateLocationDetails = new $configClass();
                $configSleepOutfittersUpdateLocationDetails->setName('Sleep Outfitters Update Location Details');
                $configSleepOutfittersUpdateLocationDetails->setValue('');

                $objectManager->persist($configSleepOutfittersUpdateLocationDetails);
                $objectManager->flush($configSleepOutfittersUpdateLocationDetails);
            } elseif ($existingConfigSleepOutfittersUpdateLocationDetails->getValue()) {
                return true;
            }
        }

        return false;
    }

    private function disableUpdateLocationDetails(DataMiddlewareInterface $dataMiddleware)
    {
        $objectManager = $dataMiddleware->getObjectManager();

        if ($objectManager instanceof ObjectManager) {
            $configurationRepository = $objectManager->getRepository(ConfigurationInterface::class);

            $existingConfigSleepOutfittersUpdateLocationDetails = $configurationRepository->findOneBy(['slug' => 'sleep-outfitters-update-location-details']);
            if ($existingConfigSleepOutfittersUpdateLocationDetails instanceof ConfigurationInterface) {
                $existingConfigSleepOutfittersUpdateLocationDetails->setValue('');

                $objectManager->persist($existingConfigSleepOutfittersUpdateLocationDetails);
                $objectManager->flush($existingConfigSleepOutfittersUpdateLocationDetails);
            }
        }
    }

    private function updateLocationDetails(DataMiddlewareInterface $dataMiddleware, &$logContext = null)
    {
        $key = $logContext['messageKey'] ?? null;
        if (LocationVerificationMiddleware::LOG_LOCATION_VERIFICATION_DESTINATION_LOCATIONS_RETRIEVED !== $key) {
            return;
        }

        if (!$this->getGoogleMapsGeocoder()) {
            return;
        }

        // Get address of store and use Google Geocoding API to retrieve Lat and Long

        $dataDestinationLocations = $logContext['dataDestinationLocations']['data']['locations'] ?? [];

        $stateLocations = [];
        $cityStateLocations = [];

        $storisLocations = [];
        foreach ($dataDestinationLocations as $location) {
            $locationId = $location['id'] ?? null;

            if (!$this->isAllowedLocationId($locationId)) {
                continue;
            }

            $locationName = $location['description'] ?? '';
            $locationAddress = $location['locationAddress'] ?? [];
            // In Middleware Location Sync, add Location Transfer Routes to the data.
            $locationTransferRoutes = $location['transferRoutes'] ?? [];

            $address = $locationAddress['address1'] ?? '';
            if (isset($locationAddress['address2']) && $locationAddress['address2']) {
                $address .= ' '.$locationAddress['address2'];
            }
            if (isset($locationAddress['city']) && $locationAddress['city']) {
                $address .= ' '.$locationAddress['city'];
            }
            if (isset($locationAddress['state']) && $locationAddress['state']) {
                $address .= ' '.$locationAddress['state'];
            }
            if (isset($locationAddress['zipCode']) && $locationAddress['zipCode']) {
                $address .= ' '.$locationAddress['zipCode'];
            }

            $phoneNumber = $locationAddress['phoneNumber'] ?? '';

            if ($locationId && $address) {
                //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_geocoder_addresses'][''.$locationId] = $address;

                try {
                    $results = $this->getGoogleMapsGeocoder()->geocodeQuery(GeocodeQuery::create($address));
                    foreach ($results as $result) {
                        /* @var \Geocoder\Provider\GoogleMaps\Model\GoogleAddress $result */
                        $storisLocations[''.$locationId] = [
                            'lat' => $result->getCoordinates()->getLatitude(),
                            'long' => $result->getCoordinates()->getLongitude(),
                            'name' => $locationName,
                            'address' => $result->getFormattedAddress() ?? $address,
                            'phone' => $phoneNumber,
                            'transferRoutes' => $locationTransferRoutes,
                        ];
                        $this->addStateLocation($stateLocations, $storisLocations[''.$locationId], $result, $address, $locationId, $locationName, $locationAddress, $dataDestinationLocations, $logContext);
                        $this->addCityStateLocation($cityStateLocations, $storisLocations[''.$locationId], $result, $address, $locationId, $locationName, $locationAddress, $dataDestinationLocations, $logContext);
                        break; // just the first one
                    }
                } catch (\Exception $e) {
                    // do nothing
                    $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_geocoder_exceptions'][''.$locationId] = $e->getMessage();
                }
            }
        }

        // In Middleware, update Location Data into Shopify once per day and include data from Yext.
        foreach ($storisLocations as $storisLocationId => $locationDetails) {
            try {
                $yextLocation = $this->getYextLocation()->getLocationById($storisLocationId)->toArray();

                $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_yextLocation_responses'][$storisLocationId] = $yextLocation;

                $storisLocations[$storisLocationId]['googlePlaceId'] = $yextLocation['response']['docs'][0]['googlePlaceId'] ?? null;
                $storisLocations[$storisLocationId]['hours'] = $yextLocation['response']['docs'][0]['hours'] ?? null;
            } catch (\Exception $e) {
                // do nothing
                $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_yextLocation_exceptions'][$storisLocationId] = $e->getMessage();
            }
        }

        $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_locations'] = $storisLocations;
        $logContext['locationDetailsTotalCount'] = count($storisLocations);

        $updatedCount = 0;
        $deletedCount = 0;
        $errorCount = 0;
        $loopIndex = 0;

        foreach ($storisLocations as $storisLocationId => $locationDetails) {
            try {
                $params = $this->createLocationDetailsRequest($dataMiddleware, $locationDetails, $storisLocationId);

                $isDeleteRequest = $this->isDeleteShopMetafieldRequest($dataMiddleware, $params, [
                    'middleware_delete_shop_metafield' => !$this->isAllowedLocationId($storisLocationId) || $this->isNonPhysicalLocation($locationDetails, $storisLocationId, $dataDestinationLocations) || $this->isTestLocation($locationDetails, $storisLocationId, $dataDestinationLocations),
                ], $storisLocationId);

                if ($isDeleteRequest) {
                    //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_delete_requests'][$storisLocationId] = $params;

                    $response = $this->deleteLocationDetailsMetafield($dataMiddleware, $params, $storisLocationId);

                    //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_delete_responses'][$storisLocationId] = $response;

                    ++$deletedCount;
                } else {
                    //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_requests'][$storisLocationId] = $params;

                    $response = $this->updateLocationDetailsMetafield($dataMiddleware, $params, $storisLocationId);

                    //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_responses'][$storisLocationId] = $response;

                    ++$updatedCount;
                }
            } catch (\Exception $e) {
                $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_exceptions'][$storisLocationId] = $e->getMessage();

                ++$errorCount;
            }

            // prevent too many logs stored in memory
            $dataMiddleware->sendEntityChangesToDatabase($loopIndex);

            ++$loopIndex;
        }

        $logContext['locationDetailsUpdatedCount'] = $updatedCount;
        $logContext['locationDetailsDeletedCount'] = $deletedCount;
        $logContext['locationDetailsErrorCount'] = $errorCount;

        $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_stateLocations'] = $stateLocations;
        $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_cityStateLocations'] = $cityStateLocations;

        if ($stateLocations) {
            try {
                $params = $this->createLocationsStatesRequest($dataMiddleware, $stateLocations);

                //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_stateLocations_requests'][] = $params;

                $response = $this->updateLocationsStatesMetafield($dataMiddleware, $params);

                //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_stateLocations_responses'][] = $response;
            } catch (\Exception $e) {
                $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_stateLocations_exceptions'][] = $e->getMessage();
            }
        }

        $loopIndex = 0;

        foreach ($cityStateLocations as $stateSlug => $cityStateLocation) {
            try {
                $params = $this->createLocationsStateCitiesRequest($dataMiddleware, $cityStateLocation, $stateSlug, $stateLocations);

                //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_cityStateLocations_requests'][$stateSlug] = $params;

                $response = $this->updateLocationsStateCitiesMetafield($dataMiddleware, $params);

                //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_cityStateLocations_responses'][$stateSlug] = $response;
            } catch (\Exception $e) {
                $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_cityStateLocations_exceptions'][$stateSlug] = $e->getMessage();
            }

            // prevent too many logs stored in memory
            $dataMiddleware->sendEntityChangesToDatabase($loopIndex);

            ++$loopIndex;
        }

        // This MetafieldStorefrontVisibilityCreate code was working before the Shopify 2025-01 version
        /*
        // In Middleware, update Location metafield logic to execute the Visibility mutation.
        $metafieldStorefrontVisibilityCreateRequests = $this->generateMetafieldStorefrontVisibilityCreateRequests($dataDestinationLocations, $stateLocations);
        $metafieldStorefrontVisibilityCreateMutation = $this->getMetafieldStorefrontVisibilityCreateMutation();

        $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldStorefrontVisibilityCreate_mutation'] = $metafieldStorefrontVisibilityCreateMutation;

        $loopIndex = 0;

        foreach ($metafieldStorefrontVisibilityCreateRequests as $metafieldStorefrontVisibilityCreateRequestIndex => $metafieldStorefrontVisibilityCreateRequest) {
            try {
                //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldStorefrontVisibilityCreate_requests'][$metafieldStorefrontVisibilityCreateRequestIndex] = $metafieldStorefrontVisibilityCreateRequest;

                $response = $this->requestGraphQLQuery($dataMiddleware, $metafieldStorefrontVisibilityCreateMutation, $metafieldStorefrontVisibilityCreateRequest);

                //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldStorefrontVisibilityCreate_responses'][$metafieldStorefrontVisibilityCreateRequestIndex] = $response;
            } catch (\Exception $e) {
                $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldStorefrontVisibilityCreate_exceptions'][$metafieldStorefrontVisibilityCreateRequestIndex] = $e->getMessage();
            }

            // prevent too many logs stored in memory
            $dataMiddleware->sendEntityChangesToDatabase($loopIndex);

            ++$loopIndex;
        }
        */

        $metafieldDefinitionCreateWithStorefrontVisibilityRequests = $this->generateMetafieldDefinitionCreateWithStorefrontVisibilityRequests($dataDestinationLocations, $stateLocations);
        $metafieldDefinitionCreateWithStorefrontVisibilityMutation = $this->getMetafieldDefinitionCreateWithStorefrontVisibilityMutation();

        $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldDefinitionCreateWithStorefrontVisibility_mutation'] = $metafieldDefinitionCreateWithStorefrontVisibilityMutation;

        // skip the creation until needed, see the update exception handling below
        /*
        $loopIndex = 0;

        foreach ($metafieldDefinitionCreateWithStorefrontVisibilityRequests as $metafieldDefinitionCreateWithStorefrontVisibilityRequestIndex => $metafieldDefinitionCreateWithStorefrontVisibilityRequest) {
            try {
                //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldDefinitionCreateWithStorefrontVisibility_requests'][$metafieldDefinitionCreateWithStorefrontVisibilityRequestIndex] = $metafieldDefinitionCreateWithStorefrontVisibilityRequest;

                $response = $this->requestGraphQLQuery($dataMiddleware, $metafieldDefinitionCreateWithStorefrontVisibilityMutation, $metafieldDefinitionCreateWithStorefrontVisibilityRequest);

                //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldDefinitionCreateWithStorefrontVisibility_responses'][$metafieldDefinitionCreateWithStorefrontVisibilityRequestIndex] = $response;
            } catch (\Exception $e) {
                $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldDefinitionCreateWithStorefrontVisibility_exceptions'][$metafieldDefinitionCreateWithStorefrontVisibilityRequestIndex] = $e->getMessage();
            }

            // prevent too many logs stored in memory
            $dataMiddleware->sendEntityChangesToDatabase($loopIndex);

            ++$loopIndex;
        }
        */

        // skipping the metafield storefront visibility updates for now until the Shopify 2025-01 version changes are figured out
        /*
        $metafieldDefinitionUpdateWithStorefrontVisibilityRequests = $this->generateMetafieldDefinitionUpdateWithStorefrontVisibilityRequests($dataDestinationLocations, $stateLocations);
        $metafieldDefinitionUpdateWithStorefrontVisibilityMutation = $this->getMetafieldDefinitionUpdateWithStorefrontVisibilityMutation();

        $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldDefinitionUpdateWithStorefrontVisibility_mutation'] = $metafieldDefinitionUpdateWithStorefrontVisibilityMutation;

        $loopIndex = 0;

        foreach ($metafieldDefinitionUpdateWithStorefrontVisibilityRequests as $metafieldDefinitionUpdateWithStorefrontVisibilityRequestIndex => $metafieldDefinitionUpdateWithStorefrontVisibilityRequest) {
            try {
                //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldDefinitionUpdateWithStorefrontVisibility_requests'][$metafieldDefinitionUpdateWithStorefrontVisibilityRequestIndex] = $metafieldDefinitionUpdateWithStorefrontVisibilityRequest;

                $response = $this->requestGraphQLQuery($dataMiddleware, $metafieldDefinitionUpdateWithStorefrontVisibilityMutation, $metafieldDefinitionUpdateWithStorefrontVisibilityRequest);

                //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldDefinitionUpdateWithStorefrontVisibility_responses'][$metafieldDefinitionUpdateWithStorefrontVisibilityRequestIndex] = $response;
            } catch (\Exception $e) {
                $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldDefinitionUpdateWithStorefrontVisibility_exceptions'][$metafieldDefinitionUpdateWithStorefrontVisibilityRequestIndex] = $e->getMessage();

                // usually the metafield definition needs to be created first, so try that before updating again
                $metafieldDefinitionCreateWithStorefrontVisibilityRequestIndex = $metafieldDefinitionUpdateWithStorefrontVisibilityRequestIndex;
                $metafieldDefinitionCreateWithStorefrontVisibilityRequest = $metafieldDefinitionCreateWithStorefrontVisibilityRequests[$metafieldDefinitionCreateWithStorefrontVisibilityRequestIndex] ?? null;
                if ($metafieldDefinitionCreateWithStorefrontVisibilityRequest) {
                    // Error: "Stores can only have 250 definitions for each store resource."  RESOURCE_TYPE_LIMIT_EXCEEDED
                    // It seems that there is a limit on the number of metafield definitions that can be created, so disable this for now
                    //try {
                    //    //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldDefinitionCreateWithStorefrontVisibility_requests'][$metafieldDefinitionCreateWithStorefrontVisibilityRequestIndex] = $metafieldDefinitionCreateWithStorefrontVisibilityRequest;
                    //
                    //    $response = $this->requestGraphQLQuery($dataMiddleware, $metafieldDefinitionCreateWithStorefrontVisibilityMutation, $metafieldDefinitionCreateWithStorefrontVisibilityRequest);
                    //
                    //    //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldDefinitionCreateWithStorefrontVisibility_responses'][$metafieldDefinitionCreateWithStorefrontVisibilityRequestIndex] = $response;
                    //} catch (\Exception $ex) {
                    //    $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldDefinitionCreateWithStorefrontVisibility_exceptions'][$metafieldDefinitionCreateWithStorefrontVisibilityRequestIndex] = $ex->getMessage();
                    //}
                    try {
                        //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldDefinitionUpdateWithStorefrontVisibility_requests_retry'][$metafieldDefinitionUpdateWithStorefrontVisibilityRequestIndex] = $metafieldDefinitionUpdateWithStorefrontVisibilityRequest;

                        $response = $this->requestGraphQLQuery($dataMiddleware, $metafieldDefinitionUpdateWithStorefrontVisibilityMutation, $metafieldDefinitionUpdateWithStorefrontVisibilityRequest);

                        //$logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldDefinitionUpdateWithStorefrontVisibility_responses_retry'][$metafieldDefinitionUpdateWithStorefrontVisibilityRequestIndex] = $response;
                    } catch (\Exception $exc) {
                        $logContext['MetafieldLocationDetailsSubscriber_updateLocationDetails_metafieldDefinitionUpdateWithStorefrontVisibility_exceptions_retry'][$metafieldDefinitionUpdateWithStorefrontVisibilityRequestIndex] = $exc->getMessage();
                    }
                }
            }

            // prevent too many logs stored in memory
            $dataMiddleware->sendEntityChangesToDatabase($loopIndex);

            ++$loopIndex;
        }
        */
    }

    private function createLocationDetailsRequest(DataMiddlewareInterface $dataMiddleware, $locationDetails, $storisLocationId)
    {
        $metafieldId = null;

        if ($this->getReportMetafieldStore() instanceof DoctrineAwareInterface
            && $dataMiddleware->getObjectManager()
        ) {
            $this->getReportMetafieldStore()->setObjectManager($dataMiddleware->getObjectManager());
            $this->getReportMetafieldStore()->setDoctrine($dataMiddleware->getDoctrine());
        }

        if ($this->getReportMetafieldStore()) {
            $metafieldId = $this->getReportMetafieldStore()->getReportMetafieldId('location_details', $storisLocationId, null, ['allowLocationIdFromMetafieldKey' => true]);
        }

        if ($metafieldId) {
            // edit metafield
            $request['id'] = $metafieldId; // Metafield ID from DataSource (Shopify)
            $request['namespace'] = 'location_details';
            $request['key'] = (string) $storisLocationId; // Location ID from DataDestination (Storis)
            $request['value'] = json_encode($locationDetails);
            $request['type'] = 'json_string';
        } else {
            // create metafield
            $request['namespace'] = 'location_details';
            $request['key'] = (string) $storisLocationId; // Location ID from DataDestination (Storis)
            $request['value'] = json_encode($locationDetails);
            $request['type'] = 'json_string';
        }

        return $request;
    }

    private function updateLocationDetailsMetafield(DataMiddlewareInterface $dataMiddleware, $params = [], $storisLocationId = null)
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

    private function deleteLocationDetailsMetafield(DataMiddlewareInterface $dataMiddleware, $params = [], $storisLocationId = null)
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

        // force the delete request
        $params['type'] = 'boolean';
        $params['value'] = false;

        $logContext = [];

        $response = $dataMiddleware->updateReportMetafieldWithDetails($params, [
            'options' => [
                'isReportMetafieldDeleteRequestAllowed' => true,
                'inventoryLocationIndex' => $storisLocationId,
            ],
        ], null, $logContext);

        $dataMiddleware->storeReportMetafieldWithDetails($response, $params, [
            'options' => [
                'isReportMetafieldDeleteRequestAllowed' => true,
                'inventoryLocationIndex' => $storisLocationId,
            ],
        ], null, $logContext);

        return $response;
    }

    private function isDeleteShopMetafieldRequest(DataMiddlewareInterface $dataMiddleware, $params = [], $data = [], $storisLocationId = null)
    {
        if (isset($data['middleware_delete_shop_metafield']) && $data['middleware_delete_shop_metafield']) {
            return true;
        }

        if (!$dataMiddleware->getDataSource()->getDataSourceApiConnector() instanceof WithMetafieldsInterface) {
            return null;
        }

        return $dataMiddleware->getDataSource()->getDataSourceApiConnector()->isDeleteMetafieldRequest($params, array_merge($data, [
            'inventoryLocationIndex' => $storisLocationId,
        ]));
    }

    private function isAllowedLocationId($locationId)
    {
        return static::checkIsAllowedLocationId($locationId);
    }

    private function isNonPhysicalLocation($locationDetails = [], $storisLocationId = null, $dataDestinationLocations = [])
    {
        return static::checkIsNonPhysicalLocation($locationDetails, $storisLocationId, $dataDestinationLocations);
    }

    private function isTestLocation($locationDetails = [], $storisLocationId = null, $dataDestinationLocations = [])
    {
        return static::checkIsTestLocation($locationDetails, $storisLocationId, $dataDestinationLocations);
    }

    private function addStateLocation(&$stateLocations, $locationDetails, $result, $address, $locationId, $locationName, $locationAddress, $dataDestinationLocations, &$logContext = null)
    {
        if (!$this->isAllowedLocationId($locationId) || $this->isNonPhysicalLocation($locationDetails, $locationId, $dataDestinationLocations) || $this->isTestLocation($locationDetails, $locationId, $dataDestinationLocations)) {
            return;
        }

        if (isset($locationAddress['state']) && $locationAddress['state']) {
            $stateName = $this->getStateName($locationAddress['state']);
            $stateSlug = $this->getSlug($stateName);

            $stateLocations[$stateSlug]['name'] = $stateName;
            $stateLocations[$stateSlug]['slug'] = $stateSlug;
        }
    }

    private function addCityStateLocation(&$cityStateLocations, $locationDetails, $result, $address, $locationId, $locationName, $locationAddress, $dataDestinationLocations, &$logContext = null)
    {
        if (!$this->isAllowedLocationId($locationId) || $this->isNonPhysicalLocation($locationDetails, $locationId, $dataDestinationLocations) || $this->isTestLocation($locationDetails, $locationId, $dataDestinationLocations)) {
            return;
        }

        if (isset($locationAddress['state']) && $locationAddress['state']) {
            $stateName = $this->getStateName($locationAddress['state']);
            $stateSlug = $this->getSlug($stateName);

            if (isset($locationAddress['city']) && $locationAddress['city']) {
                $cityName = $this->getCityName($locationAddress['city']);
                $cityStateSlug = $this->getCityStateSlug($cityName, $stateName);

                $cityStateLocations[$stateSlug][$cityStateSlug]['name'] = $cityName;
                $cityStateLocations[$stateSlug][$cityStateSlug]['slug'] = $cityStateSlug;
                $cityStateLocations[$stateSlug][$cityStateSlug]['locations'][''.$locationId]['id'] = ''.$locationId;
                $cityStateLocations[$stateSlug][$cityStateSlug]['locations'][''.$locationId]['name'] = $locationName;
                $cityStateLocations[$stateSlug][$cityStateSlug]['locations'][''.$locationId]['slug'] = $this->getSlug($locationName);
            }
        }
    }

    private function getSlug($string)
    {
        $slug = '';
        $separator = '_';

        if ($string) {
            try {
                $slug = (string) $this->slugger->slug((string) $string, $separator);
                $slug = strtolower($slug);
            } catch (\Exception $e) {
                // do nothing
            }
        }

        return $slug;
    }

    private function getStateName($state)
    {
        $states = [
            'Alabama' => 'AL',
            'Alaska' => 'AK',
            'Arizona' => 'AZ',
            'Arkansas' => 'AR',
            'California' => 'CA',
            'Colorado' => 'CO',
            'Connecticut' => 'CT',
            'Delaware' => 'DE',
            'Florida' => 'FL',
            'Georgia' => 'GA',
            'Hawaii' => 'HI',
            'Idaho' => 'ID',
            'Illinois' => 'IL',
            'Indiana' => 'IN',
            'Iowa' => 'IA',
            'Kansas' => 'KS',
            'Kentucky' => 'KY',
            'Louisiana' => 'LA',
            'Maine' => 'ME',
            'Maryland' => 'MD',
            'Massachusetts' => 'MA',
            'Michigan' => 'MI',
            'Minnesota' => 'MN',
            'Mississippi' => 'MS',
            'Missouri' => 'MO',
            'Montana' => 'MT',
            'Nebraska' => 'NE',
            'Nevada' => 'NV',
            'New Hampshire' => 'NH',
            'New Jersey' => 'NJ',
            'New Mexico' => 'NM',
            'New York' => 'NY',
            'North Carolina' => 'NC',
            'North Dakota' => 'ND',
            'Ohio' => 'OH',
            'Oklahoma' => 'OK',
            'Oregon' => 'OR',
            'Pennsylvania' => 'PA',
            'Rhode Island' => 'RI',
            'South Carolina' => 'SC',
            'South Dakota' => 'SD',
            'Tennessee' => 'TN',
            'Texas' => 'TX',
            'Utah' => 'UT',
            'Vermont' => 'VT',
            'Virginia' => 'VA',
            'Washington' => 'WA',
            'West Virginia' => 'WV',
            'Wisconsin' => 'WI',
            'Wyoming' => 'WY',
            'District of Columbia' => 'DC',
        ];
        $stateCodes = array_flip($states);

        $upperState = strtoupper($state);
        if (isset($stateCodes[$upperState])) {
            return $stateCodes[$upperState];
        }

        return $state;
    }

    private function getCityName($city)
    {
        $upperCity = strtoupper($city);
        if ($upperCity === $city) {
            $lowerCity = strtolower($city);

            return ucwords($lowerCity);
        }

        return $city;
    }

    private function getCityStateSlug($city, $state)
    {
        return $this->getSlug($city).'-'.$this->getSlug($state);
    }

    private function createLocationsStatesRequest(DataMiddlewareInterface $dataMiddleware, $stateLocations)
    {
        ksort($stateLocations);

        $locationDetails = [];
        $locationDetails['states'] = $stateLocations;

        // create metafield
        $request['namespace'] = 'locations';
        $request['key'] = 'states';
        $request['value'] = json_encode($locationDetails);
        $request['type'] = 'json_string';

        return $request;
    }

    private function updateLocationsStatesMetafield(DataMiddlewareInterface $dataMiddleware, $params = [])
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

        $response = $dataMiddleware->updateReportMetafieldWithDetails($params, [], null, $logContext);

        $dataMiddleware->storeReportMetafieldWithDetails($response, $params, [], null, $logContext);

        return $response;
    }

    private function createLocationsStateCitiesRequest(DataMiddlewareInterface $dataMiddleware, $cityStateLocations, $stateSlug, $stateLocations)
    {
        ksort($cityStateLocations);

        $locationDetails = $stateLocations[$stateSlug] ?? [];
        $locationDetails['cities'] = $cityStateLocations;

        // create metafield
        $request['namespace'] = 'locations_state_cities';
        $request['key'] = $stateSlug;
        $request['value'] = json_encode($locationDetails);
        $request['type'] = 'json_string';

        return $request;
    }

    private function updateLocationsStateCitiesMetafield(DataMiddlewareInterface $dataMiddleware, $params = [])
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

        $response = $dataMiddleware->updateReportMetafieldWithDetails($params, [], null, $logContext);

        $dataMiddleware->storeReportMetafieldWithDetails($response, $params, [], null, $logContext);

        return $response;
    }

    private function requestGraphQLQuery(DataMiddlewareInterface $dataMiddleware, $query, $variables)
    {
        if (!$dataMiddleware->getDataSource()->getDataSourceApiConnector() instanceof WithSendRequestInterface) {
            return null;
        }

        return $dataMiddleware->getDataSource()->getDataSourceApiConnector()->sendRequest('POST', '/graphql', [], [
            'query' => $query,
            'variables' => $variables,
        ]);
    }

    private function getMetafieldStorefrontVisibilityCreateMutation()
    {
        return 'mutation metafieldStorefrontVisibilityCreate($input: MetafieldStorefrontVisibilityInput!) {
          metafieldStorefrontVisibilityCreate(input: $input) {
            metafieldStorefrontVisibility {
              id
              key
              namespace
              ownerType
            }
            userErrors {
              field
              message
            }
          }
        }';
    }

    private function getTypesForMetafieldStorefrontVisibilityCreate()
    {
        return [
            // metafield namespace => metafield type
            'available_to_try' => 'boolean',  // metafield key is Location ID from DataDestination (Storis)
            'inventory' => 'number_integer',  // metafield key is Location ID from DataDestination (Storis)
            'retail_warehouse' => 'number_integer',  // metafield key is Location ID from DataDestination (Storis)
            'location_details' => 'json',  // metafield key is Location ID from DataDestination (Storis)
            'locations' => 'json',  // metafield key is "states"
            'locations_state_cities' => 'json',  // metafield key is the state slug
        ];
    }

    private function getAllowedNamespacesForMetafieldStorefrontVisibilityCreate()
    {
        return [
            // metafield namespace => metafield ownerType
            'available_to_try' => 'PRODUCTVARIANT',  // metafield key is Location ID from DataDestination (Storis)
            'inventory' => 'PRODUCTVARIANT',  // metafield key is Location ID from DataDestination (Storis)
            'retail_warehouse' => 'SHOP',  // metafield key is Location ID from DataDestination (Storis)
            'location_details' => 'SHOP',  // metafield key is Location ID from DataDestination (Storis)
            'locations' => 'SHOP',  // metafield key is "states"
            'locations_state_cities' => 'SHOP',  // metafield key is the state slug
        ];
    }

    private function createMetafieldStorefrontVisibilityCreateRequest($metafieldNamespace, $metafieldKey, $metafieldOwnerType)
    {
        $request['input']['namespace'] = $metafieldNamespace;
        $request['input']['key'] = $metafieldKey;
        $request['input']['ownerType'] = $metafieldOwnerType; // SHOP, PRODUCT, PRODUCTVARIANT

        return $request;
    }

    private function generateMetafieldStorefrontVisibilityCreateRequests($dataDestinationLocations, $stateLocations)
    {
        $requests = [];

        foreach ($this->getAllowedNamespacesForMetafieldStorefrontVisibilityCreate() as $metafieldNamespace => $metafieldOwnerType) {
            $metafieldKeys = [];

            if ('locations' === $metafieldNamespace) {
                $metafieldKeys[] = 'states';
            } elseif ('locations_state_cities' === $metafieldNamespace) {
                $metafieldKeys = array_keys($stateLocations);
            } else {
                foreach ($dataDestinationLocations as $location) {
                    $locationId = $location['id'] ?? null;
                    if ($locationId) {
                        $metafieldKeys[] = ''.$locationId;
                    }
                }
            }

            foreach ($metafieldKeys as $metafieldKey) {
                $requests[$metafieldNamespace.'.'.$metafieldKey] = $this->createMetafieldStorefrontVisibilityCreateRequest($metafieldNamespace, $metafieldKey, $metafieldOwnerType);
            }
        }

        return $requests;
    }

    private function getMetafieldDefinitionCreateWithStorefrontVisibilityMutation()
    {
        return 'mutation metafieldDefinitionCreate($input: MetafieldDefinitionInput!) {
          metafieldDefinitionCreate(definition: $input) {
            createdDefinition {
              id
              key
              namespace
              ownerType
              access {
                admin
                customerAccount
                storefront
              }
            }
            userErrors {
              field
              message
            }
          }
        }';
    }

    private function createMetafieldDefinitionCreateWithStorefrontVisibilityRequest($metafieldNamespace, $metafieldKey, $metafieldOwnerType)
    {
        $request['input']['namespace'] = $metafieldNamespace;
        $request['input']['key'] = $metafieldKey;
        $request['input']['ownerType'] = $metafieldOwnerType; // SHOP, PRODUCT, PRODUCTVARIANT
        $request['input']['name'] = $metafieldNamespace.' '.$metafieldKey;
        $request['input']['type'] = $this->getTypesForMetafieldStorefrontVisibilityCreate()[$metafieldNamespace] ?? null;

        return $request;
    }

    private function generateMetafieldDefinitionCreateWithStorefrontVisibilityRequests($dataDestinationLocations, $stateLocations)
    {
        $requests = [];

        foreach ($this->getAllowedNamespacesForMetafieldStorefrontVisibilityCreate() as $metafieldNamespace => $metafieldOwnerType) {
            // only allow the SHOP type of metafield definition to be created for now
            // since they do not usually have a way to be created in the Shopify admin site
            if ('SHOP' !== $metafieldOwnerType) {
                continue;
            }

            $metafieldKeys = [];

            if ('locations' === $metafieldNamespace) {
                $metafieldKeys[] = 'states';
            } elseif ('locations_state_cities' === $metafieldNamespace) {
                $metafieldKeys = array_keys($stateLocations);
            } else {
                foreach ($dataDestinationLocations as $location) {
                    $locationId = $location['id'] ?? null;
                    if ($locationId) {
                        $metafieldKeys[] = ''.$locationId;
                    }
                }
            }

            foreach ($metafieldKeys as $metafieldKey) {
                $requests[$metafieldNamespace.'.'.$metafieldKey] = $this->createMetafieldDefinitionCreateWithStorefrontVisibilityRequest($metafieldNamespace, $metafieldKey, $metafieldOwnerType);
            }
        }

        return $requests;
    }

    private function getMetafieldDefinitionUpdateWithStorefrontVisibilityMutation()
    {
        return 'mutation metafieldDefinitionUpdate($input: MetafieldDefinitionUpdateInput!) {
          metafieldDefinitionUpdate(definition: $input) {
            updatedDefinition {
              id
              key
              namespace
              ownerType
              access {
                admin
                customerAccount
                storefront
              }
            }
            userErrors {
              field
              message
            }
          }
        }';
    }

    private function createMetafieldDefinitionUpdateWithStorefrontVisibilityRequest($metafieldNamespace, $metafieldKey, $metafieldOwnerType)
    {
        $request['input']['namespace'] = $metafieldNamespace;
        $request['input']['key'] = $metafieldKey;
        $request['input']['ownerType'] = $metafieldOwnerType; // SHOP, PRODUCT, PRODUCTVARIANT
        $request['input']['access']['storefront'] = 'PUBLIC_READ';

        return $request;
    }

    private function generateMetafieldDefinitionUpdateWithStorefrontVisibilityRequests($dataDestinationLocations, $stateLocations)
    {
        $requests = [];

        foreach ($this->getAllowedNamespacesForMetafieldStorefrontVisibilityCreate() as $metafieldNamespace => $metafieldOwnerType) {
            $metafieldKeys = [];

            if ('locations' === $metafieldNamespace) {
                $metafieldKeys[] = 'states';
            } elseif ('locations_state_cities' === $metafieldNamespace) {
                $metafieldKeys = array_keys($stateLocations);
            } else {
                foreach ($dataDestinationLocations as $location) {
                    $locationId = $location['id'] ?? null;
                    if ($locationId) {
                        $metafieldKeys[] = ''.$locationId;
                    }
                }
            }

            foreach ($metafieldKeys as $metafieldKey) {
                $requests[$metafieldNamespace.'.'.$metafieldKey] = $this->createMetafieldDefinitionUpdateWithStorefrontVisibilityRequest($metafieldNamespace, $metafieldKey, $metafieldOwnerType);
            }
        }

        return $requests;
    }
}
