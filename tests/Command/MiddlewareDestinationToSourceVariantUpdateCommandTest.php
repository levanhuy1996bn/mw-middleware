<?php

namespace App\Tests\Command;

use App\EcommerceMiddleware\Driver\Shopify\Data\Product\AppProductShopifySource;
use App\EcommerceMiddleware\Driver\Shopify\Data\Variant\AppVariantShopifySource;
use App\EcommerceMiddleware\ShopifyStoris\Data\Order\AppOrderMiddleware;
use App\EcommerceMiddleware\ShopifyStoris\Data\Product\AppProductMiddleware;
use App\EcommerceMiddleware\ShopifyStoris\Data\Variant\AppVariantMiddleware;
use Endertech\EcommerceMiddleware\Core\Decorator\DecoratorInspector;
use Endertech\EcommerceMiddleware\Core\Logger\MultipleLoggerDecorator;
use Endertech\EcommerceMiddleware\Core\Task\BaseTask;
use Endertech\EcommerceMiddleware\Core\Task\DestinationToSource\VariantUpdate;
use Endertech\EcommerceMiddleware\Driver\Shopify\Connector\ShopifyClient;
use Endertech\EcommerceMiddleware\Driver\Storis\Connector\StorisClient;
use Endertech\EcommerceMiddlewareEvents\Core\Data\Variant\DataMiddleware\OnStartVariantUpdateEvent;
use Endertech\EcommerceMiddlewareShopifyStorisBundle\Tests\Command\MiddlewareDestinationToSourceVariantUpdateCommandTest as BaseTest;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class MiddlewareDestinationToSourceVariantUpdateCommandTest extends BaseTest
{
    public function testExecuteWithShopifyGraphQLStorisV10ProductRetrievedUpdatesInventoryToZeroIfAvailableOnWebFalse($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        // NOTE: inventory updates are disabled by SOU-50

        $shopifyResponse = $this->getResponseJsonShopifyProductVariant('ABC-123', $dataSourceApiVersion);
        $shopifyResponse['inventoryQuantity'] = 1; // skip isAlreadyZeroInventory()

        $storisResponse = $this->getResponseJsonStorisProduct('ABC-123', $dataDestinationApiVersion);
        $storisResponse['data']['products'][0]['availableOnWeb'] = false;
        $storisResponse['data']['products'][0]['inventory']['minWebStockAvailability'] = 24;
        $storisResponse['data']['products'][0]['inventory']['locations'][0]['locationId'] = 9610; // trigger getGraphQLMutationForProductVariantInventoryLevelSet API call, also increases test locations processed
        $storisResponse['data']['products'][0]['purchaseType']['obsoleteStatus'] = 'SPC'; // one of the statuses: A, SPC, DNS

        $this->taskClass = VariantUpdate::class;

        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('middleware:destination-to-source:variant-update');

        $configuredTask = $this->configureTask($command->getTask(), null, [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0]) => $shopifyResponse,
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
            'api/Locations/Changes' => $this->getResponseJsonStorisLocations($dataDestinationApiVersion),
            'api/Products/Detail?ProductIds=ABC-123' => $storisResponse,
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

        $this->assertTrue($this->getLogger()->hasInfo('START SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForLocations()[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Retrieved Shopify Locations'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/authenticate'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Locations/Changes'));
        $this->assertTrue($this->getLogger()->hasInfo('Retrieved Storis Locations'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariants(['fields' => 'id,sku'])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Found {resultCount} Variants to process'));
        $this->assertFalse($this->getLogger()->hasInfo('No Shopify Variants found to update'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/Products/PriceAndAvailabilityStartJob'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityJobStatus'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityChunkedProducts'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/Detail'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0])));
        $this->assertTrue($this->getLogger()->hasInfo('SKU {sku}: Successful Shopify Price Update: {formattedProductInfoPrices}'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Successfully Updated Inventory Policy for Variant: {sku} to {updatedInventoryPolicy}'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, Variant ID: 1234567890, SKU: ABC-123 | Found {resultCount} Inventory Locations to process'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0])));
        $this->assertFalse($this->getLogger()->hasInfo('SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}'));
        $this->assertTrue($this->getLogger()->hasInfo('[SKIP_LOGGING] SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}'));
        $this->assertTrue($this->getLogger()->hasInfo('Processed {initialVariantsCount} Shopify Variants :: {updatedVariantCount} Records Updated, {errorVariantCount} Records in Error'));
        $this->assertTrue($this->getLogger()->hasInfo('END SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)'));

        $this->assertEquals('START SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)', $this->getLogger()->records[0]['message']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForLocations()[0]), $this->getLogger()->records[1]['message']);

        $this->assertEquals('Retrieved Shopify Locations', $this->getLogger()->records[2]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/authenticate', $this->getLogger()->records[3]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Locations/Changes', $this->getLogger()->records[4]['message']);

        $this->assertEquals('Retrieved Storis Locations', $this->getLogger()->records[5]['message']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariants(['fields' => 'id,sku'])[0]), $this->getLogger()->records[6]['message']);

        $this->assertEquals('Found {resultCount} Variants to process', $this->getLogger()->records[7]['message']);
        $this->assertEquals(1, $this->getLogger()->records[7]['context']['resultCount']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/Products/PriceAndAvailabilityStartJob', $this->getLogger()->records[8]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityJobStatus', $this->getLogger()->records[9]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityChunkedProducts', $this->getLogger()->records[10]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/Detail', $this->getLogger()->records[11]['message']);
        $this->assertSame([
            'ProductIds' => 'ABC-123',
            'LocationId' => null,
        ], $this->getLogger()->records[11]['context']['params']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0]), $this->getLogger()->records[12]['message']);

        $this->assertEquals('SKU {sku}: Successful Shopify Price Update: {formattedProductInfoPrices}', $this->getLogger()->records[13]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[13]['context']['sku']);
        $this->assertEquals('{"price":123.45,"compare_at_price":123.45}', $this->getLogger()->records[13]['context']['formattedProductInfoPrices']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0]), $this->getLogger()->records[14]['message']);

        $this->assertEquals('Successfully Updated Inventory Policy for Variant: {sku} to {updatedInventoryPolicy}', $this->getLogger()->records[15]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[15]['context']['sku']);
        $this->assertEquals('continue', $this->getLogger()->records[15]['context']['updatedInventoryPolicy']);
        $this->assertEquals([
            'inventory_policy' => 'continue',
        ], $this->getLogger()->records[15]['context']['InventorySubscriber_updateShopifyProductVariantInventoryPolicy_updateInventoryPolicyRequest']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0]), $this->getLogger()->records[16]['message']);

        $this->assertEquals('[1/1] Product ID: 9876543210, Variant ID: 1234567890, SKU: ABC-123 | Found {resultCount} Inventory Locations to process', $this->getLogger()->records[17]['message']);
        $this->assertEquals(3, $this->getLogger()->records[17]['context']['resultCount']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0]), $this->getLogger()->records[18]['message']);
        $this->assertEquals('[SKIP_LOGGING] SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}', $this->getLogger()->records[18]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[18]['context']['sku']);
        $this->assertEquals(0, $this->getLogger()->records[18]['context']['formattedAvailableInventory']);
        $this->assertEquals('TestLoc321', $this->getLogger()->records[18]['context']['locationName']);
        $this->assertEquals(true, $this->getLogger()->records[18]['context']['updateToZeroInventory']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0]), $this->getLogger()->records[19]['message']);
        $this->assertEquals('[SKIP_LOGGING] SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}', $this->getLogger()->records[19]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[19]['context']['sku']);
        $this->assertEquals(0, $this->getLogger()->records[19]['context']['formattedAvailableInventory']);
        $this->assertEquals('TestLoc654', $this->getLogger()->records[19]['context']['locationName']);
        $this->assertEquals(true, $this->getLogger()->records[19]['context']['updateToZeroInventory']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0]), $this->getLogger()->records[20]['message']);
        $this->assertEquals('[SKIP_LOGGING] SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}', $this->getLogger()->records[20]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[20]['context']['sku']);
        $this->assertEquals(0, $this->getLogger()->records[20]['context']['formattedAvailableInventory']);
        $this->assertEquals('MiddlewareIndex 9610', $this->getLogger()->records[20]['context']['locationName']);
        $this->assertEquals(true, $this->getLogger()->records[20]['context']['updateToZeroInventory']);

        $this->assertEquals('Processed {initialVariantsCount} Shopify Variants :: {updatedVariantCount} Records Updated, {errorVariantCount} Records in Error', $this->getLogger()->records[21]['message']);
        $this->assertEquals(1, $this->getLogger()->records[21]['context']['initialVariantsCount']);
        $this->assertEquals(1, $this->getLogger()->records[21]['context']['updatedVariantCount']);
        $this->assertEquals(0, $this->getLogger()->records[21]['context']['errorVariantCount']);

        $this->assertEquals('END SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)', $this->getLogger()->records[22]['message']);
    }

    public function testExecuteWithShopifyGraphQLStorisV10ProductRetrievedUpdatesInventoryWithValueIfAvailableOnWebTrue($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        // NOTE: inventory updates are disabled by SOU-50

        $shopifyResponse = $this->getResponseJsonShopifyProductVariant('ABC-123', $dataSourceApiVersion);
        $shopifyResponse['inventoryQuantity'] = 1; // skip isAlreadyZeroInventory()

        $storisResponse = $this->getResponseJsonStorisProduct('ABC-123', $dataDestinationApiVersion);
        $storisResponse['data']['products'][0]['availableOnWeb'] = true;
        $storisResponse['data']['products'][0]['inventory']['minWebStockAvailability'] = 24;
        $storisResponse['data']['products'][0]['inventory']['locations'][0]['locationId'] = 9610; // trigger getGraphQLMutationForProductVariantInventoryLevelSet API call, also increases test locations processed
        $storisResponse['data']['products'][0]['purchaseType']['obsoleteStatus'] = 'SPC'; // one of the statuses: A, SPC, DNS

        $this->taskClass = VariantUpdate::class;

        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('middleware:destination-to-source:variant-update');

        $configuredTask = $this->configureTask($command->getTask(), null, [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0]) => $shopifyResponse,
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
            'api/Locations/Changes' => $this->getResponseJsonStorisLocations($dataDestinationApiVersion),
            'api/Products/Detail?ProductIds=ABC-123' => $storisResponse,
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

        $this->assertTrue($this->getLogger()->hasInfo('START SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForLocations()[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Retrieved Shopify Locations'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/authenticate'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Locations/Changes'));
        $this->assertTrue($this->getLogger()->hasInfo('Retrieved Storis Locations'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariants(['fields' => 'id,sku'])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Found {resultCount} Variants to process'));
        $this->assertFalse($this->getLogger()->hasInfo('No Shopify Variants found to update'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/Products/PriceAndAvailabilityStartJob'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityJobStatus'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityChunkedProducts'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/Detail'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0])));
        $this->assertTrue($this->getLogger()->hasInfo('SKU {sku}: Successful Shopify Price Update: {formattedProductInfoPrices}'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Successfully Updated Inventory Policy for Variant: {sku} to {updatedInventoryPolicy}'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, Variant ID: 1234567890, SKU: ABC-123 | Found {resultCount} Inventory Locations to process'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0])));
        $this->assertTrue($this->getLogger()->hasInfo('SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}'));
        $this->assertTrue($this->getLogger()->hasInfo('[SKIP_LOGGING] SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}'));
        $this->assertTrue($this->getLogger()->hasInfo('Processed {initialVariantsCount} Shopify Variants :: {updatedVariantCount} Records Updated, {errorVariantCount} Records in Error'));
        $this->assertTrue($this->getLogger()->hasInfo('END SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)'));

        $this->assertEquals('START SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)', $this->getLogger()->records[0]['message']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForLocations()[0]), $this->getLogger()->records[1]['message']);

        $this->assertEquals('Retrieved Shopify Locations', $this->getLogger()->records[2]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/authenticate', $this->getLogger()->records[3]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Locations/Changes', $this->getLogger()->records[4]['message']);

        $this->assertEquals('Retrieved Storis Locations', $this->getLogger()->records[5]['message']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariants(['fields' => 'id,sku'])[0]), $this->getLogger()->records[6]['message']);

        $this->assertEquals('Found {resultCount} Variants to process', $this->getLogger()->records[7]['message']);
        $this->assertEquals(1, $this->getLogger()->records[7]['context']['resultCount']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/Products/PriceAndAvailabilityStartJob', $this->getLogger()->records[8]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityJobStatus', $this->getLogger()->records[9]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityChunkedProducts', $this->getLogger()->records[10]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/Detail', $this->getLogger()->records[11]['message']);
        $this->assertSame([
            'ProductIds' => 'ABC-123',
            'LocationId' => null,
        ], $this->getLogger()->records[11]['context']['params']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0]), $this->getLogger()->records[12]['message']);

        $this->assertEquals('SKU {sku}: Successful Shopify Price Update: {formattedProductInfoPrices}', $this->getLogger()->records[13]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[13]['context']['sku']);
        $this->assertEquals('{"price":123.45,"compare_at_price":123.45}', $this->getLogger()->records[13]['context']['formattedProductInfoPrices']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0]), $this->getLogger()->records[14]['message']);

        $this->assertEquals('Successfully Updated Inventory Policy for Variant: {sku} to {updatedInventoryPolicy}', $this->getLogger()->records[15]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[15]['context']['sku']);
        $this->assertEquals('continue', $this->getLogger()->records[15]['context']['updatedInventoryPolicy']);
        $this->assertEquals([
            'inventory_policy' => 'continue',
        ], $this->getLogger()->records[15]['context']['InventorySubscriber_updateShopifyProductVariantInventoryPolicy_updateInventoryPolicyRequest']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0]), $this->getLogger()->records[16]['message']);
        $this->assertEquals('[1/1] Product ID: 9876543210, Variant ID: 1234567890, SKU: ABC-123 | Found {resultCount} Inventory Locations to process', $this->getLogger()->records[16]['message']);
        $this->assertEquals(3, $this->getLogger()->records[16]['context']['resultCount']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0]), $this->getLogger()->records[17]['message']);
        $this->assertEquals('[SKIP_LOGGING] SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}', $this->getLogger()->records[17]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[17]['context']['sku']);
        $this->assertEquals(0, $this->getLogger()->records[17]['context']['formattedAvailableInventory']);
        $this->assertEquals('TestLoc321', $this->getLogger()->records[17]['context']['locationName']);
        $this->assertArrayNotHasKey('updateToZeroInventory', $this->getLogger()->records[17]['context']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0]), $this->getLogger()->records[18]['message']);
        $this->assertEquals('SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}', $this->getLogger()->records[18]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[18]['context']['sku']);
        $this->assertEquals(45, $this->getLogger()->records[18]['context']['formattedAvailableInventory']);
        $this->assertEquals('TestLoc654', $this->getLogger()->records[18]['context']['locationName']);
        $this->assertArrayNotHasKey('updateToZeroInventory', $this->getLogger()->records[18]['context']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0]), $this->getLogger()->records[19]['message']);
        $this->assertEquals('SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}', $this->getLogger()->records[19]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[19]['context']['sku']);
        $this->assertEquals(50, $this->getLogger()->records[19]['context']['formattedAvailableInventory']);
        $this->assertEquals('MiddlewareIndex 9610', $this->getLogger()->records[19]['context']['locationName']);
        $this->assertArrayNotHasKey('updateToZeroInventory', $this->getLogger()->records[19]['context']);

        $this->assertEquals('Processed {initialVariantsCount} Shopify Variants :: {updatedVariantCount} Records Updated, {errorVariantCount} Records in Error', $this->getLogger()->records[20]['message']);
        $this->assertEquals(1, $this->getLogger()->records[20]['context']['initialVariantsCount']);
        $this->assertEquals(1, $this->getLogger()->records[20]['context']['updatedVariantCount']);
        $this->assertEquals(0, $this->getLogger()->records[20]['context']['errorVariantCount']);

        $this->assertEquals('END SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)', $this->getLogger()->records[21]['message']);
    }

    public function testExecuteWithShopifyGraphQLStorisV10ProductRetrievedUpdatesInventoryPolicyToContinue($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        // NOTE: inventory updates are disabled by SOU-50

        $shopifyResponse = $this->getResponseJsonShopifyProductVariant('ABC-123', $dataSourceApiVersion);
        $shopifyResponse['inventoryQuantity'] = 1; // skip isAlreadyZeroInventory()

        $storisResponse = $this->getResponseJsonStorisProduct('ABC-123', $dataDestinationApiVersion);
        $storisResponse['data']['products'][0]['availableOnWeb'] = true;
        $storisResponse['data']['products'][0]['inventory']['minWebStockAvailability'] = 24;
        $storisResponse['data']['products'][0]['inventory']['locations'][0]['locationId'] = 9610; // trigger getGraphQLMutationForProductVariantInventoryLevelSet API call, also increases test locations processed
        $storisResponse['data']['products'][0]['purchaseType']['obsoleteStatus'] = 'SPC'; // one of the statuses: A, SPC, DNS

        $this->taskClass = VariantUpdate::class;

        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('middleware:destination-to-source:variant-update');

        $configuredTask = $this->configureTask($command->getTask(), null, [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0]) => $shopifyResponse,
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
            'api/Locations/Changes' => $this->getResponseJsonStorisLocations($dataDestinationApiVersion),
            'api/Products/Detail?ProductIds=ABC-123' => $storisResponse,
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

        $this->assertTrue($this->getLogger()->hasInfo('START SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForLocations()[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Retrieved Shopify Locations'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/authenticate'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Locations/Changes'));
        $this->assertTrue($this->getLogger()->hasInfo('Retrieved Storis Locations'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariants(['fields' => 'id,sku'])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Found {resultCount} Variants to process'));
        $this->assertFalse($this->getLogger()->hasInfo('No Shopify Variants found to update'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/Products/PriceAndAvailabilityStartJob'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityJobStatus'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityChunkedProducts'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/Detail'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0])));
        $this->assertTrue($this->getLogger()->hasInfo('SKU {sku}: Successful Shopify Price Update: {formattedProductInfoPrices}'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Successfully Updated Inventory Policy for Variant: {sku} to {updatedInventoryPolicy}'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, Variant ID: 1234567890, SKU: ABC-123 | Found {resultCount} Inventory Locations to process'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0])));
        $this->assertTrue($this->getLogger()->hasInfo('SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}'));
        $this->assertTrue($this->getLogger()->hasInfo('[SKIP_LOGGING] SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}'));
        $this->assertTrue($this->getLogger()->hasInfo('Processed {initialVariantsCount} Shopify Variants :: {updatedVariantCount} Records Updated, {errorVariantCount} Records in Error'));
        $this->assertTrue($this->getLogger()->hasInfo('END SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)'));

        $this->assertEquals('START SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)', $this->getLogger()->records[0]['message']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForLocations()[0]), $this->getLogger()->records[1]['message']);

        $this->assertEquals('Retrieved Shopify Locations', $this->getLogger()->records[2]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/authenticate', $this->getLogger()->records[3]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Locations/Changes', $this->getLogger()->records[4]['message']);

        $this->assertEquals('Retrieved Storis Locations', $this->getLogger()->records[5]['message']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariants(['fields' => 'id,sku'])[0]), $this->getLogger()->records[6]['message']);

        $this->assertEquals('Found {resultCount} Variants to process', $this->getLogger()->records[7]['message']);
        $this->assertEquals(1, $this->getLogger()->records[7]['context']['resultCount']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/Products/PriceAndAvailabilityStartJob', $this->getLogger()->records[8]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityJobStatus', $this->getLogger()->records[9]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityChunkedProducts', $this->getLogger()->records[10]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/Detail', $this->getLogger()->records[11]['message']);
        $this->assertSame([
            'ProductIds' => 'ABC-123',
            'LocationId' => null,
        ], $this->getLogger()->records[11]['context']['params']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0]), $this->getLogger()->records[12]['message']);

        $this->assertEquals('SKU {sku}: Successful Shopify Price Update: {formattedProductInfoPrices}', $this->getLogger()->records[13]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[13]['context']['sku']);
        $this->assertEquals('{"price":123.45,"compare_at_price":123.45}', $this->getLogger()->records[13]['context']['formattedProductInfoPrices']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0]), $this->getLogger()->records[14]['message']);

        $this->assertEquals('Successfully Updated Inventory Policy for Variant: {sku} to {updatedInventoryPolicy}', $this->getLogger()->records[15]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[15]['context']['sku']);
        $this->assertEquals('continue', $this->getLogger()->records[15]['context']['updatedInventoryPolicy']);
        $this->assertEquals([
            'inventory_policy' => 'continue',
        ], $this->getLogger()->records[15]['context']['InventorySubscriber_updateShopifyProductVariantInventoryPolicy_updateInventoryPolicyRequest']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0]), $this->getLogger()->records[16]['message']);
        $this->assertEquals('[1/1] Product ID: 9876543210, Variant ID: 1234567890, SKU: ABC-123 | Found {resultCount} Inventory Locations to process', $this->getLogger()->records[16]['message']);
        $this->assertEquals(3, $this->getLogger()->records[16]['context']['resultCount']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0]), $this->getLogger()->records[17]['message']);
        $this->assertEquals('[SKIP_LOGGING] SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}', $this->getLogger()->records[17]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[17]['context']['sku']);
        $this->assertEquals(0, $this->getLogger()->records[17]['context']['formattedAvailableInventory']);
        $this->assertEquals('TestLoc321', $this->getLogger()->records[17]['context']['locationName']);
        $this->assertArrayNotHasKey('updateToZeroInventory', $this->getLogger()->records[17]['context']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0]), $this->getLogger()->records[18]['message']);
        $this->assertEquals('SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}', $this->getLogger()->records[18]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[18]['context']['sku']);
        $this->assertEquals(45, $this->getLogger()->records[18]['context']['formattedAvailableInventory']);
        $this->assertEquals('TestLoc654', $this->getLogger()->records[18]['context']['locationName']);
        $this->assertArrayNotHasKey('updateToZeroInventory', $this->getLogger()->records[18]['context']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0]), $this->getLogger()->records[19]['message']);
        $this->assertEquals('SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}', $this->getLogger()->records[19]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[19]['context']['sku']);
        $this->assertEquals(50, $this->getLogger()->records[19]['context']['formattedAvailableInventory']);
        $this->assertEquals('MiddlewareIndex 9610', $this->getLogger()->records[19]['context']['locationName']);
        $this->assertArrayNotHasKey('updateToZeroInventory', $this->getLogger()->records[19]['context']);

        $this->assertEquals('Processed {initialVariantsCount} Shopify Variants :: {updatedVariantCount} Records Updated, {errorVariantCount} Records in Error', $this->getLogger()->records[20]['message']);
        $this->assertEquals(1, $this->getLogger()->records[20]['context']['initialVariantsCount']);
        $this->assertEquals(1, $this->getLogger()->records[20]['context']['updatedVariantCount']);
        $this->assertEquals(0, $this->getLogger()->records[20]['context']['errorVariantCount']);

        $this->assertEquals('END SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)', $this->getLogger()->records[21]['message']);
    }

    public function testExecuteWithShopifyGraphQLStorisV10ProductRetrievedUpdatesInventoryPolicyToDeny($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        // NOTE: inventory updates are disabled by SOU-50

        $shopifyResponse = $this->getResponseJsonShopifyProductVariant('ABC-123', $dataSourceApiVersion);
        $shopifyResponse['inventoryQuantity'] = 1; // skip isAlreadyZeroInventory()

        $storisResponse = $this->getResponseJsonStorisProduct('ABC-123', $dataDestinationApiVersion);
        $storisResponse['data']['products'][0]['availableOnWeb'] = true;
        $storisResponse['data']['products'][0]['inventory']['minWebStockAvailability'] = 24;
        $storisResponse['data']['products'][0]['inventory']['locations'][0]['locationId'] = 9610; // trigger getGraphQLMutationForProductVariantInventoryLevelSet API call, also increases test locations processed
        $storisResponse['data']['products'][0]['purchaseType']['obsoleteStatus'] = 'UNKNOWN'; // NOT one of the statuses: A, SPC, DNS

        $this->taskClass = VariantUpdate::class;

        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('middleware:destination-to-source:variant-update');

        $configuredTask = $this->configureTask($command->getTask(), null, [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0]) => $shopifyResponse,
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
            'api/Locations/Changes' => $this->getResponseJsonStorisLocations($dataDestinationApiVersion),
            'api/Products/Detail?ProductIds=ABC-123' => $storisResponse,
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

        $this->assertTrue($this->getLogger()->hasInfo('START SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForLocations()[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Retrieved Shopify Locations'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/authenticate'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Locations/Changes'));
        $this->assertTrue($this->getLogger()->hasInfo('Retrieved Storis Locations'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariants(['fields' => 'id,sku'])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Found {resultCount} Variants to process'));
        $this->assertFalse($this->getLogger()->hasInfo('No Shopify Variants found to update'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/Products/PriceAndAvailabilityStartJob'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityJobStatus'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityChunkedProducts'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/Detail'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0])));
        $this->assertTrue($this->getLogger()->hasInfo('SKU {sku}: Successful Shopify Price Update: {formattedProductInfoPrices}'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Successfully Updated Inventory Policy for Variant: {sku} to {updatedInventoryPolicy}'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, Variant ID: 1234567890, SKU: ABC-123 | Found {resultCount} Inventory Locations to process'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0])));
        $this->assertTrue($this->getLogger()->hasInfo('SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}'));
        $this->assertTrue($this->getLogger()->hasInfo('[SKIP_LOGGING] SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}'));
        $this->assertTrue($this->getLogger()->hasInfo('Processed {initialVariantsCount} Shopify Variants :: {updatedVariantCount} Records Updated, {errorVariantCount} Records in Error'));
        $this->assertTrue($this->getLogger()->hasInfo('END SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)'));

        $this->assertEquals('START SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)', $this->getLogger()->records[0]['message']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForLocations()[0]), $this->getLogger()->records[1]['message']);

        $this->assertEquals('Retrieved Shopify Locations', $this->getLogger()->records[2]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/authenticate', $this->getLogger()->records[3]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Locations/Changes', $this->getLogger()->records[4]['message']);

        $this->assertEquals('Retrieved Storis Locations', $this->getLogger()->records[5]['message']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariants(['fields' => 'id,sku'])[0]), $this->getLogger()->records[6]['message']);

        $this->assertEquals('Found {resultCount} Variants to process', $this->getLogger()->records[7]['message']);
        $this->assertEquals(1, $this->getLogger()->records[7]['context']['resultCount']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/Products/PriceAndAvailabilityStartJob', $this->getLogger()->records[8]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityJobStatus', $this->getLogger()->records[9]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityChunkedProducts', $this->getLogger()->records[10]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/Detail', $this->getLogger()->records[11]['message']);
        $this->assertSame([
            'ProductIds' => 'ABC-123',
            'LocationId' => null,
        ], $this->getLogger()->records[11]['context']['params']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0]), $this->getLogger()->records[12]['message']);

        $this->assertEquals('SKU {sku}: Successful Shopify Price Update: {formattedProductInfoPrices}', $this->getLogger()->records[13]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[13]['context']['sku']);
        $this->assertEquals('{"price":123.45,"compare_at_price":123.45}', $this->getLogger()->records[13]['context']['formattedProductInfoPrices']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0]), $this->getLogger()->records[14]['message']);

        $this->assertEquals('Successfully Updated Inventory Policy for Variant: {sku} to {updatedInventoryPolicy}', $this->getLogger()->records[15]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[15]['context']['sku']);
        $this->assertEquals('deny', $this->getLogger()->records[15]['context']['updatedInventoryPolicy']);
        $this->assertEquals([
            'inventory_policy' => 'deny',
        ], $this->getLogger()->records[15]['context']['InventorySubscriber_updateShopifyProductVariantInventoryPolicy_updateInventoryPolicyRequest']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0]), $this->getLogger()->records[16]['message']);
        $this->assertEquals('[1/1] Product ID: 9876543210, Variant ID: 1234567890, SKU: ABC-123 | Found {resultCount} Inventory Locations to process', $this->getLogger()->records[16]['message']);
        $this->assertEquals(3, $this->getLogger()->records[16]['context']['resultCount']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0]), $this->getLogger()->records[17]['message']);
        $this->assertEquals('[SKIP_LOGGING] SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}', $this->getLogger()->records[17]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[17]['context']['sku']);
        $this->assertEquals(0, $this->getLogger()->records[17]['context']['formattedAvailableInventory']);
        $this->assertEquals('TestLoc321', $this->getLogger()->records[17]['context']['locationName']);
        $this->assertArrayNotHasKey('updateToZeroInventory', $this->getLogger()->records[17]['context']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0]), $this->getLogger()->records[18]['message']);
        $this->assertEquals('SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}', $this->getLogger()->records[18]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[18]['context']['sku']);
        $this->assertEquals(45, $this->getLogger()->records[18]['context']['formattedAvailableInventory']);
        $this->assertEquals('TestLoc654', $this->getLogger()->records[18]['context']['locationName']);
        $this->assertArrayNotHasKey('updateToZeroInventory', $this->getLogger()->records[18]['context']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0]), $this->getLogger()->records[19]['message']);
        $this->assertEquals('SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}', $this->getLogger()->records[19]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[19]['context']['sku']);
        $this->assertEquals(50, $this->getLogger()->records[19]['context']['formattedAvailableInventory']);
        $this->assertEquals('MiddlewareIndex 9610', $this->getLogger()->records[19]['context']['locationName']);
        $this->assertArrayNotHasKey('updateToZeroInventory', $this->getLogger()->records[19]['context']);

        $this->assertEquals('Processed {initialVariantsCount} Shopify Variants :: {updatedVariantCount} Records Updated, {errorVariantCount} Records in Error', $this->getLogger()->records[20]['message']);
        $this->assertEquals(1, $this->getLogger()->records[20]['context']['initialVariantsCount']);
        $this->assertEquals(1, $this->getLogger()->records[20]['context']['updatedVariantCount']);
        $this->assertEquals(0, $this->getLogger()->records[20]['context']['errorVariantCount']);

        $this->assertEquals('END SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)', $this->getLogger()->records[21]['message']);
    }

    public function testExecuteWithShopifyGraphQLStorisV10ProductRetrievedUpdatesInventoryPolicyWithSomeSkippedRequests($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        // NOTE: inventory updates are disabled by SOU-50

        $shopifyResponse = $this->getResponseJsonShopifyProductVariant('ABC-123', $dataSourceApiVersion);
        $shopifyResponse['inventoryQuantity'] = 1; // skip isAlreadyZeroInventory()
        $shopifyResponse['inventoryPolicy'] = 'continue'; // skip InventorySubscriber API request

        $storisResponse = $this->getResponseJsonStorisProduct('ABC-123', $dataDestinationApiVersion);
        $storisResponse['data']['products'][0]['availableOnWeb'] = true;
        $storisResponse['data']['products'][0]['inventory']['minWebStockAvailability'] = 24;
        $storisResponse['data']['products'][0]['inventory']['locations'][0]['locationId'] = 9610; // trigger getGraphQLMutationForProductVariantInventoryLevelSet API call, also increases test locations processed
        $storisResponse['data']['products'][0]['purchaseType']['obsoleteStatus'] = 'SPC'; // one of the statuses: A, SPC, DNS

        $this->taskClass = VariantUpdate::class;

        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('middleware:destination-to-source:variant-update');

        $configuredTask = $this->configureTask($command->getTask(), null, [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0]) => $shopifyResponse,
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0]) => $shopifyResponse,
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
            'api/Locations/Changes' => $this->getResponseJsonStorisLocations($dataDestinationApiVersion),
            'api/Products/Detail?ProductIds=ABC-123' => $storisResponse,
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

        $this->assertTrue($this->getLogger()->hasInfo('START SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForLocations()[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Retrieved Shopify Locations'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/authenticate'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Locations/Changes'));
        $this->assertTrue($this->getLogger()->hasInfo('Retrieved Storis Locations'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariants(['fields' => 'id,sku'])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Found {resultCount} Variants to process'));
        $this->assertFalse($this->getLogger()->hasInfo('No Shopify Variants found to update'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/Products/PriceAndAvailabilityStartJob'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityJobStatus'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityChunkedProducts'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/Detail'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0])));
        $this->assertTrue($this->getLogger()->hasInfo('SKU {sku}: Successful Shopify Price Update: {formattedProductInfoPrices}'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Successfully Updated Inventory Policy for Variant: {sku} to {updatedInventoryPolicy}'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, Variant ID: 1234567890, SKU: ABC-123 | Found {resultCount} Inventory Locations to process'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0])));
        $this->assertTrue($this->getLogger()->hasInfo('SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}'));
        $this->assertTrue($this->getLogger()->hasInfo('[SKIP_LOGGING] SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}'));
        $this->assertTrue($this->getLogger()->hasInfo('Processed {initialVariantsCount} Shopify Variants :: {updatedVariantCount} Records Updated, {errorVariantCount} Records in Error'));
        $this->assertTrue($this->getLogger()->hasInfo('END SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)'));

        $this->assertEquals('START SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)', $this->getLogger()->records[0]['message']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForLocations()[0]), $this->getLogger()->records[1]['message']);

        $this->assertEquals('Retrieved Shopify Locations', $this->getLogger()->records[2]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/authenticate', $this->getLogger()->records[3]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Locations/Changes', $this->getLogger()->records[4]['message']);

        $this->assertEquals('Retrieved Storis Locations', $this->getLogger()->records[5]['message']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariants(['fields' => 'id,sku'])[0]), $this->getLogger()->records[6]['message']);

        $this->assertEquals('Found {resultCount} Variants to process', $this->getLogger()->records[7]['message']);
        $this->assertEquals(1, $this->getLogger()->records[7]['context']['resultCount']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/Products/PriceAndAvailabilityStartJob', $this->getLogger()->records[8]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityJobStatus', $this->getLogger()->records[9]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityChunkedProducts', $this->getLogger()->records[10]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/Detail', $this->getLogger()->records[11]['message']);
        $this->assertSame([
            'ProductIds' => 'ABC-123',
            'LocationId' => null,
        ], $this->getLogger()->records[11]['context']['params']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0]), $this->getLogger()->records[12]['message']);

        $this->assertEquals('SKU {sku}: Successful Shopify Price Update: {formattedProductInfoPrices}', $this->getLogger()->records[13]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[13]['context']['sku']);
        $this->assertEquals('{"price":123.45,"compare_at_price":123.45}', $this->getLogger()->records[13]['context']['formattedProductInfoPrices']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0]), $this->getLogger()->records[14]['message']);
        $this->assertEquals('Successfully Updated Inventory Policy for Variant: {sku} to {updatedInventoryPolicy}', $this->getLogger()->records[14]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[14]['context']['sku']);
        $this->assertEquals('continue', $this->getLogger()->records[14]['context']['updatedInventoryPolicy']);
        $this->assertEquals([
            'inventory_policy' => 'continue',
        ], $this->getLogger()->records[14]['context']['InventorySubscriber_updateShopifyProductVariantInventoryPolicy_updateInventoryPolicyRequest']);
        $this->assertEquals([
            'inventory_policy' => 'continue',
        ], $this->getLogger()->records[14]['context']['InventorySubscriber_updateShopifyProductVariantInventoryPolicy_updateInventoryPolicy_skipped']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0]), $this->getLogger()->records[15]['message']);
        $this->assertEquals('[1/1] Product ID: 9876543210, Variant ID: 1234567890, SKU: ABC-123 | Found {resultCount} Inventory Locations to process', $this->getLogger()->records[15]['message']);
        $this->assertEquals(3, $this->getLogger()->records[15]['context']['resultCount']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0]), $this->getLogger()->records[16]['message']);
        $this->assertEquals('[SKIP_LOGGING] SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}', $this->getLogger()->records[16]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[16]['context']['sku']);
        $this->assertEquals(0, $this->getLogger()->records[16]['context']['formattedAvailableInventory']);
        $this->assertEquals('TestLoc321', $this->getLogger()->records[16]['context']['locationName']);
        $this->assertArrayNotHasKey('updateToZeroInventory', $this->getLogger()->records[16]['context']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0]), $this->getLogger()->records[17]['message']);
        $this->assertEquals('SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}', $this->getLogger()->records[17]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[17]['context']['sku']);
        $this->assertEquals(45, $this->getLogger()->records[17]['context']['formattedAvailableInventory']);
        $this->assertEquals('TestLoc654', $this->getLogger()->records[17]['context']['locationName']);
        $this->assertArrayNotHasKey('updateToZeroInventory', $this->getLogger()->records[17]['context']);

        //$this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0]), $this->getLogger()->records[18]['message']);
        $this->assertEquals('SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}', $this->getLogger()->records[18]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[18]['context']['sku']);
        $this->assertEquals(50, $this->getLogger()->records[18]['context']['formattedAvailableInventory']);
        $this->assertEquals('MiddlewareIndex 9610', $this->getLogger()->records[18]['context']['locationName']);
        $this->assertArrayNotHasKey('updateToZeroInventory', $this->getLogger()->records[18]['context']);

        $this->assertEquals('Processed {initialVariantsCount} Shopify Variants :: {updatedVariantCount} Records Updated, {errorVariantCount} Records in Error', $this->getLogger()->records[19]['message']);
        $this->assertEquals(1, $this->getLogger()->records[19]['context']['initialVariantsCount']);
        $this->assertEquals(1, $this->getLogger()->records[19]['context']['updatedVariantCount']);
        $this->assertEquals(0, $this->getLogger()->records[19]['context']['errorVariantCount']);

        $this->assertEquals('END SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)', $this->getLogger()->records[20]['message']);
    }

    public function testExecuteWithStorisV2ProductRetrievedUpdatesPriceOnlyWithPreventAllPriceUpdatesDisabled($dataSourceApiVersion = ShopifyClient::API_VERSION_GRAPHQL_MIN, $dataDestinationApiVersion = StorisClient::API_VERSION_V10)
    {
        // NOTE: inventory updates are disabled by SOU-50

        $shopifyResponse = $this->getResponseJsonShopifyProductVariant('ABC-123', $dataSourceApiVersion);
        $shopifyResponse['inventoryQuantity'] = 1; // skip isAlreadyZeroInventory()
        $shopifyResponse['inventoryPolicy'] = 'continue'; // skip InventorySubscriber API request

        $storisResponse = $this->getResponseJsonStorisProduct('ABC-123', $dataDestinationApiVersion);
        $storisResponse['data']['products'][0]['availableOnWeb'] = true;
        $storisResponse['data']['products'][0]['inventory']['minWebStockAvailability'] = 24;
        $storisResponse['data']['products'][0]['inventory']['locations'][0]['locationId'] = 9610; // trigger getGraphQLMutationForProductVariantInventoryLevelSet API call, also increases test locations processed
        $storisResponse['data']['products'][0]['purchaseType']['obsoleteStatus'] = 'SPC'; // one of the statuses: A, SPC, DNS

        $this->taskClass = VariantUpdate::class;

        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('middleware:destination-to-source:variant-update');

        $configuredTask = $this->configureTask($command->getTask(), null, [
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0]) => $shopifyResponse,
            '/graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0]) => $shopifyResponse,
        ], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($dataDestinationApiVersion),
            'api/Locations/Changes' => $this->getResponseJsonStorisLocations($dataDestinationApiVersion),
            'api/Products/Detail?ProductIds=ABC-123' => $storisResponse,
        ], null, null, null, null, null, null, [
            'storis_username' => 'johndoe',
            'storis_secret' => 's3cr3t',
            'api_version' => $dataDestinationApiVersion,
            'data_source_api_version' => $dataSourceApiVersion,
            'data_destination_api_version' => $dataDestinationApiVersion,
        ]);
        $eventListeners = self::getContainer()->get('event_dispatcher')->getListeners();
        foreach ($eventListeners as $k => $v) {
            if (OnStartVariantUpdateEvent::class == $k) {
                for ($i = 0; $i < count($v); ++$i) {
                    if ('onStartVariantUpdateForVariantUpdatePriceOnly' == $v[$i][1]) {
                        //$v[$i][0]->setUseVariantUpdatePriceOnlyEnabled(true);  // set with the command options below
                    }
                }
            }
        }
        $command->setTask($configuredTask);
        $command->setDecoratedLogger(new MultipleLoggerDecorator(null, $this->getLogger()));

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--quiet' => true, '-vvv' => true]); // the quiet and verbose options together allows the setUseVariantUpdatePriceOnlyEnabled to be set

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('1/1', $output);
        $this->assertStringContainsString('100%', $output);

        $this->assertTrue($this->getLogger()->hasInfoRecords(), 'There should be INFO logs');
        $this->assertFalse($this->getLogger()->hasErrorRecords(), 'There should not be ERROR logs');
        $this->assertTrue($this->getLogger()->hasDebugRecords(), 'There should be DEBUG logs');

        $this->assertTrue($this->getLogger()->hasInfo('START SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForLocations()[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Retrieved Shopify Locations'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/authenticate'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Locations/Changes'));
        $this->assertTrue($this->getLogger()->hasInfo('Retrieved Storis Locations'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariants(['fields' => 'id,sku'])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('Found {resultCount} Variants to process'));
        $this->assertFalse($this->getLogger()->hasInfo('No Shopify Variants found to update'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/Products/PriceAndAvailabilityStartJob'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityJobStatus'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityChunkedProducts'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/Detail'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0])));
        $this->assertTrue($this->getLogger()->hasInfo('SKU {sku}: Successful Shopify Price Update: {formattedProductInfoPrices}'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0])));
        $this->assertFalse($this->getLogger()->hasInfo('Successfully Updated Inventory Policy for Variant: {sku} to {updatedInventoryPolicy}'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariant('9876543210', '1234567890', ['fields' => 'inventoryQuantity'])[0])));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, Variant ID: 1234567890, SKU: ABC-123 | Found {resultCount} Inventory Locations to process'));
        $this->assertFalse($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantInventoryLevelSet('9876543210', '1234567890')[0])));
        $this->assertFalse($this->getLogger()->hasInfo('SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}'));
        $this->assertFalse($this->getLogger()->hasInfo('[SKIP_LOGGING] SKU {sku}: Successful Inventory Update: {formattedAvailableInventory}, Location Name: {locationName}'));
        $this->assertTrue($this->getLogger()->hasInfo('[1/1] Product ID: 9876543210, Variant ID: 1234567890, SKU: ABC-123 | No Inventory Locations found to update'));
        $this->assertTrue($this->getLogger()->hasInfo('Processed {initialVariantsCount} Shopify Variants :: {updatedVariantCount} Records Updated, {errorVariantCount} Records in Error'));
        $this->assertTrue($this->getLogger()->hasInfo('END SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)'));

        $this->assertEquals('START SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)', $this->getLogger()->records[0]['message']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForLocations()[0]), $this->getLogger()->records[1]['message']);

        $this->assertEquals('Retrieved Shopify Locations', $this->getLogger()->records[2]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/authenticate', $this->getLogger()->records[3]['message']);
        $this->assertSame([
        ], $this->getLogger()->records[3]['context']['params']);
        $this->assertSame([
            'token' => [
                'access_token' => 'access_token_abc123',
                'expires_in' => 1,
                'token_type' => 'Bearer',
            ],
            'success' => true,
            'message' => '',
        ], $this->getLogger()->records[3]['context']['response']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Locations/Changes', $this->getLogger()->records[4]['message']);
        $this->assertSame([
        ], $this->getLogger()->records[4]['context']['params']);
        $this->assertSame([
            'success' => true,
            'transactionId' => 'transaction-id-012',
            'message' => '',
            'data' => [
                'locations' => [
                    0 => [
                        'id' => 321,
                        'description' => 'TestLoc321',
                    ],
                    1 => [
                        'id' => 654,
                        'description' => 'TestLoc654',
                    ],
                ],
                'deletedLocations' => [
                ],
            ],
            'serverTime' => '2021-03-08T01:01:01Z',
        ], $this->getLogger()->records[4]['context']['response']);

        $this->assertEquals('Retrieved Storis Locations', $this->getLogger()->records[5]['message']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLQueryForProductVariants(['fields' => 'id,sku'])[0]), $this->getLogger()->records[6]['message']);

        $this->assertEquals('Found {resultCount} Variants to process', $this->getLogger()->records[7]['message']);
        $this->assertEquals(1, $this->getLogger()->records[7]['context']['resultCount']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: POST | URL: api/Products/PriceAndAvailabilityStartJob', $this->getLogger()->records[8]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityJobStatus', $this->getLogger()->records[9]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/PriceAndAvailabilityChunkedProducts', $this->getLogger()->records[10]['message']);

        $this->assertEquals('Executed Storis API Request - Status Code: 200 | Method: GET | URL: api/Products/Detail', $this->getLogger()->records[11]['message']);
        $this->assertSame([
            'ProductIds' => 'ABC-123',
            'LocationId' => null,
        ], $this->getLogger()->records[11]['context']['params']);
        $this->assertSame([
            'success' => true,
            'transactionId' => 'storis-transaction-id-678',
            'message' => '',
            'data' => [
                'products' => [
                    0 => [
                        'id' => 'ABC-123',
                        'description' => 'Really Great Looking Variant',
                        'availableOnWeb' => true,
                        'productBenefits' => 'Really Great Looking Variant',
                        'inventory' => [
                            'netQuantityAvailable' => 95,
                            'minWebStockAvailability' => 24,
                            'locations' => [
                                0 => [
                                    'locationId' => 9610,
                                    'quantityAvailable' => 50,
                                ],
                                1 => [
                                    'locationId' => 654,
                                    'quantityAvailable' => 45,
                                ],
                            ],
                        ],
                        'brand' => [
                            'brandDescription' => 'Really Great Looking Variant',
                        ],
                        'inventoryPrices' => [
                            0 => [
                                'currentSellingPrice' => 123.45,
                                'standardSellingPrice' => 123.45,
                                'msrp' => 678.9,
                            ],
                        ],
                        'purchaseType' => [
                            'obsoleteStatus' => 'SPC',
                        ],
                    ],
                ],
            ],
            'serverTime' => '2021-03-07T01:01:01Z',
        ], $this->getLogger()->records[11]['context']['response']);

        $this->assertEquals('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls'.$this->encodeGraphQLParams($this->getGraphQLMutationForProductVariantUpdate('9876543210', '1234567890')[0]), $this->getLogger()->records[12]['message']);

        $this->assertEquals('SKU {sku}: Successful Shopify Price Update: {formattedProductInfoPrices}', $this->getLogger()->records[13]['message']);
        $this->assertEquals('ABC-123', $this->getLogger()->records[13]['context']['sku']);
        $this->assertEquals('{"price":123.45,"compare_at_price":123.45}', $this->getLogger()->records[13]['context']['formattedProductInfoPrices']);

        $this->assertEquals('[1/1] Product ID: 9876543210, Variant ID: 1234567890, SKU: ABC-123 | Found {resultCount} Inventory Locations to process', $this->getLogger()->records[14]['message']);
        $this->assertEquals(0, $this->getLogger()->records[14]['context']['resultCount']);

        $this->assertEquals('[1/1] Product ID: 9876543210, Variant ID: 1234567890, SKU: ABC-123 | No Inventory Locations found to update', $this->getLogger()->records[15]['message']);

        $this->assertEquals('Processed {initialVariantsCount} Shopify Variants :: {updatedVariantCount} Records Updated, {errorVariantCount} Records in Error', $this->getLogger()->records[16]['message']);
        $this->assertEquals(1, $this->getLogger()->records[16]['context']['initialVariantsCount']);
        $this->assertEquals(1, $this->getLogger()->records[16]['context']['updatedVariantCount']);
        $this->assertEquals(0, $this->getLogger()->records[16]['context']['errorVariantCount']);

        $this->assertEquals('END SHOPIFY VARIANT UPDATE PROCESS (Inventory, Price and Location Tags)', $this->getLogger()->records[17]['message']);
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
}
