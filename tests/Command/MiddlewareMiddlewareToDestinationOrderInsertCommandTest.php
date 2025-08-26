<?php

namespace App\Tests\Command;

use App\EcommerceMiddleware\Driver\Shopify\Data\Product\AppProductShopifySource;
use App\EcommerceMiddleware\Driver\Shopify\Data\Variant\AppVariantShopifySource;
use App\EcommerceMiddleware\ShopifyStoris\Data\Order\AppOrderMiddleware;
use App\EcommerceMiddleware\ShopifyStoris\Data\Product\AppProductMiddleware;
use App\EcommerceMiddleware\ShopifyStoris\Data\Variant\AppVariantMiddleware;
use Endertech\EcommerceMiddleware\Core\Decorator\DecoratorInspector;
use Endertech\EcommerceMiddleware\Core\Task\BaseTask;
use Endertech\EcommerceMiddleware\Driver\Storis\Connector\StorisClient;
use Endertech\EcommerceMiddlewareShopifyStorisBundle\Tests\Command\MiddlewareMiddlewareToDestinationOrderInsertCommandTest as BaseTest;

class MiddlewareMiddlewareToDestinationOrderInsertCommandTest extends BaseTest
{
    protected function configureTask(BaseTask $task, $repositoryResults = null, $dataSourceApiResults = null, $dataDestinationApiResults = null, $dataSourceApiConnector = null, $dataDestinationApiConnector = null, $dataSourceHttpClient = null, $dataDestinationHttpClient = null, $objectManager = null, $doctrine = null, $options = [])
    {
        // @see \Endertech\EcommerceMiddleware\ShopifyStoris\Tests\Task\MiddlewareToDestination\OrderInsertTest::testStorisV2ExecuteInsertsOrdersWithANewCustomer()
        $apiVersion = $options['api_version'] ?? StorisClient::API_VERSION_V2;
        $storisCustomerId = '66778899';
        $storisOrderId = '99887766';

        $dataSourceApiResults = array_merge($dataSourceApiResults ?? [], [
            'getOrder' => $this->getResponseJsonShopifyOrder('1234567890'),
            'updateOrder' => $this->getResponseJsonShopifyOrder('1234567890'),
        ]);
        $dataDestinationApiResults = array_merge($dataDestinationApiResults ?? [], [
            'api/authenticate' => $this->getResponseJsonStorisApiAuthenticate($apiVersion),
            'api/Locations/Changes' => $this->getResponseJsonStorisLocations($apiVersion),
            'api/Customers/FindByPhone' => $this->getResponseJsonStorisFindCustomerNotFound($apiVersion),
            'api/Customers/Create' => $this->getResponseJsonStorisCreateCustomer($storisCustomerId, $apiVersion),
            'api/Orders/Submit' => $this->getResponseJsonStorisCreateOrder($storisOrderId, $storisCustomerId, $apiVersion),
            'api/Orders/Detail|POST|orderTypeCode=1&orderId='.$storisOrderId.'&customerId='.$storisCustomerId => $this->getResponseJsonStorisOrder($storisOrderId, null, $storisCustomerId, $apiVersion),
            'api/Orders/Deposit' => $this->getResponseJsonStorisApplyDepositsToOrder($storisOrderId, $apiVersion),
        ]);
        $options = array_merge($options ?? [], [
            'storis_username' => 'johndoe',
            'storis_secret' => 's3cr3t',
            'api_version' => $apiVersion,
        ]);

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

        return $configuredTask;
    }
}
