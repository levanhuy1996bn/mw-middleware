<?php

namespace App\Tests\Command;

use App\EcommerceMiddleware\Driver\Shopify\Data\Product\AppProductShopifySource;
use App\EcommerceMiddleware\Driver\Shopify\Data\Variant\AppVariantShopifySource;
use App\EcommerceMiddleware\ShopifyStoris\Data\Order\AppOrderMiddleware;
use App\EcommerceMiddleware\ShopifyStoris\Data\Product\AppProductMiddleware;
use App\EcommerceMiddleware\ShopifyStoris\Data\Variant\AppVariantMiddleware;
use App\EcommerceMiddleware\ShopifyStoris\Task\DestinationToSource\LocationDetailsUpdate;
use App\Location\YextLocation;
use Endertech\EcommerceMiddleware\Contracts\Model\ConfigurationInterface;
use Endertech\EcommerceMiddleware\Core\Decorator\DecoratorInspector;
use Endertech\EcommerceMiddleware\Core\Logger\MultipleLoggerDecorator;
use Endertech\EcommerceMiddleware\Core\Model\Configuration;
use Endertech\EcommerceMiddleware\Core\Task\BaseTask;
use Endertech\EcommerceMiddleware\Driver\Shopify\Connector\ShopifyClient;
use Endertech\EcommerceMiddleware\Driver\Storis\Connector\StorisClient;
use Endertech\EcommerceMiddleware\ShopifyStoris\Tests\Task\ShopifyStorisTaskTesterTrait;
use Endertech\EcommerceMiddlewareReport\Contracts\Model\ReportMetafieldInterface;
use Endertech\EcommerceMiddlewareReport\Contracts\Model\ReportOrderMetafieldInterface;
use Endertech\EcommerceMiddlewareReport\Contracts\Model\ReportProductMetafieldInterface;
use Endertech\EcommerceMiddlewareReport\Contracts\Model\ReportVariantMetafieldInterface;
use Endertech\EcommerceMiddlewareReportMetafieldBundle\Repository\ReportMetafieldRepository;
use Endertech\EcommerceMiddlewareReportMetafieldBundle\Repository\ReportOrderMetafieldRepository;
use Endertech\EcommerceMiddlewareReportMetafieldBundle\Repository\ReportProductMetafieldRepository;
use Endertech\EcommerceMiddlewareReportMetafieldBundle\Repository\ReportVariantMetafieldRepository;
use Geocoder\Provider\GoogleMaps\GoogleMaps;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class MiddlewareDestinationToSourceLocationDetailsUpdateCommandTest extends KernelTestCase
{
    use ShopifyStorisTaskTesterTrait {
        callbackFindBy as localtraitCallbackFindBy;
        callbackFindOneBy as localtraitCallbackFindOneBy;
        configureTask as localtraitConfigureTask;
        getRepositoryMap as localtraitRepositoryMap;
    }

    public function testExecuteWithShopifyGraphQLStorisV10UpdatesLocationDetailsMetafieldsAndRetailWarehouseMetafields($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        $storisLocations = $this->getResponseJsonStorisLocations($dataDestinationApiVersion);
        $storisLocations['data']['locations'][0]['id'] = 5198;
        $storisLocations['data']['locations'][0]['locationAddress']['address1'] = '321 Fake St.';
        $storisLocations['data']['locations'][0]['locationAddress']['city'] = 'Beverly Hills';
        $storisLocations['data']['locations'][0]['locationAddress']['state'] = 'CA';
        $storisLocations['data']['locations'][0]['regionCode'] = '10';
        $storisLocations['data']['locations'][1]['id'] = 5564;
        $storisLocations['data']['locations'][1]['locationAddress']['address1'] = '654 Fake Ave.';
        $storisLocations['data']['locations'][1]['locationAddress']['city'] = 'Orlando';
        $storisLocations['data']['locations'][1]['locationAddress']['state'] = 'FL';
        $storisLocations['data']['locations'][1]['regionCode'] = '20';

        $this->taskClass = LocationDetailsUpdate::class;

        $kernel = static::bootKernel();

        static::getContainer()->set('http_client.uri_template', $this->getHttpClient([
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
            'api/Locations/Changes' => $storisLocations,
            sprintf(GoogleMaps::GEOCODE_ENDPOINT_URL_SSL, '321%20Fake%20St.') => [
                'status' => 'OK',
                'results' => [
                    0 => [
                        'address_components' => [],
                        'formatted_address' => '321 Fake Street',
                        'geometry' => [
                            'location_type' => '',
                            'location' => [
                                'lat' => 1.1,
                                'lng' => -1.1,
                            ],
                        ],
                    ],
                ],
            ],
            sprintf(GoogleMaps::GEOCODE_ENDPOINT_URL_SSL, '654%20Fake%20Ave.') => [
                'status' => 'OK',
                'results' => [
                    0 => [
                        'address_components' => [],
                        'formatted_address' => '654 Fake Avenue',
                        'geometry' => [
                            'location_type' => '',
                            'location' => [
                                'lat' => 1.2,
                                'lng' => -1.2,
                            ],
                        ],
                    ],
                ],
            ],
            sprintf(YextLocation::API_LOCATIONS_URL_FORMAT, '', 5198) => [
                'response' => [
                    'docs' => [
                        0 => [
                            'googlePlaceId' => 'GPID-321',
                            'hours' => [
                                'holidayHours' => [
                                    0 => [
                                        'date' => '2020-07-11',
                                        'isClosed' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            sprintf(YextLocation::API_LOCATIONS_URL_FORMAT, '', 5564) => [
                'response' => [
                    'docs' => [
                        0 => [
                            'googlePlaceId' => 'GPID-654',
                            'hours' => [
                                'holidayHours' => [
                                    0 => [
                                        'date' => '2020-07-12',
                                        'isClosed' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]));

        $application = new Application($kernel);

        $command = $application->find('middleware:destination-to-source:location-details-update');

        $configuredTask = $this->configureTask($command->getTask(), [
            ConfigurationInterface::class => [
                0 => $this->createConfiguration(331, 'sleep-outfitters-update-location-details')->setValue('1'),
                1 => $this->createConfiguration(332, 'sleep-outfitters-update-retail-warehouse-locations')->setValue('1'),
            ],
        ], [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForLocations()[0]) => ['data' => $this->getResponseJsonShopifyLocations($dataSourceApiVersion)],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShop(null, ['fields' => 'id'])[0]) => ['data' => ['shop' => ['id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop')]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForShopMetafieldCreate($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), [])[0]) => ['data' => ['metafieldsSet' => ['metafields' => [0 => $this->getResponseJsonShopifyMetafield('111222333444', $dataSourceApiVersion)]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '5198'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '5564'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations', 'key' => 'states'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'california'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'florida'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '5198'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '5564'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
            'api/Locations/Changes' => $storisLocations,
        ], null, null, null, null, null, null, [
            'storis_username' => 'johndoe',
            'storis_secret' => 's3cr3t',
            'api_version' => $dataDestinationApiVersion,
            'data_source_api_version' => $dataSourceApiVersion,
            'data_destination_api_version' => $dataDestinationApiVersion,
        ]);
        $command->setTask($configuredTask);
        $command->setDecoratedLogger(new MultipleLoggerDecorator(null, $this->getLogger()));

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        //$this->assertStringContainsString('1/1', $output);
        //$this->assertStringContainsString('100%', $output);
        $this->assertEquals('', $output);

        $this->assertTrue($this->getLogger()->hasInfo('START {commandName} PROCESS'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForLocations()[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Retrieved Shopify Locations'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/authenticate'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Locations/Changes'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShop(null, ['fields' => 'id'])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '5198'])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForShopMetafieldCreate($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), [])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '5564'])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForShopMetafieldCreate($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), [])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations', 'key' => 'states'])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForShopMetafieldCreate($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), [])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'california'])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForShopMetafieldCreate($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), [])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'florida'])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForShopMetafieldCreate($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), [])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Updated Location Details, Updated: {locationDetailsUpdatedCount}, Deleted: {locationDetailsDeletedCount}, Error: {locationDetailsErrorCount}, Total: {locationDetailsTotalCount}'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '5198'])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForShopMetafieldCreate($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), [])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '5564'])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForShopMetafieldCreate($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), [])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Updated Retail Warehouses, Updated: {retailWarehouseUpdatedCount}, Error: {retailWarehouseErrorCount}, Total: {retailWarehouseTotalCount}'));
        $this->assertTrue($this->getLogger()->hasInfo('Retrieved Storis Locations'));
        $this->assertTrue($this->getLogger()->hasInfo('{resultCount} results'));
        $this->assertTrue($this->getLogger()->hasInfo('No results'));
        $this->assertTrue($this->getLogger()->hasInfo('END {commandName} PROCESS'));

        $this->assertEquals('START {commandName} PROCESS', $this->getLogger()->records[0]['message']);
        $this->assertEquals('middleware:destination-to-source:location-details-update', $this->getLogger()->records[0]['context']['commandName']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForLocations()[0]), $this->getLogger()->records[1]['message']);
        $this->assertSame([
            'perPage' => 50,
            'endCursor' => null,
        ], $this->getLogger()->records[1]['context']['request']['variables']);
        $this->assertSame([
            'data' => $this->getResponseJsonShopifyLocations($dataSourceApiVersion),
        ], $this->getLogger()->records[1]['context']['response']);

        $this->assertEquals('Retrieved Shopify Locations', $this->getLogger()->records[2]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/authenticate', $this->getLogger()->records[3]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Locations/Changes', $this->getLogger()->records[4]['message']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShop(null, ['fields' => 'id'])[0]), $this->getLogger()->records[5]['message']);
        $this->assertSame([
            'endCursor' => null,
        ], $this->getLogger()->records[5]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'shop' => [
                    'id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'),
                ],
            ],
        ], $this->getLogger()->records[5]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '5198'])[0]), $this->getLogger()->records[6]['message']);
        $this->assertSame([
            'perPage' => 50,
            'endCursor' => null,
            'id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'),
        ], $this->getLogger()->records[6]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'shop' => [
                    'metafields' => [
                        'nodes' => [],
                    ],
                ],
            ],
        ], $this->getLogger()->records[6]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForShopMetafieldCreate($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), [])[0]), $this->getLogger()->records[7]['message']);
        $this->assertSame([
            'metafields' => [
                0 => [
                    'ownerId' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'),
                    'namespace' => 'location_details',
                    'key' => '5198',
                    'value' => json_encode([
                        'lat' => 1.1,
                        'long' => -1.1,
                        'name' => 'TestLoc321',
                        'address' => '321 Fake Street',
                        'phone' => '',
                        'transferRoutes' => [],
                        'googlePlaceId' => 'GPID-321',
                        'hours' => [
                            'holidayHours' => [
                                0 => [
                                    'date' => '2020-07-11',
                                    'isClosed' => true,
                                ],
                            ],
                        ],
                    ]),
                    'type' => 'json_string',
                ],
            ],
        ], $this->getLogger()->records[7]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'metafieldsSet' => [
                    'metafields' => [
                        0 => [
                            'id' => $this->generateShopifyGraphQLIdentifier('111222333444', 'Metafield'),
                            'value' => 42,
                        ],
                    ],
                ],
            ],
        ], $this->getLogger()->records[7]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '5564'])[0]), $this->getLogger()->records[8]['message']);
        $this->assertSame([
            'perPage' => 50,
            'endCursor' => null,
            'id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'),
        ], $this->getLogger()->records[8]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'shop' => [
                    'metafields' => [
                        'nodes' => [],
                    ],
                ],
            ],
        ], $this->getLogger()->records[8]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForShopMetafieldCreate($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), [])[0]), $this->getLogger()->records[9]['message']);
        $this->assertSame([
            'metafields' => [
                0 => [
                    'ownerId' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'),
                    'namespace' => 'location_details',
                    'key' => '5564',
                    'value' => json_encode([
                        'lat' => 1.2,
                        'long' => -1.2,
                        'name' => 'TestLoc654',
                        'address' => '654 Fake Avenue',
                        'phone' => '',
                        'transferRoutes' => [],
                        'googlePlaceId' => 'GPID-654',
                        'hours' => [
                            'holidayHours' => [
                                0 => [
                                    'date' => '2020-07-12',
                                    'isClosed' => true,
                                ],
                            ],
                        ],
                    ]),
                    'type' => 'json_string',
                ],
            ],
        ], $this->getLogger()->records[9]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'metafieldsSet' => [
                    'metafields' => [
                        0 => [
                            'id' => $this->generateShopifyGraphQLIdentifier('111222333444', 'Metafield'),
                            'value' => 42,
                        ],
                    ],
                ],
            ],
        ], $this->getLogger()->records[9]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations', 'key' => 'states'])[0]), $this->getLogger()->records[10]['message']);
        $this->assertSame([
            'perPage' => 50,
            'endCursor' => null,
            'id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'),
        ], $this->getLogger()->records[10]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'shop' => [
                    'metafields' => [
                        'nodes' => [],
                    ],
                ],
            ],
        ], $this->getLogger()->records[10]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForShopMetafieldCreate($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), [])[0]), $this->getLogger()->records[11]['message']);
        $this->assertSame([
            'metafields' => [
                0 => [
                    'ownerId' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'),
                    'namespace' => 'locations',
                    'key' => 'states',
                    'value' => json_encode([
                        'states' => [
                            'california' => [
                                'name' => 'California',
                                'slug' => 'california',
                            ],
                            'florida' => [
                                'name' => 'Florida',
                                'slug' => 'florida',
                            ],
                        ],
                    ]),
                    'type' => 'json_string',
                ],
            ],
        ], $this->getLogger()->records[11]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'metafieldsSet' => [
                    'metafields' => [
                        0 => [
                            'id' => $this->generateShopifyGraphQLIdentifier('111222333444', 'Metafield'),
                            'value' => 42,
                        ],
                    ],
                ],
            ],
        ], $this->getLogger()->records[11]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'california'])[0]), $this->getLogger()->records[12]['message']);
        $this->assertSame([
            'perPage' => 50,
            'endCursor' => null,
            'id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'),
        ], $this->getLogger()->records[12]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'shop' => [
                    'metafields' => [
                        'nodes' => [],
                    ],
                ],
            ],
        ], $this->getLogger()->records[12]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForShopMetafieldCreate($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), [])[0]), $this->getLogger()->records[13]['message']);
        $this->assertSame([
            'metafields' => [
                0 => [
                    'ownerId' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'),
                    'namespace' => 'locations_state_cities',
                    'key' => 'california',
                    'value' => json_encode([
                        'name' => 'California',
                        'slug' => 'california',
                        'cities' => [
                            'beverly_hills-california' => [
                                'name' => 'Beverly Hills',
                                'slug' => 'beverly_hills-california',
                                'locations' => [
                                    '5198' => [
                                        'id' => '5198',
                                        'name' => 'TestLoc321',
                                        'slug' => 'testloc321',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                    'type' => 'json_string',
                ],
            ],
        ], $this->getLogger()->records[13]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'metafieldsSet' => [
                    'metafields' => [
                        0 => [
                            'id' => $this->generateShopifyGraphQLIdentifier('111222333444', 'Metafield'),
                            'value' => 42,
                        ],
                    ],
                ],
            ],
        ], $this->getLogger()->records[13]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'florida'])[0]), $this->getLogger()->records[14]['message']);
        $this->assertSame([
            'perPage' => 50,
            'endCursor' => null,
            'id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'),
        ], $this->getLogger()->records[14]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'shop' => [
                    'metafields' => [
                        'nodes' => [],
                    ],
                ],
            ],
        ], $this->getLogger()->records[14]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForShopMetafieldCreate($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), [])[0]), $this->getLogger()->records[15]['message']);
        $this->assertSame([
            'metafields' => [
                0 => [
                    'ownerId' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'),
                    'namespace' => 'locations_state_cities',
                    'key' => 'florida',
                    'value' => json_encode([
                        'name' => 'Florida',
                        'slug' => 'florida',
                        'cities' => [
                            'orlando-florida' => [
                                'name' => 'Orlando',
                                'slug' => 'orlando-florida',
                                'locations' => [
                                    '5564' => [
                                        'id' => '5564',
                                        'name' => 'TestLoc654',
                                        'slug' => 'testloc654',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                    'type' => 'json_string',
                ],
            ],
        ], $this->getLogger()->records[15]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'metafieldsSet' => [
                    'metafields' => [
                        0 => [
                            'id' => $this->generateShopifyGraphQLIdentifier('111222333444', 'Metafield'),
                            'value' => 42,
                        ],
                    ],
                ],
            ],
        ], $this->getLogger()->records[15]['context']['response']);

        $this->assertEquals('Updated Location Details, Updated: {locationDetailsUpdatedCount}, Deleted: {locationDetailsDeletedCount}, Error: {locationDetailsErrorCount}, Total: {locationDetailsTotalCount}', $this->getLogger()->records[16]['message']);
        $this->assertEquals(2, $this->getLogger()->records[16]['context']['locationDetailsUpdatedCount']);
        $this->assertEquals(0, $this->getLogger()->records[16]['context']['locationDetailsDeletedCount']);
        $this->assertEquals(0, $this->getLogger()->records[16]['context']['locationDetailsErrorCount']);
        $this->assertEquals(2, $this->getLogger()->records[16]['context']['locationDetailsTotalCount']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '5198'])[0]), $this->getLogger()->records[17]['message']);
        $this->assertSame([
            'perPage' => 50,
            'endCursor' => null,
            'id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'),
        ], $this->getLogger()->records[17]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'shop' => [
                    'metafields' => [
                        'nodes' => [],
                    ],
                ],
            ],
        ], $this->getLogger()->records[17]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForShopMetafieldCreate($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), [])[0]), $this->getLogger()->records[18]['message']);
        $this->assertSame([
            'metafields' => [
                0 => [
                    'ownerId' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'),
                    'namespace' => 'retail_warehouse',
                    'key' => '5198',
                    'value' => '9610',
                    'type' => 'integer',
                ],
            ],
        ], $this->getLogger()->records[18]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'metafieldsSet' => [
                    'metafields' => [
                        0 => [
                            'id' => $this->generateShopifyGraphQLIdentifier('111222333444', 'Metafield'),
                            'value' => 42,
                        ],
                    ],
                ],
            ],
        ], $this->getLogger()->records[18]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '5564'])[0]), $this->getLogger()->records[19]['message']);
        $this->assertSame([
            'perPage' => 50,
            'endCursor' => null,
            'id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'),
        ], $this->getLogger()->records[19]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'shop' => [
                    'metafields' => [
                        'nodes' => [],
                    ],
                ],
            ],
        ], $this->getLogger()->records[19]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForShopMetafieldCreate($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), [])[0]), $this->getLogger()->records[20]['message']);
        $this->assertSame([
            'metafields' => [
                0 => [
                    'ownerId' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'),
                    'namespace' => 'retail_warehouse',
                    'key' => '5564',
                    'value' => '9620',
                    'type' => 'integer',
                ],
            ],
        ], $this->getLogger()->records[20]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'metafieldsSet' => [
                    'metafields' => [
                        0 => [
                            'id' => $this->generateShopifyGraphQLIdentifier('111222333444', 'Metafield'),
                            'value' => 42,
                        ],
                    ],
                ],
            ],
        ], $this->getLogger()->records[20]['context']['response']);

        $this->assertEquals('Updated Retail Warehouses, Updated: {retailWarehouseUpdatedCount}, Error: {retailWarehouseErrorCount}, Total: {retailWarehouseTotalCount}', $this->getLogger()->records[21]['message']);
        $this->assertEquals(2, $this->getLogger()->records[21]['context']['retailWarehouseUpdatedCount']);
        $this->assertEquals(0, $this->getLogger()->records[21]['context']['retailWarehouseErrorCount']);
        $this->assertEquals(2, $this->getLogger()->records[21]['context']['retailWarehouseTotalCount']);

        $this->assertEquals('Retrieved Storis Locations', $this->getLogger()->records[22]['message']);

        $this->assertEquals('{resultCount} results', $this->getLogger()->records[23]['message']);
        $this->assertEquals(0, $this->getLogger()->records[23]['context']['resultCount']);

        $this->assertEquals('No results', $this->getLogger()->records[24]['message']);

        $this->assertEquals('END {commandName} PROCESS', $this->getLogger()->records[25]['message']);
        $this->assertEquals('middleware:destination-to-source:location-details-update', $this->getLogger()->records[25]['context']['commandName']);
    }

    public function callbackFindBy($criteria, $orderBy, $limit, $offset, $results, $objectName)
    {
        if (isset($criteria['slug'])) {
            $res = [];
            foreach ($results[$objectName] as $obj) {
                if (method_exists($obj, 'getSlug') && $obj->getSlug() == $criteria['slug']) {
                    $res[] = $obj;
                }
            }

            return $res;
        }

        return $this->localtraitCallbackFindBy($criteria, $orderBy, $limit, $offset, $results, $objectName);
    }

    public function callbackFindOneBy($criteria, $orderBy, $results, $objectName)
    {
        if (isset($criteria['slug'])) {
            foreach ($results[$objectName] as $obj) {
                if (method_exists($obj, 'getSlug') && $obj->getSlug() == $criteria['slug']) {
                    return $obj;
                }
            }

            return null;
        }

        return $this->localtraitCallbackFindOneBy($criteria, $orderBy, $results, $objectName);
    }

    protected function configureTask(BaseTask $task, $repositoryResults = null, $dataSourceApiResults = null, $dataDestinationApiResults = null, $dataSourceApiConnector = null, $dataDestinationApiConnector = null, $dataSourceHttpClient = null, $dataDestinationHttpClient = null, $objectManager = null, $doctrine = null, $options = [])
    {
        $options['fqcn']['order']['configured_middleware'] = AppOrderMiddleware::class;
        $options['fqcn']['product']['configured_middleware'] = AppProductMiddleware::class;
        $options['fqcn']['product']['data_source'] = AppProductShopifySource::class;
        $options['fqcn']['variant']['configured_middleware'] = AppVariantMiddleware::class;
        $options['fqcn']['variant']['data_source'] = AppVariantShopifySource::class;

        $configuredTask = $this->localtraitConfigureTask($task, $repositoryResults, $dataSourceApiResults, $dataDestinationApiResults, $dataSourceApiConnector, $dataDestinationApiConnector, $dataSourceHttpClient, $dataDestinationHttpClient, $objectManager, $doctrine, $options);

        if (DecoratorInspector::methodExists($configuredTask->getDataMiddleware(), 'setEventDispatcher')) {
            $eventDispatcher = self::getContainer()->get('event_dispatcher');
            $configuredTask->getDataMiddleware()->setEventDispatcher($eventDispatcher);
        }
        if (DecoratorInspector::methodExists($configuredTask->getDataMiddleware()->getDataSource(), 'setEventDispatcher')) {
            $eventDispatcher = self::getContainer()->get('event_dispatcher');
            $configuredTask->getDataMiddleware()->getDataSource()->setEventDispatcher($eventDispatcher);
        }
        if (DecoratorInspector::methodExists($configuredTask->getDataMiddleware()->getDataSource()->getDataSourceApiConnector(), 'setEventDispatcher')) {
            $eventDispatcher = self::getContainer()->get('event_dispatcher');
            $configuredTask->getDataMiddleware()->getDataSource()->getDataSourceApiConnector()->setEventDispatcher($eventDispatcher);
        }
        if (DecoratorInspector::methodExists($configuredTask->getDataMiddleware()->getDataDestination(), 'setEventDispatcher')) {
            $eventDispatcher = self::getContainer()->get('event_dispatcher');
            $configuredTask->getDataMiddleware()->getDataDestination()->setEventDispatcher($eventDispatcher);
        }
        if (DecoratorInspector::methodExists($configuredTask->getDataMiddleware()->getDataDestination()->getDataDestinationApiConnector(), 'setEventDispatcher')) {
            $eventDispatcher = self::getContainer()->get('event_dispatcher');
            $configuredTask->getDataMiddleware()->getDataDestination()->getDataDestinationApiConnector()->setEventDispatcher($eventDispatcher);
        }

        return $configuredTask;
    }

    protected function getRepositoryMap()
    {
        $map = $this->localtraitRepositoryMap();

        $map[ReportMetafieldInterface::class] = ReportMetafieldRepository::class;
        $map[ReportOrderMetafieldInterface::class] = ReportOrderMetafieldRepository::class;
        $map[ReportProductMetafieldInterface::class] = ReportProductMetafieldRepository::class;
        $map[ReportVariantMetafieldInterface::class] = ReportVariantMetafieldRepository::class;

        return $map;
    }

    protected function createConfiguration($id = null, $slug = null)
    {
        $configuration = new Configuration();
        $configuration->setValue('');
        $configuration->setName('test');
        $configuration->setSlug($slug ?? 'test');
        $configuration->setCreatedAt(new \DateTime('2020-07-22 12:34:56 UTC'));
        $configuration->setUpdatedAt(new \DateTime('2020-07-22 12:34:56 UTC'));

        if ($id) {
            $refl = new \ReflectionProperty($configuration, 'id');
            $refl->setAccessible(true);
            $refl->setValue($configuration, $id);
        }

        return $configuration;
    }
}
