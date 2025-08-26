<?php

namespace App\Tests\Controller;

use App\Controller\FrontendMetafieldApiController;
use App\EcommerceMiddleware\Driver\Shopify\Data\Variant\AppVariantShopifySource;
use App\EcommerceMiddleware\ShopifyStoris\Data\Order\AppOrderMiddleware;
use App\EcommerceMiddleware\ShopifyStoris\Data\Product\AppProductMiddleware;
use App\EcommerceMiddleware\ShopifyStoris\Data\Variant\AppVariantMiddleware;
use Endertech\EcommerceMiddleware\Contracts\Variant\DataMiddlewareInterface as VariantDataMiddlewareInterface;
use Endertech\EcommerceMiddleware\Core\Decorator\DecoratorInspector;
use Endertech\EcommerceMiddleware\Core\Task\BaseTask;
use Endertech\EcommerceMiddleware\Core\Task\DestinationToSource\VariantUpdate;
use Endertech\EcommerceMiddleware\Driver\Shopify\Connector\ShopifyClient;
use Endertech\EcommerceMiddleware\Driver\Storis\Connector\StorisClient;
use Endertech\EcommerceMiddleware\ShopifyStoris\Tests\Task\ShopifyStorisTaskTesterTrait;
use Endertech\EcommerceMiddlewareReport\Contracts\Model\ReportMetafieldInterface;
use Endertech\EcommerceMiddlewareReport\Contracts\Model\ReportOrderMetafieldInterface;
use Endertech\EcommerceMiddlewareReport\Contracts\Model\ReportProductMetafieldInterface;
use Endertech\EcommerceMiddlewareReport\Contracts\Model\ReportVariantMetafieldInterface;
use Endertech\EcommerceMiddlewareReport\Metafield\Model\ReportMetafield;
use Endertech\EcommerceMiddlewareReportMetafieldBundle\Repository\ReportMetafieldRepository;
use Endertech\EcommerceMiddlewareReportMetafieldBundle\Repository\ReportOrderMetafieldRepository;
use Endertech\EcommerceMiddlewareReportMetafieldBundle\Repository\ReportProductMetafieldRepository;
use Endertech\EcommerceMiddlewareReportMetafieldBundle\Repository\ReportVariantMetafieldRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FrontendMetafieldApiControllerTest extends WebTestCase
{
    use ShopifyStorisTaskTesterTrait {
        configureTask as localtraitConfigureTask;
        getRepositoryMap as localtraitRepositoryMap;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetServices();
    }

    public function testShopifyGraphQLStorisV10FrontendMetafieldGetListWithoutFilters($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        $client = static::createClient();

        $this->taskClass = VariantUpdate::class;
        $task = $this->getTask(null, [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShop(null, ['fields' => 'id'])[0]) => ['data' => ['shop' => ['id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop')]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '5321'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '9654'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations', 'key' => 'states'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'california'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'florida'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '5321'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '9654'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
        ], null, null, null, null, null, null, [
            'storis_username' => 'johndoe',
            'storis_secret' => 's3cr3t',
            'api_version' => $dataDestinationApiVersion,
            'data_source_api_version' => $dataSourceApiVersion,
            'data_destination_api_version' => $dataDestinationApiVersion,
        ]);
        $dataMiddleware = $task->getDataMiddleware();
        static::getContainer()->set(VariantDataMiddlewareInterface::class, $dataMiddleware);

        /* @var \Symfony\Bundle\FrameworkBundle\KernelBrowser $client */
        $client->disableReboot(); // preserve kernel state and the object manager registry changes

        $client->request('POST', '/api/metafields', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.FrontendMetafieldApiController::FRONTEND_METAFIELDS_AUTH_TOKEN,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'metafield' => [
                'namespace' => null,
                'key' => null,
                'ownerId' => null,
            ],
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('metafields', $client->getResponse()->getContent());
        $this->assertSame([
            'metafields' => [],
        ], json_decode($client->getResponse()->getContent(), true));
    }

    public function testShopifyGraphQLStorisV10FrontendMetafieldGetListWithFilterNamespace($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        $client = static::createClient();

        $shopifyMetafieldsResponse = [
            0 => [
                'id' => $this->generateShopifyGraphQLIdentifier('1112223334445556661', 'Metafield'),
                'value' => 42,
            ],
            1 => [
                'id' => $this->generateShopifyGraphQLIdentifier('1112223334445556662', 'Metafield'),
                'value' => 43,
            ],
        ];
        $shopifyMetafieldsResponseFiltered = [
            0 => $shopifyMetafieldsResponse[1],
        ];
        $shopifyMetafieldsResponseFilteredOwner = [
            0 => $shopifyMetafieldsResponse[0],
        ];

        $this->taskClass = VariantUpdate::class;
        $task = $this->getTask(null, [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShop(null, ['fields' => 'id'])[0]) => ['data' => ['shop' => ['id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop')]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => $shopifyMetafieldsResponse]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '5321'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => $shopifyMetafieldsResponseFiltered]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '9654'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations', 'key' => 'states'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'california'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'florida'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '5321'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '9654'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafields('9876543210', ['namespace' => 'location_details', 'key' => '5321'])[0]) => ['data' => ['product' => ['metafields' => ['nodes' => $shopifyMetafieldsResponseFilteredOwner]]]],
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
        ], null, null, null, null, null, null, [
            'storis_username' => 'johndoe',
            'storis_secret' => 's3cr3t',
            'api_version' => $dataDestinationApiVersion,
            'data_source_api_version' => $dataSourceApiVersion,
            'data_destination_api_version' => $dataDestinationApiVersion,
        ]);
        $dataMiddleware = $task->getDataMiddleware();
        static::getContainer()->set(VariantDataMiddlewareInterface::class, $dataMiddleware);

        /* @var \Symfony\Bundle\FrameworkBundle\KernelBrowser $client */
        $client->disableReboot(); // preserve kernel state and the object manager registry changes

        $client->request('POST', '/api/metafields', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.FrontendMetafieldApiController::FRONTEND_METAFIELDS_AUTH_TOKEN,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'metafield' => [
                'namespace' => 'location_details',
                'key' => null,
                'ownerId' => null,
            ],
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('metafields', $client->getResponse()->getContent());
        $this->assertSame([
            'metafields' => $shopifyMetafieldsResponse,
        ], json_decode($client->getResponse()->getContent(), true));
    }

    public function testShopifyGraphQLStorisV10FrontendMetafieldGetListWithFilterNamespaceReturnsError($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        $client = static::createClient();

        $shopifyMetafieldsResponse = [
            0 => [
                'id' => $this->generateShopifyGraphQLIdentifier('1112223334445556661', 'Metafield'),
                'value' => 42,
            ],
            1 => [
                'id' => $this->generateShopifyGraphQLIdentifier('1112223334445556662', 'Metafield'),
                'value' => 43,
            ],
        ];
        $shopifyMetafieldsResponseFiltered = [
            0 => $shopifyMetafieldsResponse[1],
        ];
        $shopifyMetafieldsResponseFilteredOwner = [
            0 => $shopifyMetafieldsResponse[0],
        ];
        $shopifyMetafieldsError = new \Exception('There was an error!');

        $this->taskClass = VariantUpdate::class;
        $task = $this->getTask(null, [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShop(null, ['fields' => 'id'])[0]) => ['data' => ['shop' => ['id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop')]]],
            //'/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => $shopifyMetafieldsResponse]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details'])[0]) => $shopifyMetafieldsError,
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '5321'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => $shopifyMetafieldsResponseFiltered]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '9654'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations', 'key' => 'states'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'california'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'florida'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '5321'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '9654'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafields('9876543210', ['namespace' => 'location_details', 'key' => '5321'])[0]) => ['data' => ['product' => ['metafields' => ['nodes' => $shopifyMetafieldsResponseFilteredOwner]]]],
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
        ], null, null, null, null, null, null, [
            'storis_username' => 'johndoe',
            'storis_secret' => 's3cr3t',
            'api_version' => $dataDestinationApiVersion,
            'data_source_api_version' => $dataSourceApiVersion,
            'data_destination_api_version' => $dataDestinationApiVersion,
        ]);
        $dataMiddleware = $task->getDataMiddleware();
        static::getContainer()->set(VariantDataMiddlewareInterface::class, $dataMiddleware);

        /* @var \Symfony\Bundle\FrameworkBundle\KernelBrowser $client */
        $client->disableReboot(); // preserve kernel state and the object manager registry changes

        $client->request('POST', '/api/metafields', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.FrontendMetafieldApiController::FRONTEND_METAFIELDS_AUTH_TOKEN,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'metafield' => [
                'namespace' => 'location_details',
                'key' => null,
                'ownerId' => null,
            ],
        ]));

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringNotContainsString('metafields', $client->getResponse()->getContent());
        $this->assertSame([
            'error' => 'There was an error!',
        ], json_decode($client->getResponse()->getContent(), true));
    }

    public function testShopifyGraphQLStorisV10FrontendMetafieldGetListWithFilterNamespaceAndFilterKey($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        $client = static::createClient();

        $shopifyMetafieldsResponse = [
            0 => [
                'id' => $this->generateShopifyGraphQLIdentifier('1112223334445556661', 'Metafield'),
                'value' => 42,
            ],
            1 => [
                'id' => $this->generateShopifyGraphQLIdentifier('1112223334445556662', 'Metafield'),
                'value' => 43,
            ],
        ];
        $shopifyMetafieldsResponseFiltered = [
            0 => $shopifyMetafieldsResponse[1],
        ];
        $shopifyMetafieldsResponseFilteredOwner = [
            0 => $shopifyMetafieldsResponse[0],
        ];

        $this->taskClass = VariantUpdate::class;
        $task = $this->getTask(null, [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShop(null, ['fields' => 'id'])[0]) => ['data' => ['shop' => ['id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop')]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => $shopifyMetafieldsResponse]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '5321'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => $shopifyMetafieldsResponseFiltered]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '9654'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations', 'key' => 'states'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'california'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'florida'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '5321'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '9654'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafields('9876543210', ['namespace' => 'location_details', 'key' => '5321'])[0]) => ['data' => ['product' => ['metafields' => ['nodes' => $shopifyMetafieldsResponseFilteredOwner]]]],
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
        ], null, null, null, null, null, null, [
            'storis_username' => 'johndoe',
            'storis_secret' => 's3cr3t',
            'api_version' => $dataDestinationApiVersion,
            'data_source_api_version' => $dataSourceApiVersion,
            'data_destination_api_version' => $dataDestinationApiVersion,
        ]);
        $dataMiddleware = $task->getDataMiddleware();
        static::getContainer()->set(VariantDataMiddlewareInterface::class, $dataMiddleware);

        /* @var \Symfony\Bundle\FrameworkBundle\KernelBrowser $client */
        $client->disableReboot(); // preserve kernel state and the object manager registry changes

        $client->request('POST', '/api/metafields', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.FrontendMetafieldApiController::FRONTEND_METAFIELDS_AUTH_TOKEN,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'metafield' => [
                'namespace' => 'location_details',
                'key' => '5321',
                'ownerId' => null,
            ],
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('metafields', $client->getResponse()->getContent());
        $this->assertSame([
            'metafields' => $shopifyMetafieldsResponseFiltered,
        ], json_decode($client->getResponse()->getContent(), true));
    }

    public function testShopifyGraphQLStorisV10FrontendMetafieldGetListWithFilterNamespaceAndFilterKeyAndFilterOwnerId($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        $client = static::createClient();

        $shopifyMetafieldsResponse = [
            0 => [
                'id' => $this->generateShopifyGraphQLIdentifier('1112223334445556661', 'Metafield'),
                'value' => 42,
            ],
            1 => [
                'id' => $this->generateShopifyGraphQLIdentifier('1112223334445556662', 'Metafield'),
                'value' => 43,
            ],
        ];
        $shopifyMetafieldsResponseFiltered = [
            0 => $shopifyMetafieldsResponse[1],
        ];
        $shopifyMetafieldsResponseFilteredOwner = [
            0 => $shopifyMetafieldsResponse[0],
        ];

        $this->taskClass = VariantUpdate::class;
        $task = $this->getTask(null, [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShop(null, ['fields' => 'id'])[0]) => ['data' => ['shop' => ['id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop')]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => $shopifyMetafieldsResponse]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '5321'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => $shopifyMetafieldsResponseFiltered]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '9654'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations', 'key' => 'states'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'california'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'florida'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '5321'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '9654'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafields('9876543210', ['namespace' => 'location_details', 'key' => '5321'])[0]) => ['data' => ['product' => ['metafields' => ['nodes' => $shopifyMetafieldsResponseFilteredOwner]]]],
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
        ], null, null, null, null, null, null, [
            'storis_username' => 'johndoe',
            'storis_secret' => 's3cr3t',
            'api_version' => $dataDestinationApiVersion,
            'data_source_api_version' => $dataSourceApiVersion,
            'data_destination_api_version' => $dataDestinationApiVersion,
        ]);
        $dataMiddleware = $task->getDataMiddleware();
        static::getContainer()->set(VariantDataMiddlewareInterface::class, $dataMiddleware);

        /* @var \Symfony\Bundle\FrameworkBundle\KernelBrowser $client */
        $client->disableReboot(); // preserve kernel state and the object manager registry changes

        $client->request('POST', '/api/metafields', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.FrontendMetafieldApiController::FRONTEND_METAFIELDS_AUTH_TOKEN,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'metafield' => [
                'namespace' => 'location_details',
                'key' => '5321',
                'ownerId' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
            ],
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('metafields', $client->getResponse()->getContent());
        $this->assertSame([
            'metafields' => $shopifyMetafieldsResponseFilteredOwner,
        ], json_decode($client->getResponse()->getContent(), true));
    }

    public function testShopifyGraphQLStorisV10FrontendMetafieldGetListWithUseLocalCacheEnabledAndWithFilterNamespace($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        $client = static::createClient();

        $shopifyMetafieldsResponse = [
            0 => [
                'id' => $this->generateShopifyGraphQLIdentifier('1112223334445556661', 'Metafield'),
                'value' => 42,
            ],
            1 => [
                'id' => $this->generateShopifyGraphQLIdentifier('1112223334445556662', 'Metafield'),
                'value' => 43,
            ],
        ];
        $shopifyMetafieldsResponseFiltered = [
            0 => $shopifyMetafieldsResponse[1],
        ];
        $shopifyMetafieldsResponseFilteredOwner = [
            0 => $shopifyMetafieldsResponse[0],
        ];

        $this->taskClass = VariantUpdate::class;
        $task = $this->getTask([
            ReportMetafieldInterface::class => [
                321 => $this->createReportMetafield('333322221111', 'location_details', 5321, ['id' => '333322221111', 'namespace' => 'location_details', 'key' => '5321', 'value' => json_encode(['test' => 123]), 'type' => 'json_string'], 101),
                654 => $this->createReportMetafield('666655554444', 'location_details', 9654, ['id' => '666655554444', 'namespace' => 'location_details', 'key' => '9654', 'value' => json_encode(['test' => 456]), 'type' => 'json_string'], 102),
            ],
        ], [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShop(null, ['fields' => 'id'])[0]) => ['data' => ['shop' => ['id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop')]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => $shopifyMetafieldsResponse]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '5321'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => $shopifyMetafieldsResponseFiltered]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '9654'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations', 'key' => 'states'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'california'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'florida'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '5321'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '9654'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafields('9876543210', ['namespace' => 'location_details', 'key' => '5321'])[0]) => ['data' => ['product' => ['metafields' => ['nodes' => $shopifyMetafieldsResponseFilteredOwner]]]],
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
        ], null, null, null, null, null, null, [
            'storis_username' => 'johndoe',
            'storis_secret' => 's3cr3t',
            'api_version' => $dataDestinationApiVersion,
            'data_source_api_version' => $dataSourceApiVersion,
            'data_destination_api_version' => $dataDestinationApiVersion,
        ]);
        $dataMiddleware = $task->getDataMiddleware();
        static::getContainer()->set(VariantDataMiddlewareInterface::class, $dataMiddleware);

        /* @var \Symfony\Bundle\FrameworkBundle\KernelBrowser $client */
        $client->disableReboot(); // preserve kernel state and the object manager registry changes

        $client->request('POST', '/api/metafields', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.FrontendMetafieldApiController::FRONTEND_METAFIELDS_AUTH_TOKEN,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'metafield' => [
                'namespace' => 'location_details',
                'key' => null,
                'ownerId' => null,
            ],
            //'useLocalCache' => true, // this is the default
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('metafields', $client->getResponse()->getContent());
        $this->assertSame([
            'metafields' => [
                0 => [
                    'id' => '333322221111',
                    'namespace' => 'location_details',
                    'key' => '5321',
                    'value' => json_encode(['test' => 123]),
                    'type' => 'json_string',
                ],
                1 => [
                    'id' => '666655554444',
                    'namespace' => 'location_details',
                    'key' => '9654',
                    'value' => json_encode(['test' => 456]),
                    'type' => 'json_string',
                ],
            ],
        ], json_decode($client->getResponse()->getContent(), true));
    }

    public function testShopifyGraphQLStorisV10FrontendMetafieldGetListWithUseLocalCacheDisabledAndWithFilterNamespace($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        $client = static::createClient();

        $shopifyMetafieldsResponse = [
            0 => [
                'id' => $this->generateShopifyGraphQLIdentifier('1112223334445556661', 'Metafield'),
                'value' => 42,
            ],
            1 => [
                'id' => $this->generateShopifyGraphQLIdentifier('1112223334445556662', 'Metafield'),
                'value' => 43,
            ],
        ];
        $shopifyMetafieldsResponseFiltered = [
            0 => $shopifyMetafieldsResponse[1],
        ];
        $shopifyMetafieldsResponseFilteredOwner = [
            0 => $shopifyMetafieldsResponse[0],
        ];

        $this->taskClass = VariantUpdate::class;
        $task = $this->getTask([
            ReportMetafieldInterface::class => [
                321 => $this->createReportMetafield('333322221111', 'location_details', 5321, ['id' => '333322221111', 'namespace' => 'location_details', 'key' => '5321', 'value' => json_encode(['test' => 123]), 'type' => 'json_string'], 101),
                654 => $this->createReportMetafield('666655554444', 'location_details', 9654, ['id' => '666655554444', 'namespace' => 'location_details', 'key' => '9654', 'value' => json_encode(['test' => 456]), 'type' => 'json_string'], 102),
            ],
        ], [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShop(null, ['fields' => 'id'])[0]) => ['data' => ['shop' => ['id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop')]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => $shopifyMetafieldsResponse]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '5321'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => $shopifyMetafieldsResponseFiltered]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '9654'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations', 'key' => 'states'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'california'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'florida'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '5321'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '9654'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafields('9876543210', ['namespace' => 'location_details', 'key' => '5321'])[0]) => ['data' => ['product' => ['metafields' => ['nodes' => $shopifyMetafieldsResponseFilteredOwner]]]],
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
        ], null, null, null, null, null, null, [
            'storis_username' => 'johndoe',
            'storis_secret' => 's3cr3t',
            'api_version' => $dataDestinationApiVersion,
            'data_source_api_version' => $dataSourceApiVersion,
            'data_destination_api_version' => $dataDestinationApiVersion,
        ]);
        $dataMiddleware = $task->getDataMiddleware();
        static::getContainer()->set(VariantDataMiddlewareInterface::class, $dataMiddleware);

        /* @var \Symfony\Bundle\FrameworkBundle\KernelBrowser $client */
        $client->disableReboot(); // preserve kernel state and the object manager registry changes

        $client->request('POST', '/api/metafields', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.FrontendMetafieldApiController::FRONTEND_METAFIELDS_AUTH_TOKEN,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'metafield' => [
                'namespace' => 'location_details',
                'key' => null,
                'ownerId' => null,
            ],
            'useLocalCache' => false,
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('metafields', $client->getResponse()->getContent());
        $this->assertSame([
            'metafields' => $shopifyMetafieldsResponse,
        ], json_decode($client->getResponse()->getContent(), true));
    }

    public function testShopifyGraphQLStorisV10FrontendMetafieldGetListWithUseLocalCacheEnabledAndWithForceLatestAndWithFilterNamespace($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        $client = static::createClient();

        $shopifyMetafieldsResponse = [
            0 => [
                'id' => $this->generateShopifyGraphQLIdentifier('1112223334445556661', 'Metafield'),
                'value' => 42,
            ],
            1 => [
                'id' => $this->generateShopifyGraphQLIdentifier('1112223334445556662', 'Metafield'),
                'value' => 43,
            ],
        ];
        $shopifyMetafieldsResponseFiltered = [
            0 => $shopifyMetafieldsResponse[1],
        ];
        $shopifyMetafieldsResponseFilteredOwner = [
            0 => $shopifyMetafieldsResponse[0],
        ];

        $this->taskClass = VariantUpdate::class;
        $task = $this->getTask([
            ReportMetafieldInterface::class => [
                321 => $this->createReportMetafield('333322221111', 'location_details', 5321, ['id' => '333322221111', 'namespace' => 'location_details', 'key' => '5321', 'value' => json_encode(['test' => 123]), 'type' => 'json_string'], 101),
                654 => $this->createReportMetafield('666655554444', 'location_details', 9654, ['id' => '666655554444', 'namespace' => 'location_details', 'key' => '9654', 'value' => json_encode(['test' => 456]), 'type' => 'json_string'], 102),
            ],
        ], [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShop(null, ['fields' => 'id'])[0]) => ['data' => ['shop' => ['id' => $this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop')]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => $shopifyMetafieldsResponse]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '5321'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => $shopifyMetafieldsResponseFiltered]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'location_details', 'key' => '9654'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations', 'key' => 'states'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'california'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'locations_state_cities', 'key' => 'florida'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '5321'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForShopMetafields($this->generateShopifyGraphQLIdentifier('9999888877776666555544443333222211110000', 'Shop'), ['namespace' => 'retail_warehouse', 'key' => '9654'])[0]) => ['data' => ['shop' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafields('9876543210', ['namespace' => 'location_details', 'key' => '5321'])[0]) => ['data' => ['product' => ['metafields' => ['nodes' => $shopifyMetafieldsResponseFilteredOwner]]]],
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
        ], null, null, null, null, null, null, [
            'storis_username' => 'johndoe',
            'storis_secret' => 's3cr3t',
            'api_version' => $dataDestinationApiVersion,
            'data_source_api_version' => $dataSourceApiVersion,
            'data_destination_api_version' => $dataDestinationApiVersion,
        ]);
        $dataMiddleware = $task->getDataMiddleware();
        static::getContainer()->set(VariantDataMiddlewareInterface::class, $dataMiddleware);

        /* @var \Symfony\Bundle\FrameworkBundle\KernelBrowser $client */
        $client->disableReboot(); // preserve kernel state and the object manager registry changes

        $client->request('POST', '/api/metafields', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.FrontendMetafieldApiController::FRONTEND_METAFIELDS_AUTH_TOKEN,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'metafield' => [
                'namespace' => 'location_details',
                'key' => null,
                'ownerId' => null,
            ],
            'useLocalCache' => true,
            'forceLatest' => true,
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('metafields', $client->getResponse()->getContent());
        $this->assertSame([
            'metafields' => $shopifyMetafieldsResponse,
        ], json_decode($client->getResponse()->getContent(), true));
    }

    protected function configureTask(BaseTask $task, $repositoryResults = null, $dataSourceApiResults = null, $dataDestinationApiResults = null, $dataSourceApiConnector = null, $dataDestinationApiConnector = null, $dataSourceHttpClient = null, $dataDestinationHttpClient = null, $objectManager = null, $doctrine = null, $options = [])
    {
        $options['fqcn']['order']['configured_middleware'] = AppOrderMiddleware::class;
        $options['fqcn']['product']['configured_middleware'] = AppProductMiddleware::class;
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

    private function createReportMetafield($metafieldId, $namespace = null, $key = null, $json = [], $id = null)
    {
        $metafield = new ReportMetafield();
        $metafield->setDataSourceMetafieldId($metafieldId);
        $metafield->setDataSourceMetafieldNamespace($namespace);
        $metafield->setDataSourceMetafieldKey($key);
        $metafield->setDataSourceMetafieldJson($json);
        $metafield->setCreatedAt(new \DateTime('2020-07-22 12:34:56 UTC'));
        $metafield->setUpdatedAt(new \DateTime('2020-07-22 12:34:56 UTC'));

        if ($id) {
            $refl = new \ReflectionProperty($metafield, 'id');
            $refl->setAccessible(true);
            $refl->setValue($metafield, $id);
        }

        return $metafield;
    }
}
