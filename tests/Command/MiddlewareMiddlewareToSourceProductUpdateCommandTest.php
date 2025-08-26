<?php

namespace App\Tests\Command;

use App\EcommerceMiddleware\Driver\Shopify\Data\Product\AppProductShopifySource;
use App\EcommerceMiddleware\Driver\Shopify\Data\Variant\AppVariantShopifySource;
use App\EcommerceMiddleware\ShopifyStoris\Data\Order\AppOrderMiddleware;
use App\EcommerceMiddleware\ShopifyStoris\Data\Product\AppProductMiddleware;
use App\EcommerceMiddleware\ShopifyStoris\Data\Variant\AppVariantMiddleware;
use Endertech\EcommerceMiddleware\Contracts\Model\ProductInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\VariantInterface;
use Endertech\EcommerceMiddleware\Core\Decorator\DecoratorInspector;
use Endertech\EcommerceMiddleware\Core\Logger\MultipleLoggerDecorator;
use Endertech\EcommerceMiddleware\Core\Task\BaseTask;
use Endertech\EcommerceMiddleware\Core\Task\MiddlewareToSource\ProductUpdate;
use Endertech\EcommerceMiddleware\Driver\Shopify\Connector\ShopifyClient;
use Endertech\EcommerceMiddleware\Driver\Storis\Connector\StorisClient;
use Endertech\EcommerceMiddlewareReport\Contracts\Model\ReportMetafieldInterface;
use Endertech\EcommerceMiddlewareReport\Contracts\Model\ReportOrderMetafieldInterface;
use Endertech\EcommerceMiddlewareReport\Contracts\Model\ReportProductMetafieldInterface;
use Endertech\EcommerceMiddlewareReport\Contracts\Model\ReportVariantMetafieldInterface;
use Endertech\EcommerceMiddlewareReportMetafieldBundle\Repository\ReportMetafieldRepository;
use Endertech\EcommerceMiddlewareReportMetafieldBundle\Repository\ReportOrderMetafieldRepository;
use Endertech\EcommerceMiddlewareReportMetafieldBundle\Repository\ReportProductMetafieldRepository;
use Endertech\EcommerceMiddlewareReportMetafieldBundle\Repository\ReportVariantMetafieldRepository;
use Endertech\EcommerceMiddlewareShopifyStorisBundle\Tests\Command\MiddlewareMiddlewareToSourceProductUpdateCommandTest as BaseTest;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class MiddlewareMiddlewareToSourceProductUpdateCommandTest extends BaseTest
{
    /*
    public function testExecuteWithShopifyGraphQLStorisV10ChangesShopifyProductPublishedStatusAndUpdatesProductTagsAndUpdatesMinWebStockAvailabilityMetafield($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        $storisProduct = $this->getResponseJsonStorisProduct('ABC-123', $dataDestinationApiVersion);

        $this->taskClass = ProductUpdate::class;

        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('middleware:middleware-to-source:product-update');

        $configuredTask = $this->configureTask($command->getTask(), [
            ProductInterface::class => [
                0 => $this->createShopifyProduct('9876543210')->setMinWebStockAvailabilityMetaFieldId(null),
            ],
            VariantInterface::class => [
                0 => $this->createShopifyVariant('ABC-123', 963)->setDataDestinationJson($storisProduct),
            ],
        ], [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProduct('9876543210', ['fields' => 'tags'])[0]) => ['data' => ['product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion)]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductUpdate('9876543210')[0]) => ['data' => ['productUpdate' => ['product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion)]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'custom_fields', 'key' => 'minimum_web_stock_availability'])[0]) => ['data' => ['product' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldUpdate('2019181716151413121110', '9876543210')[0]) => ['data' => ['metafieldsSet' => ['metafields' => [0 => $this->getResponseJsonShopifyMetafield('2019181716151413121110', $dataSourceApiVersion)]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductPublish('9876543210', ['published' => true])[0]) => ['data' => ['publishablePublishToCurrentChannel' => ['publishable' => ['id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product')]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductUnpublish('9876543210', ['published' => false])[0]) => ['data' => ['publishableUnpublishToCurrentChannel' => ['publishable' => ['id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product')]]]],
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
            'api/Locations/Changes' => $this->getResponseJsonStorisLocations($dataDestinationApiVersion),
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
        $this->assertStringContainsString('1/1', $output);
        $this->assertStringContainsString('100%', $output);

        $this->assertTrue($this->getLogger()->hasInfoRecords(), 'There should be INFO logs');
        $this->assertFalse($this->getLogger()->hasErrorRecords(), 'There should not be ERROR logs');
        $this->assertTrue($this->getLogger()->hasDebugRecords(), 'There should be DEBUG logs');

        $this->assertTrue($this->getLogger()->hasInfo('START PRODUCT UPDATE PROCESS'));
        $this->assertTrue($this->getLogger()->hasInfo('Found {resultCount} Products to update'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProduct('9876543210', ['fields' => 'tags'])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductUpdate('9876543210')[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Successfully Updated tags for Product: {dataSourceProductId}'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldUpdate('2019181716151413121110', '9876543210')[0])));
        $this->assertFalse($this->getLogger()->hasInfo('Successfully Updated Shopify Metafield ID: {metaFieldId}'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'custom_fields', 'key' => 'minimum_web_stock_availability'])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Successful GET Metafield for Product ID: {dataSourceProductId}'));
        $this->assertTrue($this->getLogger()->hasInfo('Successfully Created Metafield for Product: {dataSourceProductId}'));
        $this->assertTrue($this->getLogger()->hasInfo('Updated Metafield data in Product for ID: {dataSourceProductId}'));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, SKU: ABC-123 | Found {resultCount} Shopify Products to publish'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductPublish('9876543210', ['published' => true])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Changed Product Availability for Product ID: {dataSourceProductId} to {productStatus}'));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, SKU: ~ | Found {resultCount} Shopify Products to unpublish'));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, SKU: ~ | No Shopify Products to unpublish'));
        $this->assertTrue($this->getLogger()->hasInfo('END PRODUCT UPDATE PROCESS'));

        $this->assertEquals('START PRODUCT UPDATE PROCESS', $this->getLogger()->records[0]['message']);

        $this->assertEquals('Found {resultCount} Products to update', $this->getLogger()->records[1]['message']);
        $this->assertEquals(1, $this->getLogger()->records[1]['context']['resultCount']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProduct('9876543210', ['fields' => 'tags'])[0]), $this->getLogger()->records[2]['message']);
        $this->assertSame([
            'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        ], $this->getLogger()->records[2]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion),
            ],
        ], $this->getLogger()->records[2]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProduct('9876543210', ['fields' => 'tags'])[0]), $this->getLogger()->records[3]['message']);
        $this->assertSame([
            'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        ], $this->getLogger()->records[3]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion),
            ],
        ], $this->getLogger()->records[3]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductUpdate('9876543210')[0]), $this->getLogger()->records[4]['message']);
        $this->assertSame([
            'product' => [
                'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
                'tags' => [
                    0 => 'available_TestLoc321',
                    1 => 'available_TestLoc654',
                ],
            ],
        ], $this->getLogger()->records[4]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'productUpdate' => [
                    'product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion),
                ],
            ],
        ], $this->getLogger()->records[4]['context']['response']);

        $this->assertEquals('Successfully Updated tags for Product: {dataSourceProductId}', $this->getLogger()->records[5]['message']);
        $this->assertEquals('9876543210', $this->getLogger()->records[5]['context']['dataSourceProductId']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'custom_fields', 'key' => 'minimum_web_stock_availability'])[0]), $this->getLogger()->records[6]['message']);
        $this->assertSame([
            'perPage' => 50,
            'endCursor' => null,
            'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        ], $this->getLogger()->records[6]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'product' => [
                    'metafields' => [
                        'nodes' => [],
                    ],
                ],
            ],
        ], $this->getLogger()->records[6]['context']['response']);

        $this->assertEquals('Successful GET Metafield for Product ID: {dataSourceProductId}', $this->getLogger()->records[7]['message']);
        $this->assertEquals('9876543210', $this->getLogger()->records[7]['context']['dataSourceProductId']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductUpdate('9876543210')[0]), $this->getLogger()->records[8]['message']);
        $this->assertSame([
            'product' => [
                'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
                'metafields' => [
                    0 => [
                        'namespace' => 'custom_fields',
                        'key' => 'minimum_web_stock_availability',
                        'value' => '42',
                        'type' => 'integer',
                    ],
                ],
            ],
        ], $this->getLogger()->records[8]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'productUpdate' => [
                    'product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion),
                ],
            ],
        ], $this->getLogger()->records[8]['context']['response']);

        $this->assertEquals('Successfully Created Metafield for Product: {dataSourceProductId}', $this->getLogger()->records[9]['message']);
        $this->assertEquals('9876543210', $this->getLogger()->records[9]['context']['dataSourceProductId']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'custom_fields', 'key' => 'minimum_web_stock_availability'])[0]), $this->getLogger()->records[10]['message']);
        $this->assertSame([
            'perPage' => 50,
            'endCursor' => null,
            'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        ], $this->getLogger()->records[10]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'product' => [
                    'metafields' => [
                        'nodes' => [],
                    ],
                ],
            ],
        ], $this->getLogger()->records[10]['context']['response']);

        $this->assertEquals('Successful GET Metafield for Product ID: {dataSourceProductId}', $this->getLogger()->records[11]['message']);
        $this->assertEquals('9876543210', $this->getLogger()->records[11]['context']['dataSourceProductId']);

        $this->assertEquals('Updated Metafield data in Product for ID: {dataSourceProductId}', $this->getLogger()->records[12]['message']);
        $this->assertEquals('9876543210', $this->getLogger()->records[12]['context']['dataSourceProductId']);
        $this->assertEquals([
            'tags' => 'available_TestLoc321,available_TestLoc654',
        ], $this->getLogger()->records[12]['context']['dataSourceUpdateProductTagsRequest']);
        $this->assertEquals([
            'key' => 'minimum_web_stock_availability',
        ], $this->getLogger()->records[12]['context']['dataSourceMetafieldsMinimumWebStockAvailabilityRequest']);
        $this->assertEquals([
            'metafields' => [
                0 => [
                    'key' => 'minimum_web_stock_availability',
                    'value' => 42,
                    'type' => 'integer',
                    'namespace' => 'custom_fields',
                ],
            ],
        ], $this->getLogger()->records[12]['context']['dataSourceProductMinWebStockAvailabilityMetaFieldCreateRequest']);

        $this->assertEquals('[1/1] Product ID: 9876543210, SKU: ABC-123 | Found {resultCount} Shopify Products to publish', $this->getLogger()->records[13]['message']);
        $this->assertEquals(1, $this->getLogger()->records[13]['context']['resultCount']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductPublish('9876543210', ['published' => true])[0]), $this->getLogger()->records[14]['message']);
        $this->assertSame([
            'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        ], $this->getLogger()->records[14]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'publishablePublishToCurrentChannel' => [
                    'publishable' => [
                        'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
                    ],
                ],
            ],
        ], $this->getLogger()->records[14]['context']['response']);

        $this->assertEquals('Changed Product Availability for Product ID: {dataSourceProductId} to {productStatus}', $this->getLogger()->records[15]['message']);
        $this->assertEquals('9876543210', $this->getLogger()->records[15]['context']['dataSourceProductId']);
        $this->assertEquals('Available', $this->getLogger()->records[15]['context']['productStatus']);
        $this->assertEquals([
            'published' => true,
        ], $this->getLogger()->records[15]['context']['dataSourceProductPublishRequest']);

        $this->assertEquals('[1/1] Product ID: 9876543210, SKU: ~ | Found {resultCount} Shopify Products to unpublish', $this->getLogger()->records[16]['message']);
        $this->assertEquals(0, $this->getLogger()->records[16]['context']['resultCount']);

        $this->assertEquals('[1/1] Product ID: 9876543210, SKU: ~ | No Shopify Products to unpublish', $this->getLogger()->records[17]['message']);

        $this->assertEquals('END PRODUCT UPDATE PROCESS', $this->getLogger()->records[18]['message']);
    }
    */

    public function testExecuteWithShopifyGraphQLStorisV10DoesNotChangeShopifyProductPublishedStatusAndDoesNotUpdateProductTagsAndDoesNotUpdateMinWebStockAvailabilityMetafield($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        // the existing ProductUpdate task will be used to trigger other updates, so the default functionality should be suppressed
        // @see \App\EcommerceMiddleware\Driver\Shopify\Data\Product\AppProductShopifySource
        $storisProduct = $this->getResponseJsonStorisProduct('ABC-123', $dataDestinationApiVersion);

        $this->taskClass = ProductUpdate::class;

        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('middleware:middleware-to-source:product-update');

        $configuredTask = $this->configureTask($command->getTask(), [
            ProductInterface::class => [
                0 => $this->createShopifyProduct('9876543210')->setMinWebStockAvailabilityMetaFieldId(null),
            ],
            VariantInterface::class => [
                0 => $this->createShopifyVariant('ABC-123', 963)->setDataDestinationJson($storisProduct),
            ],
        ], [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProduct('9876543210', ['fields' => 'tags'])[0]) => ['data' => ['product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion)]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductUpdate('9876543210')[0]) => ['data' => ['productUpdate' => ['product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion)]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'custom_fields', 'key' => 'minimum_web_stock_availability'])[0]) => ['data' => ['product' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldUpdate('2019181716151413121110', '9876543210')[0]) => ['data' => ['metafieldsSet' => ['metafields' => [0 => $this->getResponseJsonShopifyMetafield('2019181716151413121110', $dataSourceApiVersion)]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductPublish('9876543210', ['published' => true])[0]) => ['data' => ['publishablePublishToCurrentChannel' => ['publishable' => ['id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product')]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductUnpublish('9876543210', ['published' => false])[0]) => ['data' => ['publishableUnpublishToCurrentChannel' => ['publishable' => ['id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product')]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'mw_marketing', 'key' => 'is_on_sale'])[0]) => ['data' => ['product' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldCreate('9876543210', [])[0]) => ['data' => ['metafieldsSet' => ['metafields' => [0 => $this->getResponseJsonShopifyMetafield('111222333444', $dataSourceApiVersion)]]]],
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
            'api/Locations/Changes' => $this->getResponseJsonStorisLocations($dataDestinationApiVersion),
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
        $this->assertStringContainsString('1/1', $output);
        $this->assertStringContainsString('100%', $output);

        $this->assertTrue($this->getLogger()->hasInfoRecords(), 'There should be INFO logs');
        $this->assertFalse($this->getLogger()->hasErrorRecords(), 'There should not be ERROR logs');
        $this->assertTrue($this->getLogger()->hasDebugRecords(), 'There should be DEBUG logs');

        $this->assertTrue($this->getLogger()->hasInfo('START PRODUCT UPDATE PROCESS'));
        $this->assertTrue($this->getLogger()->hasInfo('Found {resultCount} Products to update'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProduct('9876543210', ['fields' => 'tags'])[0])));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductUpdate('9876543210')[0])));
        $this->assertFalse($this->getLogger()->hasInfo('Successfully Updated tags for Product: {dataSourceProductId}'));
        //$this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldUpdate('2019181716151413121110', '9876543210')[0])));
        $this->assertFalse($this->getLogger()->hasInfo('Successfully Updated Shopify Metafield ID: {metaFieldId}'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'custom_fields', 'key' => 'minimum_web_stock_availability'])[0])));
        $this->assertFalse($this->getLogger()->hasInfo('Successful GET Metafield for Product ID: {dataSourceProductId}'));
        $this->assertFalse($this->getLogger()->hasInfo('Successfully Created Metafield for Product: {dataSourceProductId}'));
        $this->assertTrue($this->getLogger()->hasInfo('Updated Metafield data in Product for ID: {dataSourceProductId}'));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, SKU: ABC-123 | Found {resultCount} Shopify Products to publish'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductPublish('9876543210', ['published' => true])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Changed Product Availability for Product ID: {dataSourceProductId} to {productStatus}'));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, SKU: ~ | Found {resultCount} Shopify Products to unpublish'));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, SKU: ~ | No Shopify Products to unpublish'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'mw_marketing', 'key' => 'is_on_sale'])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldCreate('9876543210', [])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Updated Product {dataSourceProductId} Is On Sale Status to {formattedProductIsOnSaleStatus}'));
        $this->assertTrue($this->getLogger()->hasInfo('END PRODUCT UPDATE PROCESS'));

        $this->assertEquals('START PRODUCT UPDATE PROCESS', $this->getLogger()->records[0]['message']);

        $this->assertEquals('Found {resultCount} Products to update', $this->getLogger()->records[1]['message']);
        $this->assertEquals(1, $this->getLogger()->records[1]['context']['resultCount']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProduct('9876543210', ['fields' => 'tags'])[0]), $this->getLogger()->records[2]['message']);
        $this->assertSame([
            'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        ], $this->getLogger()->records[2]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion),
            ],
        ], $this->getLogger()->records[2]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProduct('9876543210', ['fields' => 'tags'])[0]), $this->getLogger()->records[3]['message']);
        $this->assertSame([
            'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        ], $this->getLogger()->records[3]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion),
            ],
        ], $this->getLogger()->records[3]['context']['response']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductUpdate('9876543210')[0]), $this->getLogger()->records[4]['message']);
        //$this->assertSame([
        //    'product' => [
        //        'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        //        'tags' => [
        //            0 => 'available_TestLoc321',
        //            1 => 'available_TestLoc654',
        //        ],
        //    ],
        //], $this->getLogger()->records[4]['context']['request']['variables']);
        //$this->assertSame([
        //    'data' => [
        //        'productUpdate' => [
        //            'product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion),
        //        ],
        //    ],
        //], $this->getLogger()->records[4]['context']['response']);
        //
        //$this->assertEquals('Successfully Updated tags for Product: {dataSourceProductId}', $this->getLogger()->records[4]['message']);
        //$this->assertEquals('9876543210', $this->getLogger()->records[4]['context']['dataSourceProductId']);
        //
        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'custom_fields', 'key' => 'minimum_web_stock_availability'])[0]), $this->getLogger()->records[4]['message']);
        //$this->assertSame([
        //    'perPage' => 50,
        //    'endCursor' => null,
        //    'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        //], $this->getLogger()->records[4]['context']['request']['variables']);
        //$this->assertSame([
        //    'data' => [
        //        'product' => [
        //            'metafields' => [
        //                'nodes' => [],
        //            ],
        //        ],
        //    ],
        //], $this->getLogger()->records[4]['context']['response']);
        //
        //$this->assertEquals('Successful GET Metafield for Product ID: {dataSourceProductId}', $this->getLogger()->records[4]['message']);
        //$this->assertEquals('9876543210', $this->getLogger()->records[4]['context']['dataSourceProductId']);
        //
        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductUpdate('9876543210')[0]), $this->getLogger()->records[4]['message']);
        //$this->assertSame([
        //    'product' => [
        //        'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        //        'metafields' => [
        //            0 => [
        //                'namespace' => 'custom_fields',
        //                'key' => 'minimum_web_stock_availability',
        //                'value' => '42',
        //                'type' => 'integer',
        //            ],
        //        ],
        //    ],
        //], $this->getLogger()->records[4]['context']['request']['variables']);
        //$this->assertSame([
        //    'data' => [
        //        'productUpdate' => [
        //            'product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion),
        //        ],
        //    ],
        //], $this->getLogger()->records[4]['context']['response']);
        //
        //$this->assertEquals('Successfully Created Metafield for Product: {dataSourceProductId}', $this->getLogger()->records[4]['message']);
        //$this->assertEquals('9876543210', $this->getLogger()->records[4]['context']['dataSourceProductId']);
        //
        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'custom_fields', 'key' => 'minimum_web_stock_availability'])[0]), $this->getLogger()->records[4]['message']);
        //$this->assertSame([
        //    'perPage' => 50,
        //    'endCursor' => null,
        //    'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        //], $this->getLogger()->records[4]['context']['request']['variables']);
        //$this->assertSame([
        //    'data' => [
        //        'product' => [
        //            'metafields' => [
        //                'nodes' => [],
        //            ],
        //        ],
        //    ],
        //], $this->getLogger()->records[4]['context']['response']);
        //
        //$this->assertEquals('Successful GET Metafield for Product ID: {dataSourceProductId}', $this->getLogger()->records[4]['message']);
        //$this->assertEquals('9876543210', $this->getLogger()->records[4]['context']['dataSourceProductId']);
        $this->assertEquals('Updated Metafield data in Product for ID: {dataSourceProductId}', $this->getLogger()->records[4]['message']);
        $this->assertEquals('9876543210', $this->getLogger()->records[4]['context']['dataSourceProductId']);
        $this->assertEquals([
            //'tags' => 'available_TestLoc321,available_TestLoc654',
        ], $this->getLogger()->records[4]['context']['dataSourceUpdateProductTagsRequest']);
        $this->assertEquals([
            //'key' => 'minimum_web_stock_availability',
        ], $this->getLogger()->records[4]['context']['dataSourceMetafieldsMinimumWebStockAvailabilityRequest']);
        $this->assertEquals([
            //'metafields' => [
            //    0 => [
            //        'key' => 'minimum_web_stock_availability',
            //        'value' => 42,
            //        'type' => 'integer',
            //        'namespace' => 'custom_fields',
            //    ],
            //],
        ], $this->getLogger()->records[4]['context']['dataSourceProductMinWebStockAvailabilityMetaFieldCreateRequest']);

        $this->assertEquals('[1/1] Product ID: 9876543210, SKU: ABC-123 | Found {resultCount} Shopify Products to publish', $this->getLogger()->records[5]['message']);
        $this->assertEquals(1, $this->getLogger()->records[5]['context']['resultCount']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductPublish('9876543210', ['published' => true])[0]), $this->getLogger()->records[6]['message']);
        //$this->assertSame([
        //    'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        //], $this->getLogger()->records[6]['context']['request']['variables']);
        //$this->assertSame([
        //    'data' => [
        //        'publishablePublishToCurrentChannel' => [
        //            'publishable' => [
        //                'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        //            ],
        //        ],
        //    ],
        //], $this->getLogger()->records[6]['context']['response']);
        $this->assertEquals('Changed Product Availability for Product ID: {dataSourceProductId} to {productStatus}', $this->getLogger()->records[6]['message']);
        $this->assertEquals('9876543210', $this->getLogger()->records[6]['context']['dataSourceProductId']);
        $this->assertEquals('Available', $this->getLogger()->records[6]['context']['productStatus']);
        $this->assertEquals([
            //'published' => true,
        ], $this->getLogger()->records[6]['context']['dataSourceProductPublishRequest']);

        $this->assertEquals('[1/1] Product ID: 9876543210, SKU: ~ | Found {resultCount} Shopify Products to unpublish', $this->getLogger()->records[7]['message']);
        $this->assertEquals(0, $this->getLogger()->records[7]['context']['resultCount']);

        $this->assertEquals('[1/1] Product ID: 9876543210, SKU: ~ | No Shopify Products to unpublish', $this->getLogger()->records[8]['message']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'mw_marketing', 'key' => 'is_on_sale'])[0]), $this->getLogger()->records[9]['message']);
        $this->assertSame([
            'perPage' => 50,
            'endCursor' => null,
            'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        ], $this->getLogger()->records[9]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'product' => [
                    'metafields' => [
                        'nodes' => [],
                    ],
                ],
            ],
        ], $this->getLogger()->records[9]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldCreate('9876543210', [])[0]), $this->getLogger()->records[10]['message']);
        $this->assertSame([
            'metafields' => [
                0 => [
                    'ownerId' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
                    'namespace' => 'mw_marketing',
                    'key' => 'is_on_sale',
                    'value' => 'false',
                    'type' => 'boolean',
                ],
            ],
        ], $this->getLogger()->records[10]['context']['request']['variables']);
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
        ], $this->getLogger()->records[10]['context']['response']);

        $this->assertEquals('Updated Product {dataSourceProductId} Is On Sale Status to {formattedProductIsOnSaleStatus}', $this->getLogger()->records[11]['message']);
        $this->assertEquals('9876543210', $this->getLogger()->records[11]['context']['dataSourceProductId']);
        $this->assertEquals('false', $this->getLogger()->records[11]['context']['formattedProductIsOnSaleStatus']);
        $this->assertEquals([
            963 => [
                'price' => 123.45,
                'compare_at_price' => 123.45,
            ],
        ], $this->getLogger()->records[11]['context']['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_variantPriceRequests']);
        $this->assertEquals([
            963 => false,
        ], $this->getLogger()->records[11]['context']['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_checkPrices']);
        $this->assertEquals([
            '' => false,
        ], $this->getLogger()->records[11]['context']['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_isOnSale']);
        $this->assertEquals([
            '' => [
                'namespace' => 'mw_marketing',
                'key' => 'is_on_sale',
                'value' => 'false',
                'type' => 'boolean',
            ],
        ], $this->getLogger()->records[11]['context']['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_requests']);
        $this->assertEquals([
            '' => $this->normalizeMiddlewareIdInNodes([
                'id' => $this->generateShopifyGraphQLIdentifier('111222333444', 'Metafield'),
                'value' => 42,
            ]),
        ], $this->getLogger()->records[11]['context']['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_responses']);

        $this->assertEquals('END PRODUCT UPDATE PROCESS', $this->getLogger()->records[12]['message']);
    }

    public function testExecuteWithShopifyGraphQLStorisV10UpdatesIsOnSaleMetafieldStatus($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        $storisProduct = $this->getResponseJsonStorisProduct('ABC-123', $dataDestinationApiVersion);

        $this->taskClass = ProductUpdate::class;

        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('middleware:middleware-to-source:product-update');

        $configuredTask = $this->configureTask($command->getTask(), [
            ProductInterface::class => [
                0 => $this->createShopifyProduct('9876543210')->setMinWebStockAvailabilityMetaFieldId(null),
            ],
            VariantInterface::class => [
                0 => $this->createShopifyVariant('ABC-123', 963)->setDataDestinationJson($storisProduct),
            ],
        ], [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProduct('9876543210', ['fields' => 'tags'])[0]) => ['data' => ['product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion)]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductUpdate('9876543210')[0]) => ['data' => ['productUpdate' => ['product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion)]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'custom_fields', 'key' => 'minimum_web_stock_availability'])[0]) => ['data' => ['product' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldUpdate('2019181716151413121110', '9876543210')[0]) => ['data' => ['metafieldsSet' => ['metafields' => [0 => $this->getResponseJsonShopifyMetafield('2019181716151413121110', $dataSourceApiVersion)]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductPublish('9876543210', ['published' => true])[0]) => ['data' => ['publishablePublishToCurrentChannel' => ['publishable' => ['id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product')]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductUnpublish('9876543210', ['published' => false])[0]) => ['data' => ['publishableUnpublishToCurrentChannel' => ['publishable' => ['id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product')]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'mw_marketing', 'key' => 'is_on_sale'])[0]) => ['data' => ['product' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldCreate('9876543210', [])[0]) => ['data' => ['metafieldsSet' => ['metafields' => [0 => $this->getResponseJsonShopifyMetafield('111222333444', $dataSourceApiVersion)]]]],
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
            'api/Locations/Changes' => $this->getResponseJsonStorisLocations($dataDestinationApiVersion),
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
        $this->assertStringContainsString('1/1', $output);
        $this->assertStringContainsString('100%', $output);

        $this->assertTrue($this->getLogger()->hasInfoRecords(), 'There should be INFO logs');
        $this->assertFalse($this->getLogger()->hasErrorRecords(), 'There should not be ERROR logs');
        $this->assertTrue($this->getLogger()->hasDebugRecords(), 'There should be DEBUG logs');

        $this->assertTrue($this->getLogger()->hasInfo('START PRODUCT UPDATE PROCESS'));
        $this->assertTrue($this->getLogger()->hasInfo('Found {resultCount} Products to update'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProduct('9876543210', ['fields' => 'tags'])[0])));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductUpdate('9876543210')[0])));
        $this->assertFalse($this->getLogger()->hasInfo('Successfully Updated tags for Product: {dataSourceProductId}'));
        //$this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldUpdate('2019181716151413121110', '9876543210')[0])));
        $this->assertFalse($this->getLogger()->hasInfo('Successfully Updated Shopify Metafield ID: {metaFieldId}'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'custom_fields', 'key' => 'minimum_web_stock_availability'])[0])));
        $this->assertFalse($this->getLogger()->hasInfo('Successful GET Metafield for Product ID: {dataSourceProductId}'));
        $this->assertFalse($this->getLogger()->hasInfo('Successfully Created Metafield for Product: {dataSourceProductId}'));
        $this->assertTrue($this->getLogger()->hasInfo('Updated Metafield data in Product for ID: {dataSourceProductId}'));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, SKU: ABC-123 | Found {resultCount} Shopify Products to publish'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductPublish('9876543210', ['published' => true])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Changed Product Availability for Product ID: {dataSourceProductId} to {productStatus}'));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, SKU: ~ | Found {resultCount} Shopify Products to unpublish'));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, SKU: ~ | No Shopify Products to unpublish'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'mw_marketing', 'key' => 'is_on_sale'])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldCreate('9876543210', [])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Updated Product {dataSourceProductId} Is On Sale Status to {formattedProductIsOnSaleStatus}'));
        $this->assertTrue($this->getLogger()->hasInfo('END PRODUCT UPDATE PROCESS'));

        $this->assertEquals('START PRODUCT UPDATE PROCESS', $this->getLogger()->records[0]['message']);

        $this->assertEquals('Found {resultCount} Products to update', $this->getLogger()->records[1]['message']);
        $this->assertEquals(1, $this->getLogger()->records[1]['context']['resultCount']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProduct('9876543210', ['fields' => 'tags'])[0]), $this->getLogger()->records[2]['message']);
        $this->assertSame([
            'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        ], $this->getLogger()->records[2]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion),
            ],
        ], $this->getLogger()->records[2]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProduct('9876543210', ['fields' => 'tags'])[0]), $this->getLogger()->records[3]['message']);
        $this->assertSame([
            'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        ], $this->getLogger()->records[3]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion),
            ],
        ], $this->getLogger()->records[3]['context']['response']);

        $this->assertEquals('Updated Metafield data in Product for ID: {dataSourceProductId}', $this->getLogger()->records[4]['message']);
        $this->assertEquals('9876543210', $this->getLogger()->records[4]['context']['dataSourceProductId']);
        $this->assertEquals([], $this->getLogger()->records[4]['context']['dataSourceUpdateProductTagsRequest']);
        $this->assertEquals([], $this->getLogger()->records[4]['context']['dataSourceMetafieldsMinimumWebStockAvailabilityRequest']);
        $this->assertEquals([], $this->getLogger()->records[4]['context']['dataSourceProductMinWebStockAvailabilityMetaFieldCreateRequest']);

        $this->assertEquals('[1/1] Product ID: 9876543210, SKU: ABC-123 | Found {resultCount} Shopify Products to publish', $this->getLogger()->records[5]['message']);
        $this->assertEquals(1, $this->getLogger()->records[5]['context']['resultCount']);

        $this->assertEquals('Changed Product Availability for Product ID: {dataSourceProductId} to {productStatus}', $this->getLogger()->records[6]['message']);
        $this->assertEquals('9876543210', $this->getLogger()->records[6]['context']['dataSourceProductId']);
        $this->assertEquals('Available', $this->getLogger()->records[6]['context']['productStatus']);
        $this->assertEquals([], $this->getLogger()->records[6]['context']['dataSourceProductPublishRequest']);

        $this->assertEquals('[1/1] Product ID: 9876543210, SKU: ~ | Found {resultCount} Shopify Products to unpublish', $this->getLogger()->records[7]['message']);
        $this->assertEquals(0, $this->getLogger()->records[7]['context']['resultCount']);

        $this->assertEquals('[1/1] Product ID: 9876543210, SKU: ~ | No Shopify Products to unpublish', $this->getLogger()->records[8]['message']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'mw_marketing', 'key' => 'is_on_sale'])[0]), $this->getLogger()->records[9]['message']);
        $this->assertSame([
            'perPage' => 50,
            'endCursor' => null,
            'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        ], $this->getLogger()->records[9]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'product' => [
                    'metafields' => [
                        'nodes' => [],
                    ],
                ],
            ],
        ], $this->getLogger()->records[9]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldCreate('9876543210', [])[0]), $this->getLogger()->records[10]['message']);
        $this->assertSame([
            'metafields' => [
                0 => [
                    'ownerId' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
                    'namespace' => 'mw_marketing',
                    'key' => 'is_on_sale',
                    'value' => 'false',
                    'type' => 'boolean',
                ],
            ],
        ], $this->getLogger()->records[10]['context']['request']['variables']);
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
        ], $this->getLogger()->records[10]['context']['response']);

        $this->assertEquals('Updated Product {dataSourceProductId} Is On Sale Status to {formattedProductIsOnSaleStatus}', $this->getLogger()->records[11]['message']);
        $this->assertEquals('9876543210', $this->getLogger()->records[11]['context']['dataSourceProductId']);
        $this->assertEquals('false', $this->getLogger()->records[11]['context']['formattedProductIsOnSaleStatus']);
        $this->assertEquals([
            963 => [
                'price' => 123.45,
                'compare_at_price' => 123.45,
            ],
        ], $this->getLogger()->records[11]['context']['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_variantPriceRequests']);
        $this->assertEquals([
            963 => false,
        ], $this->getLogger()->records[11]['context']['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_checkPrices']);
        $this->assertEquals([
            '' => false,
        ], $this->getLogger()->records[11]['context']['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_isOnSale']);
        $this->assertEquals([
            '' => [
                'namespace' => 'mw_marketing',
                'key' => 'is_on_sale',
                'value' => 'false',
                'type' => 'boolean',
            ],
        ], $this->getLogger()->records[11]['context']['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_requests']);
        $this->assertEquals([
            '' => $this->normalizeMiddlewareIdInNodes([
                'id' => $this->generateShopifyGraphQLIdentifier('111222333444', 'Metafield'),
                'value' => 42,
            ]),
        ], $this->getLogger()->records[11]['context']['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_responses']);

        $this->assertEquals('END PRODUCT UPDATE PROCESS', $this->getLogger()->records[12]['message']);
    }

    public function testExecuteWithShopifyGraphQLStorisV10UpdatesIsOnSaleMetafieldStatusWithDifferentPrices($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        $storisProduct = $this->getResponseJsonStorisProduct('ABC-123', $dataDestinationApiVersion);
        $storisProduct['data']['products'][0]['inventoryPrices'][0]['currentSellingPrice'] = 123.00; // previously 123.45

        $this->taskClass = ProductUpdate::class;

        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('middleware:middleware-to-source:product-update');

        $configuredTask = $this->configureTask($command->getTask(), [
            ProductInterface::class => [
                0 => $this->createShopifyProduct('9876543210')->setMinWebStockAvailabilityMetaFieldId(null),
            ],
            VariantInterface::class => [
                0 => $this->createShopifyVariant('ABC-123', 963)->setDataDestinationJson($storisProduct),
            ],
        ], [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProduct('9876543210', ['fields' => 'tags'])[0]) => ['data' => ['product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion)]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductUpdate('9876543210')[0]) => ['data' => ['productUpdate' => ['product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion)]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'custom_fields', 'key' => 'minimum_web_stock_availability'])[0]) => ['data' => ['product' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldUpdate('2019181716151413121110', '9876543210')[0]) => ['data' => ['metafieldsSet' => ['metafields' => [0 => $this->getResponseJsonShopifyMetafield('2019181716151413121110', $dataSourceApiVersion)]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductPublish('9876543210', ['published' => true])[0]) => ['data' => ['publishablePublishToCurrentChannel' => ['publishable' => ['id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product')]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductUnpublish('9876543210', ['published' => false])[0]) => ['data' => ['publishableUnpublishToCurrentChannel' => ['publishable' => ['id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product')]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'mw_marketing', 'key' => 'is_on_sale'])[0]) => ['data' => ['product' => ['metafields' => ['nodes' => []]]]],
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldCreate('9876543210', [])[0]) => ['data' => ['metafieldsSet' => ['metafields' => [0 => $this->getResponseJsonShopifyMetafield('111222333444', $dataSourceApiVersion)]]]],
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
            'api/Locations/Changes' => $this->getResponseJsonStorisLocations($dataDestinationApiVersion),
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
        $this->assertStringContainsString('1/1', $output);
        $this->assertStringContainsString('100%', $output);

        $this->assertTrue($this->getLogger()->hasInfoRecords(), 'There should be INFO logs');
        $this->assertFalse($this->getLogger()->hasErrorRecords(), 'There should not be ERROR logs');
        $this->assertTrue($this->getLogger()->hasDebugRecords(), 'There should be DEBUG logs');

        $this->assertTrue($this->getLogger()->hasInfo('START PRODUCT UPDATE PROCESS'));
        $this->assertTrue($this->getLogger()->hasInfo('Found {resultCount} Products to update'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProduct('9876543210', ['fields' => 'tags'])[0])));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductUpdate('9876543210')[0])));
        $this->assertFalse($this->getLogger()->hasInfo('Successfully Updated tags for Product: {dataSourceProductId}'));
        //$this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldUpdate('2019181716151413121110', '9876543210')[0])));
        $this->assertFalse($this->getLogger()->hasInfo('Successfully Updated Shopify Metafield ID: {metaFieldId}'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'custom_fields', 'key' => 'minimum_web_stock_availability'])[0])));
        $this->assertFalse($this->getLogger()->hasInfo('Successful GET Metafield for Product ID: {dataSourceProductId}'));
        $this->assertFalse($this->getLogger()->hasInfo('Successfully Created Metafield for Product: {dataSourceProductId}'));
        $this->assertTrue($this->getLogger()->hasInfo('Updated Metafield data in Product for ID: {dataSourceProductId}'));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, SKU: ABC-123 | Found {resultCount} Shopify Products to publish'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductPublish('9876543210', ['published' => true])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Changed Product Availability for Product ID: {dataSourceProductId} to {productStatus}'));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, SKU: ~ | Found {resultCount} Shopify Products to unpublish'));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, SKU: ~ | No Shopify Products to unpublish'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'mw_marketing', 'key' => 'is_on_sale'])[0])));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldCreate('9876543210', [])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Updated Product {dataSourceProductId} Is On Sale Status to {formattedProductIsOnSaleStatus}'));
        $this->assertTrue($this->getLogger()->hasInfo('END PRODUCT UPDATE PROCESS'));

        $this->assertEquals('START PRODUCT UPDATE PROCESS', $this->getLogger()->records[0]['message']);

        $this->assertEquals('Found {resultCount} Products to update', $this->getLogger()->records[1]['message']);
        $this->assertEquals(1, $this->getLogger()->records[1]['context']['resultCount']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProduct('9876543210', ['fields' => 'tags'])[0]), $this->getLogger()->records[2]['message']);
        $this->assertSame([
            'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        ], $this->getLogger()->records[2]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion),
            ],
        ], $this->getLogger()->records[2]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProduct('9876543210', ['fields' => 'tags'])[0]), $this->getLogger()->records[3]['message']);
        $this->assertSame([
            'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        ], $this->getLogger()->records[3]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'product' => $this->getResponseJsonShopifyProduct('9876543210', $dataSourceApiVersion),
            ],
        ], $this->getLogger()->records[3]['context']['response']);

        $this->assertEquals('Updated Metafield data in Product for ID: {dataSourceProductId}', $this->getLogger()->records[4]['message']);
        $this->assertEquals('9876543210', $this->getLogger()->records[4]['context']['dataSourceProductId']);
        $this->assertEquals([], $this->getLogger()->records[4]['context']['dataSourceUpdateProductTagsRequest']);
        $this->assertEquals([], $this->getLogger()->records[4]['context']['dataSourceMetafieldsMinimumWebStockAvailabilityRequest']);
        $this->assertEquals([], $this->getLogger()->records[4]['context']['dataSourceProductMinWebStockAvailabilityMetaFieldCreateRequest']);

        $this->assertEquals('[1/1] Product ID: 9876543210, SKU: ABC-123 | Found {resultCount} Shopify Products to publish', $this->getLogger()->records[5]['message']);
        $this->assertEquals(1, $this->getLogger()->records[5]['context']['resultCount']);

        $this->assertEquals('Changed Product Availability for Product ID: {dataSourceProductId} to {productStatus}', $this->getLogger()->records[6]['message']);
        $this->assertEquals('9876543210', $this->getLogger()->records[6]['context']['dataSourceProductId']);
        $this->assertEquals('Available', $this->getLogger()->records[6]['context']['productStatus']);
        $this->assertEquals([], $this->getLogger()->records[6]['context']['dataSourceProductPublishRequest']);

        $this->assertEquals('[1/1] Product ID: 9876543210, SKU: ~ | Found {resultCount} Shopify Products to unpublish', $this->getLogger()->records[7]['message']);
        $this->assertEquals(0, $this->getLogger()->records[7]['context']['resultCount']);

        $this->assertEquals('[1/1] Product ID: 9876543210, SKU: ~ | No Shopify Products to unpublish', $this->getLogger()->records[8]['message']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductMetafield(null, '9876543210', ['namespace' => 'mw_marketing', 'key' => 'is_on_sale'])[0]), $this->getLogger()->records[9]['message']);
        $this->assertSame([
            'perPage' => 50,
            'endCursor' => null,
            'id' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
        ], $this->getLogger()->records[9]['context']['request']['variables']);
        $this->assertSame([
            'data' => [
                'product' => [
                    'metafields' => [
                        'nodes' => [],
                    ],
                ],
            ],
        ], $this->getLogger()->records[9]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductMetafieldCreate('9876543210', [])[0]), $this->getLogger()->records[10]['message']);
        $this->assertSame([
            'metafields' => [
                0 => [
                    'ownerId' => $this->generateShopifyGraphQLIdentifier('9876543210', 'Product'),
                    'namespace' => 'mw_marketing',
                    'key' => 'is_on_sale',
                    'value' => 'true',  // SOU-77
                    'type' => 'boolean',
                ],
            ],
        ], $this->getLogger()->records[10]['context']['request']['variables']);
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
        ], $this->getLogger()->records[10]['context']['response']);

        $this->assertEquals('Updated Product {dataSourceProductId} Is On Sale Status to {formattedProductIsOnSaleStatus}', $this->getLogger()->records[11]['message']);
        $this->assertEquals('9876543210', $this->getLogger()->records[11]['context']['dataSourceProductId']);
        $this->assertEquals('true', $this->getLogger()->records[11]['context']['formattedProductIsOnSaleStatus']);
        $this->assertEquals([
            963 => [
                'price' => 123.00,
                'compare_at_price' => 123.45,
            ],
        ], $this->getLogger()->records[11]['context']['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_variantPriceRequests']);
        $this->assertEquals([
            963 => true,
        ], $this->getLogger()->records[11]['context']['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_checkPrices']);
        $this->assertEquals([
            '' => true,
        ], $this->getLogger()->records[11]['context']['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_isOnSale']);
        $this->assertEquals([
            '' => [
                'namespace' => 'mw_marketing',
                'key' => 'is_on_sale',
                'value' => 'true',
                'type' => 'boolean',
            ],
        ], $this->getLogger()->records[11]['context']['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_requests']);
        $this->assertEquals([
            '' => $this->normalizeMiddlewareIdInNodes([
                'id' => $this->generateShopifyGraphQLIdentifier('111222333444', 'Metafield'),
                'value' => 42,
            ]),
        ], $this->getLogger()->records[11]['context']['MetafieldIsOnSaleSubscriber_updateProductIsOnSaleStatusFromProductUpdate_responses']);

        $this->assertEquals('END PRODUCT UPDATE PROCESS', $this->getLogger()->records[12]['message']);
    }

    protected function configureTask(BaseTask $task, $repositoryResults = null, $dataSourceApiResults = null, $dataDestinationApiResults = null, $dataSourceApiConnector = null, $dataDestinationApiConnector = null, $dataSourceHttpClient = null, $dataDestinationHttpClient = null, $objectManager = null, $doctrine = null, $options = [])
    {
        $options['fqcn']['order']['configured_middleware'] = AppOrderMiddleware::class;
        $options['fqcn']['product']['configured_middleware'] = AppProductMiddleware::class;
        $options['fqcn']['product']['data_source'] = AppProductShopifySource::class;
        $options['fqcn']['variant']['configured_middleware'] = AppVariantMiddleware::class;
        $options['fqcn']['variant']['data_source'] = AppVariantShopifySource::class;

        $configuredTask = parent::configureTask($task, $repositoryResults, $dataSourceApiResults, $dataDestinationApiResults, $dataSourceApiConnector, $dataDestinationApiConnector, $dataSourceHttpClient, $dataDestinationHttpClient, $objectManager, $doctrine, $options);

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
        $map = parent::getRepositoryMap();

        $map[ReportMetafieldInterface::class] = ReportMetafieldRepository::class;
        $map[ReportOrderMetafieldInterface::class] = ReportOrderMetafieldRepository::class;
        $map[ReportProductMetafieldInterface::class] = ReportProductMetafieldRepository::class;
        $map[ReportVariantMetafieldInterface::class] = ReportVariantMetafieldRepository::class;

        return $map;
    }
}
