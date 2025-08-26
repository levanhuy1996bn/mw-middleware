<?php

namespace App\Tests\Command;

use App\Command\AddDiscountsToProductsCommand;
use App\Connector\MultiscountClient;
use App\EcommerceMiddleware\Driver\Shopify\Connector\AppShopifyApiConnector;
use App\EcommerceMiddleware\Driver\Shopify\Data\Order\AppOrderShopifySource;
use App\EcommerceMiddleware\Driver\Shopify\Data\Product\AppProductShopifySource;
use App\EcommerceMiddleware\Driver\Shopify\Data\Variant\AppVariantShopifySource;
use App\EcommerceMiddleware\ShopifyStoris\Data\Order\AppOrderMiddleware;
use App\EcommerceMiddleware\ShopifyStoris\Data\Product\AppProductMiddleware;
use App\EcommerceMiddleware\ShopifyStoris\Data\Variant\AppVariantMiddleware;
use App\Helper\GraphQLQueryHelper;
use Endertech\EcommerceMiddleware\Core\Decorator\DecoratorInspector;
use Endertech\EcommerceMiddleware\Core\Task\BaseTask;
use Endertech\EcommerceMiddleware\Driver\Shopify\Mock\MockMethods;
use Endertech\EcommerceMiddleware\Driver\Shopify\Mock\MockShopify;
use PHPShopify\ShopifySDK;
use Symfony\Component\Console\Tester\CommandTester;

class AddDiscountsToProductsCommandTest extends BaseTaskTest
{
    public function testExecuteAddDiscountsToProductsForDiscountAutomaticBasic()
    {
        $kernel = static::createKernel();

        $multiscountClient = new MultiscountClient($this->getHttpClient());
        $graphQLQueryHelper = new GraphQLQueryHelper();
        $shopifySdk = new ShopifySDK([
            'ShopUrl' => '',
            'AccessToken' => '',
        ]);
        $mockMethods = new MockMethods();
        $mockMethods->setLogger($this->getLogger());
        $mockShopify = new AddDiscountsToProductsCommandTestLocalMockShopify();
        $mockShopify->setMockMethods($mockMethods);
        $mockShopify->setMockData([
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getProductsQueryByCollection()) => [
                'data' => [
                    'collectionByHandle' => [
                        'products' => [
                            'nodes' => [
                                0 => [
                                    'id' => '1000000001',
                                    'vendor' => 'GreatVendor',
                                    'eligibleForDiscount' => [
                                        'value' => 'true',
                                    ],
                                ],
                            ],
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountQuery()) => [
                'data' => [
                    //'automaticDiscountNodes' => [  // MWS-642
                    'discountNodes' => [
                        'nodes' => [
                            0 => [
                                'id' => '2000000001',
                                //'automaticDiscount' => [  // MWS-642
                                'discount' => [
                                    '__typename' => 'DiscountAutomaticBasic',
                                    // ... on DiscountAutomaticBxgy
                                    // ... on DiscountAutomaticBasic
                                    // ... on DiscountAutomaticApp
                                    'startsAt' => '',
                                    'endsAt' => '',
                                    'title' => 'DiscountTitle201',
                                    'discountClass' => 'PRODUCT',
                                    // ... on DiscountAutomaticApp
                                    //'status' => 'ACTIVE',
                                    'combinesWith' => [
                                        'productDiscounts' => true,
                                    ],
                                    // ... on DiscountAutomaticBxgy
                                    /*
                                    'customerGets' => [
                                        'items' => [
                                            // ... on DiscountCollections
                                            'collections' => [
                                                'nodes' => [
                                                    0 => [
                                                        'title' => 'CollectionTitle204',
                                                        'id' => '2000000004',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                            // ... on DiscountProducts
                                            'products' => [
                                                'nodes' => [
                                                    0 => [
                                                        'id' => '2000000005',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '__typename' => 'DiscountAmount',
                                            // ... on DiscountAmount
                                            'amount' => [
                                                'amount' => '2.01',
                                                'currencyCode' => 'USD',
                                            ],
                                            // ... on DiscountOnQuantity
                                            'quantity' => [
                                                'quantity' => '202',
                                            ],
                                            'effect' => [
                                                '__typename' => 'DiscountPercentage',
                                                // ... on DiscountPercentage
                                                'percentage' => '2.03',
                                            ],
                                            // ... on DiscountPercentage
                                            'percentage' => '2.04',
                                        ],
                                    ],
                                    */
                                    'customerBuys' => [
                                        'items' => [
                                            // ... on DiscountCollections
                                            'collections' => [
                                                'nodes' => [
                                                    0 => [
                                                        'title' => 'CollectionTitle202',
                                                        'id' => '2000000002',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                            // ... on DiscountProducts
                                            'products' => [
                                                'nodes' => [
                                                    0 => [
                                                        'id' => '2000000003',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '__typename' => 'DiscountPurchaseAmount',
                                            // ... on DiscountPurchaseAmount
                                            'amount' => '2.05',
                                            // ... on DiscountQuantity
                                            'quantity' => '206',
                                        ],
                                    ],
                                    // ... on DiscountAutomaticBasic
                                    'customerGets' => [
                                        'items' => [
                                            // ... on DiscountCollections
                                            'collections' => [
                                                'nodes' => [
                                                    0 => [
                                                        'title' => 'CollectionTitle204',
                                                        'id' => '2000000004',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                            // ... on DiscountProducts
                                            'products' => [
                                                'nodes' => [
                                                    0 => [
                                                        'id' => '2000000005',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '__typename' => 'DiscountAmount',
                                            // ... on DiscountAmount
                                            'amount' => [
                                                'amount' => '2.07',
                                                'currencyCode' => 'USD',
                                            ],
                                            // ... on DiscountOnQuantity
                                            'quantity' => [
                                                'quantity' => '208',
                                            ],
                                            'effect' => [
                                                '__typename' => 'DiscountPercentage',
                                                // ... on DiscountPercentage
                                                'percentage' => '2.09',
                                            ],
                                            // ... on DiscountPercentage
                                            'percentage' => '2.10',
                                        ],
                                    ],
                                    'minimumRequirement' => [
                                        '__typename' => 'DiscountMinimumQuantity',
                                        // ... on DiscountMinimumQuantity
                                        'greaterThanOrEqualToQuantity' => '211',
                                        // ... on DiscountMinimumSubtotal
                                        'greaterThanOrEqualToSubtotal' => [
                                            'amount' => '2.12',
                                            'currencyCode' => 'USD',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'pageInfo' => [
                            'hasNextPage' => false,
                            'endCursor' => null,
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getMetafieldsSetQuery()) => [
                'data' => [
                    'metafieldsSet' => [
                        'metafields' => [
                            0 => [
                                'key' => '',
                                'namespace' => '',
                                'value' => '',
                                'createdAt' => '',
                                'updatedAt' => '',
                            ],
                        ],
                        'userErrors' => [
                            /*
                            0 => [
                                'field' => '',
                                'message' => '',
                                'code' => '',
                            ],
                            */
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBasicQueryByDiscountId(null, true)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '3000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBasic',
                            // ... on DiscountAutomaticBasic
                            'id' => '3000000002',
                            'title' => 'DiscountTitle302',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '3000000003',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '3.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '302',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '3.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '3.04',
                                ],
                            ],
                            'minimumRequirement' => [
                                '__typename' => 'DiscountMinimumQuantity',
                                // ... on DiscountMinimumQuantity
                                'greaterThanOrEqualToQuantity' => '305',
                                // ... on DiscountMinimumSubtotal
                                'greaterThanOrEqualToSubtotal' => [
                                    'amount' => '3.06',
                                    'currencyCode' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBasicQueryByDiscountId(null, false)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '4000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBasic',
                            // ... on DiscountAutomaticBasic
                            'id' => '4000000002',
                            'title' => 'DiscountTitle402',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle403',
                                                'id' => '4000000003',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '4.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '402',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '4.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '4.04',
                                ],
                            ],
                            'minimumRequirement' => [
                                '__typename' => 'DiscountMinimumQuantity',
                                // ... on DiscountMinimumQuantity
                                'greaterThanOrEqualToQuantity' => '405',
                                // ... on DiscountMinimumSubtotal
                                'greaterThanOrEqualToSubtotal' => [
                                    'amount' => '4.06',
                                    'currencyCode' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBxgyQueryByDiscountId(null, true)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '5000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBxgy',
                            // ... on DiscountAutomaticBxgy
                            'title' => 'DiscountTitle501',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '5.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '502',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '5.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '5.04',
                                ],
                            ],
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '5000000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountPurchaseAmount',
                                    // ... on DiscountPurchaseAmount
                                    'amount' => '5.05',
                                    // ... on DiscountQuantity
                                    'quantity' => '506',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBxgyQueryByDiscountId(null, false)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '6000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBxgy',
                            // ... on DiscountAutomaticBxgy
                            'title' => 'DiscountTitle601',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '6.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '602',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '6.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '6.04',
                                ],
                            ],
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle602',
                                                'id' => '6000000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountPurchaseAmount',
                                    // ... on DiscountPurchaseAmount
                                    'amount' => '6.05',
                                    // ... on DiscountQuantity
                                    'quantity' => '606',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getCollectionQueryByCollectionId()) => [
                'data' => [
                    'collection' => [
                        'products' => [
                            'nodes' => [
                                0 => [
                                    'id' => '7000000001',
                                ],
                            ],
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getCommentEventQuery()) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'events' => [
                            'nodes' => [
                                0 => [
                                    'message' => 'PROMO_TITLE: CommentEvent801',
                                ],
                                1 => [
                                    'message' => 'PROMO_BODY: CommentEvent802',
                                ],
                            ],
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBasicQueryByDiscountId('products', null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1100000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBasic
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '1100000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBasicQueryByDiscountId(null, null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1200000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBasic
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle122',
                                                'id' => '1200000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBxgyQueryByDiscountId('products', null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1300000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBxgy
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '1300000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBxgyQueryByDiscountId(null, null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1400000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBxgy
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle142',
                                                'id' => '1400000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getProductVariantQueryByIds()) => [
                'data' => [
                    'nodes' => [
                        0 => [
                            'id' => '1500000101',
                            'product' => [
                                'id' => '1500000111',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $command = new AddDiscountsToProductsCommand($graphQLQueryHelper, $shopifySdk, $multiscountClient);

        $refl = new \ReflectionProperty(get_class($command), 'shopifySDK');
        $refl->setAccessible(true);
        $refl->setValue($command, $mockShopify);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Start applying discounts to products', $output);
        $this->assertStringContainsString('Start querying products in "Eligible for Discount" collection...', $output);
        $this->assertStringContainsString('Successfully updated 3 products...', $output);
        $this->assertStringContainsString('Successfully apply discounts to 3 products', $output);

        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IGF1dG9tYXRpY0Rpc2NvdW50UXVlcnkoJHBlclBhZ2U6IEludCEpIHsgZGlzY291bnROb2RlcyAoZmlyc3Q6ICRwZXJQYWdlLCBxdWVyeTogXCJtZXRob2Q6YXV0b21hdGljIEFORCAoc3RhdHVzOmFjdGl2ZSBPUiBzdGF0dXM6c2NoZWR1bGVkKVwiICkgeyBub2RlcyB7IGlkIGRpc2NvdW50IHsgX190eXBlbmFtZSAuLi4gb24gRGlzY291bnRBdXRvbWF0aWNCeGd5IHsgc3RhcnRzQXQgZW5kc0F0IHRpdGxlIGRpc2NvdW50Q2xhc3MgY29tYmluZXNXaXRoIHsgcHJvZHVjdERpc2NvdW50cyB9IGN1c3RvbWVyR2V0cyB7IGl0ZW1zIHsgLi4uIG9uIERpc2NvdW50Q29sbGVjdGlvbnMgeyBjb2xsZWN0aW9ucyAoZmlyc3Q6IDEwICkgeyBub2RlcyB7IHRpdGxlIGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gLi4uIG9uIERpc2NvdW50UHJvZHVjdHMgeyBwcm9kdWN0cyAoZmlyc3Q6IDEwICkgeyBub2RlcyB7IGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gfSB2YWx1ZSB7IF9fdHlwZW5hbWUgLi4uIG9uIERpc2NvdW50QW1vdW50IHsgYW1vdW50IHsgYW1vdW50IGN1cnJlbmN5Q29kZSB9IH0gLi4uIG9uIERpc2NvdW50T25RdWFudGl0eSB7IHF1YW50aXR5IHsgcXVhbnRpdHkgfSBlZmZlY3QgeyBfX3R5cGVuYW1lIC4uLiBvbiBEaXNjb3VudFBlcmNlbnRhZ2UgeyBwZXJjZW50YWdlIH0gfSB9IC4uLiBvbiBEaXNjb3VudFBlcmNlbnRhZ2UgeyBwZXJjZW50YWdlIH0gfSB9IGN1c3RvbWVyQnV5cyB7IGl0ZW1zIHsgLi4uIG9uIERpc2NvdW50Q29sbGVjdGlvbnMgeyBjb2xsZWN0aW9ucyAoZmlyc3Q6ICRwZXJQYWdlICkgeyBub2RlcyB7IHRpdGxlIGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gLi4uIG9uIERpc2NvdW50UHJvZHVjdHMgeyBwcm9kdWN0cyAoZmlyc3Q6ICRwZXJQYWdlICkgeyBub2RlcyB7IGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gfSB2YWx1ZSB7IF9fdHlwZW5hbWUgLi4uIG9uIERpc2NvdW50UHVyY2hhc2VBbW91bnQgeyBhbW91bnQgfSAuLi4gb24gRGlzY291bnRRdWFudGl0eSB7IHF1YW50aXR5IH0gfSB9IH0gLi4uIG9uIERpc2NvdW50QXV0b21hdGljQmFzaWMgeyBzdGFydHNBdCBlbmRzQXQgdGl0bGUgZGlzY291bnRDbGFzcyBjdXN0b21lckdldHMgeyBpdGVtcyB7IC4uLiBvbiBEaXNjb3VudENvbGxlY3Rpb25zIHsgY29sbGVjdGlvbnMgKGZpcnN0OiAkcGVyUGFnZSApIHsgbm9kZXMgeyB0aXRsZSBpZCB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IC4uLiBvbiBEaXNjb3VudFByb2R1Y3RzIHsgcHJvZHVjdHMgKGZpcnN0OiAkcGVyUGFnZSApIHsgbm9kZXMgeyBpZCB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IH0gdmFsdWUgeyBfX3R5cGVuYW1lIC4uLiBvbiBEaXNjb3VudEFtb3VudCB7IGFtb3VudCB7IGFtb3VudCBjdXJyZW5jeUNvZGUgfSB9IC4uLiBvbiBEaXNjb3VudE9uUXVhbnRpdHkgeyBxdWFudGl0eSB7IHF1YW50aXR5IH0gZWZmZWN0IHsgX190eXBlbmFtZSAuLi4gb24gRGlzY291bnRQZXJjZW50YWdlIHsgcGVyY2VudGFnZSB9IH0gfSAuLi4gb24gRGlzY291bnRQZXJjZW50YWdlIHsgcGVyY2VudGFnZSB9IH0gfSBtaW5pbXVtUmVxdWlyZW1lbnQgeyBfX3R5cGVuYW1lIC4uLiBvbiBEaXNjb3VudE1pbmltdW1RdWFudGl0eSB7IGdyZWF0ZXJUaGFuT3JFcXVhbFRvUXVhbnRpdHkgfSAuLi4gb24gRGlzY291bnRNaW5pbXVtU3VidG90YWwgeyBncmVhdGVyVGhhbk9yRXF1YWxUb1N1YnRvdGFsIHsgYW1vdW50IGN1cnJlbmN5Q29kZSB9IH0gfSB9IC4uLiBvbiBEaXNjb3VudEF1dG9tYXRpY0FwcCB7IHN0YXJ0c0F0IGVuZHNBdCB0aXRsZSBkaXNjb3VudENsYXNzIHN0YXR1cyBjb21iaW5lc1dpdGggeyBwcm9kdWN0RGlzY291bnRzIH0gfSB9IH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0i'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IGRpc2NvdW50QmFzaWNRdWVyeUJ5RGlzY291bnRJZCgkcGVyUGFnZTogSW50ISwgJGRpc2NvdW50SWQ6IElEISkgeyBhdXRvbWF0aWNEaXNjb3VudE5vZGUgKGlkOiAkZGlzY291bnRJZCkgeyBhdXRvbWF0aWNEaXNjb3VudCB7IC4uLiBvbiBEaXNjb3VudEF1dG9tYXRpY0Jhc2ljIHsgY3VzdG9tZXJHZXRzIHsgaXRlbXMgeyAuLi4gb24gRGlzY291bnRDb2xsZWN0aW9ucyB7IGNvbGxlY3Rpb25zIChmaXJzdDogJHBlclBhZ2UgKSB7IG5vZGVzIHsgdGl0bGUgaWQgfSBwYWdlSW5mbyB7IGhhc05leHRQYWdlIGVuZEN1cnNvciB9IH0gfSB9IH0gfSB9IH0gfSI='));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IGNvbW1lbnRFdmVudFF1ZXJ5KCRwZXJQYWdlOiBJbnQhLCAkZGlzY291bnRJZDogSUQhKSB7IGF1dG9tYXRpY0Rpc2NvdW50Tm9kZSAoaWQ6ICRkaXNjb3VudElkKSB7IGV2ZW50cyAoZmlyc3Q6ICRwZXJQYWdlKSB7IG5vZGVzIHsgbWVzc2FnZSB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IH0i'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IGNvbGxlY3Rpb25RdWVyeUJ5Q29sbGVjdGlvbklkKCRjb2xsZWN0aW9uSWQ6IElEISwgJHBlclBhZ2U6IEludCEpIHsgY29sbGVjdGlvbihpZDogJGNvbGxlY3Rpb25JZCkgeyBwcm9kdWN0cyhmaXJzdDogJHBlclBhZ2UpIHsgbm9kZXMgeyBpZCB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IH0i'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IHByb2R1Y3RzQnlDb2xsZWN0aW9uKCRjb2xsZWN0aW9uTmFtZTogU3RyaW5nISwgJHBlclBhZ2U6IEludCEpIHsgY29sbGVjdGlvbkJ5SGFuZGxlKGhhbmRsZTogJGNvbGxlY3Rpb25OYW1lKSB7IHByb2R1Y3RzKGZpcnN0OiAkcGVyUGFnZSkgeyBub2RlcyB7IGlkIHZlbmRvciBlbGlnaWJsZUZvckRpc2NvdW50OiBtZXRhZmllbGQobmFtZXNwYWNlOiBcIm13X21hcmtldGluZ1wiIGtleTogXCJlbGlnaWJsZV9mb3JfZGlzY291bnRcIikgeyB2YWx1ZSB9IH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gfSI='));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsIm11dGF0aW9uIE1ldGFmaWVsZHNTZXQoJG1ldGFmaWVsZHM6IFtNZXRhZmllbGRzU2V0SW5wdXQhXSEpIHsgbWV0YWZpZWxkc1NldChtZXRhZmllbGRzOiAkbWV0YWZpZWxkcykgeyBtZXRhZmllbGRzIHsga2V5IG5hbWVzcGFjZSB2YWx1ZSBjcmVhdGVkQXQgdXBkYXRlZEF0IH0gdXNlckVycm9ycyB7IGZpZWxkIG1lc3NhZ2UgY29kZSB9IH0gfSI='));

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[0]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query automaticDiscountQuery($perPage: Int!) {
            discountNodes (first: $perPage, query: "method:automatic AND (status:active OR status:scheduled)" ) {
              nodes {
                id
                discount {
                  __typename
                  ... on DiscountAutomaticBxgy {
                    startsAt
                    endsAt
                    title
                    discountClass
                    combinesWith {
                      productDiscounts
                    }
                    customerGets {
                      items {
                        ... on DiscountCollections {
                          collections (first: 10 ) {
                            nodes {
                              title
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                        ... on DiscountProducts {
                          products (first: 10 ) {
                            nodes {
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                      }
                      value {
                        __typename
                        ... on DiscountAmount {
                          amount {
                            amount
                            currencyCode
                          }
                        }
                        ... on DiscountOnQuantity {
                          quantity {
                            quantity
                          }
                          effect {
                            __typename
                            ... on DiscountPercentage {
                              percentage
                            }
                          }
                        }
                        ... on DiscountPercentage {
                          percentage
                        }
                      }
                    }
                    customerBuys {
                      items {
                        ... on DiscountCollections {
                          collections (first: $perPage ) {
                            nodes {
                              title
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                        ... on DiscountProducts {
                          products (first: $perPage ) {
                            nodes {
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                      }
                      value {
                        __typename
                        ... on DiscountPurchaseAmount {
                          amount
                        }
                        ... on DiscountQuantity {
                          quantity
                        }
                      }
                    }
                  }
                  ... on DiscountAutomaticBasic {
                    startsAt
                    endsAt
                    title
                    discountClass
                    customerGets {
                      items {
                        ... on DiscountCollections {
                          collections (first: $perPage ) {
                            nodes {
                              title
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                        ... on DiscountProducts {
                          products (first: $perPage ) {
                            nodes {
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                      }
                      value {
                        __typename
                        ... on DiscountAmount {
                          amount {
                            amount
                            currencyCode
                          }
                        }
                        ... on DiscountOnQuantity {
                          quantity {
                            quantity
                          }
                          effect {
                            __typename
                            ... on DiscountPercentage {
                              percentage
                            }
                          }
                        }
                        ... on DiscountPercentage {
                          percentage
                        }
                      }
                    }
                    minimumRequirement {
                      __typename
                      ... on DiscountMinimumQuantity {
                        greaterThanOrEqualToQuantity
                      }
                      ... on DiscountMinimumSubtotal {
                        greaterThanOrEqualToSubtotal {
                          amount
                          currencyCode
                        }
                      }
                    }
                  }
                  ... on DiscountAutomaticApp {
                    startsAt
                    endsAt
                    title
                    discountClass
                    status
                    combinesWith {
                      productDiscounts
                    }
                  }
                }
              }
              pageInfo {
                hasNextPage
                endCursor
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[0]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 20,
            'endCursor' => null,
        ], $this->getLogger()->records[0]['context']['request']['variables']);
        //$this->assertEquals('2000000001', $this->getLogger()->records[0]['context']['response']['data']['automaticDiscountNodes']['nodes'][0]['id']);  // MWS-642
        $this->assertEquals('2000000001', $this->getLogger()->records[0]['context']['response']['data']['discountNodes']['nodes'][0]['id']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[1]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query discountBasicQueryByDiscountId($perPage: Int!, $discountId: ID!) {
            automaticDiscountNode (id: $discountId) {
              automaticDiscount {
                ... on DiscountAutomaticBasic {
                  customerGets {
                    items {
                      ... on DiscountCollections {
                        collections (first: $perPage ) {
                          nodes {
                            title
                            id
                          }
                          pageInfo {
                            hasNextPage
                            endCursor
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[1]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 250,
            'endCursor' => null,
            'discountId' => '2000000001',
        ], $this->getLogger()->records[1]['context']['request']['variables']);
        $this->assertEquals('CollectionTitle122', $this->getLogger()->records[1]['context']['response']['data']['automaticDiscountNode']['automaticDiscount']['customerGets']['items']['collections']['nodes'][0]['title']);
        $this->assertEquals('1200000002', $this->getLogger()->records[1]['context']['response']['data']['automaticDiscountNode']['automaticDiscount']['customerGets']['items']['collections']['nodes'][0]['id']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[2]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query commentEventQuery($perPage: Int!, $discountId: ID!) {
            automaticDiscountNode (id: $discountId) {
              events (first: $perPage) {
                nodes {
                  message
                }
                pageInfo {
                  hasNextPage
                  endCursor
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[2]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 50,
            'discountId' => '2000000001',
            'endCursor' => null,
        ], $this->getLogger()->records[2]['context']['request']['variables']);
        $this->assertEquals('PROMO_TITLE: CommentEvent801', $this->getLogger()->records[2]['context']['response']['data']['automaticDiscountNode']['events']['nodes'][0]['message']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[3]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query collectionQueryByCollectionId($collectionId: ID!, $perPage: Int!) {
            collection(id: $collectionId) {
              products(first: $perPage) {
                nodes {
                  id
                }
                pageInfo {
                  hasNextPage
                  endCursor
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[3]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 20,
            'collectionId' => '1200000002',
            'endCursor' => null,
        ], $this->getLogger()->records[3]['context']['request']['variables']);
        $this->assertEquals('7000000001', $this->getLogger()->records[3]['context']['response']['data']['collection']['products']['nodes'][0]['id']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[4]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query productsByCollection($collectionName: String!, $perPage: Int!) {
            collectionByHandle(handle: $collectionName) {
              products(first: $perPage) {
                nodes {
                  id
                  vendor
                  eligibleForDiscount: metafield(namespace: "mw_marketing" key: "eligible_for_discount") {
                    value
                  }
                }
                pageInfo {
                  hasNextPage
                  endCursor
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[4]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 20,
            'endCursor' => null,
            'collectionName' => 'eligible-for-discount',
        ], $this->getLogger()->records[4]['context']['request']['variables']);
        $this->assertEquals('1000000001', $this->getLogger()->records[4]['context']['response']['data']['collectionByHandle']['products']['nodes'][0]['id']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[5]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          mutation MetafieldsSet($metafields: [MetafieldsSetInput!]!) {
            metafieldsSet(metafields: $metafields) {
              metafields {
                key
                namespace
                value
                createdAt
                updatedAt
              }
              userErrors {
                field
                message
                code
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[5]['context']['request']['query']));
        $this->assertSame([
            'metafields' => [
                0 => [
                    'key' => 'applicable_discounts',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '2000000005',
                    'type' => 'json',
                    'value' => '[{"discountTitle":"DiscountTitle201","discountId":"2000000001","collectionTitle":"","collectionBody":"","qualified":"","unQualified":"","promoTitle":"CommentEvent801","promoBody":"CommentEvent802","combinesWithProductDiscounts":true,"startsAt":null,"endsAt":null,"timezone":"UTC","discountType":"DiscountAutomaticBasic","minimumRequirement":{"__typename":"DiscountMinimumQuantity","greaterThanOrEqualToQuantity":"211","greaterThanOrEqualToSubtotal":{"amount":"2.12","currencyCode":"USD"}},"discountValue":{"__typename":"DiscountAmount","amount":{"amount":"2.07","currencyCode":"USD"},"quantity":{"quantity":"208"},"effect":{"__typename":"DiscountPercentage","percentage":"2.09"},"percentage":"2.10"},"customerBuysValue":{"__typename":"DiscountPurchaseAmount","amount":"2.05","quantity":"206"},"customerGetsItems":[{"title":"CollectionTitle122","id":"1200000002"},{"id":"2000000005"}]}]',
                ],
                1 => [
                    'key' => 'eligible_for_discount',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '2000000005',
                    'type' => 'boolean',
                    'value' => 'true',
                ],
                2 => [
                    'key' => 'applicable_discounts',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '7000000001',
                    'type' => 'json',
                    'value' => '[{"discountTitle":"DiscountTitle201","discountId":"2000000001","collectionTitle":"","collectionBody":"","qualified":"","unQualified":"","promoTitle":"CommentEvent801","promoBody":"CommentEvent802","combinesWithProductDiscounts":true,"startsAt":null,"endsAt":null,"timezone":"UTC","discountType":"DiscountAutomaticBasic","minimumRequirement":{"__typename":"DiscountMinimumQuantity","greaterThanOrEqualToQuantity":"211","greaterThanOrEqualToSubtotal":{"amount":"2.12","currencyCode":"USD"}},"discountValue":{"__typename":"DiscountAmount","amount":{"amount":"2.07","currencyCode":"USD"},"quantity":{"quantity":"208"},"effect":{"__typename":"DiscountPercentage","percentage":"2.09"},"percentage":"2.10"},"customerBuysValue":{"__typename":"DiscountPurchaseAmount","amount":"2.05","quantity":"206"},"customerGetsItems":[{"title":"CollectionTitle122","id":"1200000002"},{"id":"2000000005"}]}]',
                ],
                3 => [
                    'key' => 'eligible_for_discount',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '7000000001',
                    'type' => 'boolean',
                    'value' => 'true',
                ],
                4 => [
                    'key' => 'eligible_for_discount',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '1000000001',
                    'type' => 'boolean',
                    'value' => 'false',
                ],
                5 => [
                    'key' => 'applicable_discounts',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '1000000001',
                    'type' => 'json',
                    'value' => '[]',
                ],
            ],
        ], $this->getLogger()->records[5]['context']['request']['variables']);
        $this->assertEquals([], $this->getLogger()->records[5]['context']['response']['data']['metafieldsSet']['userErrors']);
    }

    public function testExecuteAddDiscountsToProductsForDiscountAutomaticBxgy()
    {
        $kernel = static::createKernel();

        $multiscountClient = new MultiscountClient($this->getHttpClient());
        $graphQLQueryHelper = new GraphQLQueryHelper();
        $shopifySdk = new ShopifySDK([
            'ShopUrl' => '',
            'AccessToken' => '',
        ]);
        $mockMethods = new MockMethods();
        $mockMethods->setLogger($this->getLogger());
        $mockShopify = new AddDiscountsToProductsCommandTestLocalMockShopify();
        $mockShopify->setMockMethods($mockMethods);
        $mockShopify->setMockData([
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getProductsQueryByCollection()) => [
                'data' => [
                    'collectionByHandle' => [
                        'products' => [
                            'nodes' => [
                                0 => [
                                    'id' => '1000000001',
                                    'vendor' => 'GreatVendor',
                                    'eligibleForDiscount' => [
                                        'value' => 'true',
                                    ],
                                ],
                            ],
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountQuery()) => [
                'data' => [
                    //'automaticDiscountNodes' => [  // MWS-642
                    'discountNodes' => [
                        'nodes' => [
                            0 => [
                                'id' => '2000000001',
                                //'automaticDiscount' => [  // MWS-642
                                'discount' => [
                                    '__typename' => 'DiscountAutomaticBxgy',
                                    // ... on DiscountAutomaticBxgy
                                    // ... on DiscountAutomaticBasic
                                    // ... on DiscountAutomaticApp
                                    'startsAt' => '',
                                    'endsAt' => '',
                                    'title' => 'DiscountTitle201',
                                    'discountClass' => 'PRODUCT',
                                    // ... on DiscountAutomaticApp
                                    //'status' => 'ACTIVE',
                                    'combinesWith' => [
                                        'productDiscounts' => true,
                                    ],
                                    // ... on DiscountAutomaticBxgy
                                    /*
                                    'customerGets' => [
                                        'items' => [
                                            // ... on DiscountCollections
                                            'collections' => [
                                                'nodes' => [
                                                    0 => [
                                                        'title' => 'CollectionTitle204',
                                                        'id' => '2000000004',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                            // ... on DiscountProducts
                                            'products' => [
                                                'nodes' => [
                                                    0 => [
                                                        'id' => '2000000005',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '__typename' => 'DiscountAmount',
                                            // ... on DiscountAmount
                                            'amount' => [
                                                'amount' => '2.01',
                                                'currencyCode' => 'USD',
                                            ],
                                            // ... on DiscountOnQuantity
                                            'quantity' => [
                                                'quantity' => '202',
                                            ],
                                            'effect' => [
                                                '__typename' => 'DiscountPercentage',
                                                // ... on DiscountPercentage
                                                'percentage' => '2.03',
                                            ],
                                            // ... on DiscountPercentage
                                            'percentage' => '2.04',
                                        ],
                                    ],
                                    */
                                    'customerBuys' => [
                                        'items' => [
                                            // ... on DiscountCollections
                                            'collections' => [
                                                'nodes' => [
                                                    0 => [
                                                        'title' => 'CollectionTitle202',
                                                        'id' => '2000000002',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                            // ... on DiscountProducts
                                            'products' => [
                                                'nodes' => [
                                                    0 => [
                                                        'id' => '2000000003',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '__typename' => 'DiscountPurchaseAmount',
                                            // ... on DiscountPurchaseAmount
                                            'amount' => '2.05',
                                            // ... on DiscountQuantity
                                            'quantity' => '206',
                                        ],
                                    ],
                                    // ... on DiscountAutomaticBasic
                                    'customerGets' => [
                                        'items' => [
                                            // ... on DiscountCollections
                                            'collections' => [
                                                'nodes' => [
                                                    0 => [
                                                        'title' => 'CollectionTitle204',
                                                        'id' => '2000000004',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                            // ... on DiscountProducts
                                            'products' => [
                                                'nodes' => [
                                                    0 => [
                                                        'id' => '2000000005',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '__typename' => 'DiscountAmount',
                                            // ... on DiscountAmount
                                            'amount' => [
                                                'amount' => '2.07',
                                                'currencyCode' => 'USD',
                                            ],
                                            // ... on DiscountOnQuantity
                                            'quantity' => [
                                                'quantity' => '208',
                                            ],
                                            'effect' => [
                                                '__typename' => 'DiscountPercentage',
                                                // ... on DiscountPercentage
                                                'percentage' => '2.09',
                                            ],
                                            // ... on DiscountPercentage
                                            'percentage' => '2.10',
                                        ],
                                    ],
                                    'minimumRequirement' => [
                                        '__typename' => 'DiscountMinimumQuantity',
                                        // ... on DiscountMinimumQuantity
                                        'greaterThanOrEqualToQuantity' => '211',
                                        // ... on DiscountMinimumSubtotal
                                        'greaterThanOrEqualToSubtotal' => [
                                            'amount' => '2.12',
                                            'currencyCode' => 'USD',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'pageInfo' => [
                            'hasNextPage' => false,
                            'endCursor' => null,
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getMetafieldsSetQuery()) => [
                'data' => [
                    'metafieldsSet' => [
                        'metafields' => [
                            0 => [
                                'key' => '',
                                'namespace' => '',
                                'value' => '',
                                'createdAt' => '',
                                'updatedAt' => '',
                            ],
                        ],
                        'userErrors' => [
                            /*
                            0 => [
                                'field' => '',
                                'message' => '',
                                'code' => '',
                            ],
                            */
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBasicQueryByDiscountId(null, true)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '3000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBasic',
                            // ... on DiscountAutomaticBasic
                            'id' => '3000000002',
                            'title' => 'DiscountTitle302',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '3000000003',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '3.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '302',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '3.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '3.04',
                                ],
                            ],
                            'minimumRequirement' => [
                                '__typename' => 'DiscountMinimumQuantity',
                                // ... on DiscountMinimumQuantity
                                'greaterThanOrEqualToQuantity' => '305',
                                // ... on DiscountMinimumSubtotal
                                'greaterThanOrEqualToSubtotal' => [
                                    'amount' => '3.06',
                                    'currencyCode' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBasicQueryByDiscountId(null, false)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '4000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBasic',
                            // ... on DiscountAutomaticBasic
                            'id' => '4000000002',
                            'title' => 'DiscountTitle402',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle403',
                                                'id' => '4000000003',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '4.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '402',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '4.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '4.04',
                                ],
                            ],
                            'minimumRequirement' => [
                                '__typename' => 'DiscountMinimumQuantity',
                                // ... on DiscountMinimumQuantity
                                'greaterThanOrEqualToQuantity' => '405',
                                // ... on DiscountMinimumSubtotal
                                'greaterThanOrEqualToSubtotal' => [
                                    'amount' => '4.06',
                                    'currencyCode' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBxgyQueryByDiscountId(null, true)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '5000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBxgy',
                            // ... on DiscountAutomaticBxgy
                            'title' => 'DiscountTitle501',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '5.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '502',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '5.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '5.04',
                                ],
                            ],
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '5000000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountPurchaseAmount',
                                    // ... on DiscountPurchaseAmount
                                    'amount' => '5.05',
                                    // ... on DiscountQuantity
                                    'quantity' => '506',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBxgyQueryByDiscountId(null, false)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '6000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBxgy',
                            // ... on DiscountAutomaticBxgy
                            'title' => 'DiscountTitle601',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '6.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '602',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '6.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '6.04',
                                ],
                            ],
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle602',
                                                'id' => '6000000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountPurchaseAmount',
                                    // ... on DiscountPurchaseAmount
                                    'amount' => '6.05',
                                    // ... on DiscountQuantity
                                    'quantity' => '606',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getCollectionQueryByCollectionId()) => [
                'data' => [
                    'collection' => [
                        'products' => [
                            'nodes' => [
                                0 => [
                                    'id' => '7000000001',
                                ],
                            ],
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getCommentEventQuery()) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'events' => [
                            'nodes' => [
                                0 => [
                                    'message' => 'PROMO_TITLE: CommentEvent801',
                                ],
                                1 => [
                                    'message' => 'PROMO_BODY: CommentEvent802',
                                ],
                            ],
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBasicQueryByDiscountId('products', null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1100000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBasic
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '1100000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBasicQueryByDiscountId(null, null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1200000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBasic
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle122',
                                                'id' => '1200000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBxgyQueryByDiscountId('products', null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1300000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBxgy
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '1300000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBxgyQueryByDiscountId(null, null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1400000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBxgy
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle142',
                                                'id' => '1400000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getProductVariantQueryByIds()) => [
                'data' => [
                    'nodes' => [
                        0 => [
                            'id' => '1500000101',
                            'product' => [
                                'id' => '1500000111',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $command = new AddDiscountsToProductsCommand($graphQLQueryHelper, $shopifySdk, $multiscountClient);

        $refl = new \ReflectionProperty(get_class($command), 'shopifySDK');
        $refl->setAccessible(true);
        $refl->setValue($command, $mockShopify);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Start applying discounts to products', $output);
        $this->assertStringContainsString('Start querying products in "Eligible for Discount" collection...', $output);
        $this->assertStringContainsString('Successfully updated 3 products...', $output);
        $this->assertStringContainsString('Successfully apply discounts to 3 products', $output);

        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IGF1dG9tYXRpY0Rpc2NvdW50UXVlcnkoJHBlclBhZ2U6IEludCEpIHsgZGlzY291bnROb2RlcyAoZmlyc3Q6ICRwZXJQYWdlLCBxdWVyeTogXCJtZXRob2Q6YXV0b21hdGljIEFORCAoc3RhdHVzOmFjdGl2ZSBPUiBzdGF0dXM6c2NoZWR1bGVkKVwiICkgeyBub2RlcyB7IGlkIGRpc2NvdW50IHsgX190eXBlbmFtZSAuLi4gb24gRGlzY291bnRBdXRvbWF0aWNCeGd5IHsgc3RhcnRzQXQgZW5kc0F0IHRpdGxlIGRpc2NvdW50Q2xhc3MgY29tYmluZXNXaXRoIHsgcHJvZHVjdERpc2NvdW50cyB9IGN1c3RvbWVyR2V0cyB7IGl0ZW1zIHsgLi4uIG9uIERpc2NvdW50Q29sbGVjdGlvbnMgeyBjb2xsZWN0aW9ucyAoZmlyc3Q6IDEwICkgeyBub2RlcyB7IHRpdGxlIGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gLi4uIG9uIERpc2NvdW50UHJvZHVjdHMgeyBwcm9kdWN0cyAoZmlyc3Q6IDEwICkgeyBub2RlcyB7IGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gfSB2YWx1ZSB7IF9fdHlwZW5hbWUgLi4uIG9uIERpc2NvdW50QW1vdW50IHsgYW1vdW50IHsgYW1vdW50IGN1cnJlbmN5Q29kZSB9IH0gLi4uIG9uIERpc2NvdW50T25RdWFudGl0eSB7IHF1YW50aXR5IHsgcXVhbnRpdHkgfSBlZmZlY3QgeyBfX3R5cGVuYW1lIC4uLiBvbiBEaXNjb3VudFBlcmNlbnRhZ2UgeyBwZXJjZW50YWdlIH0gfSB9IC4uLiBvbiBEaXNjb3VudFBlcmNlbnRhZ2UgeyBwZXJjZW50YWdlIH0gfSB9IGN1c3RvbWVyQnV5cyB7IGl0ZW1zIHsgLi4uIG9uIERpc2NvdW50Q29sbGVjdGlvbnMgeyBjb2xsZWN0aW9ucyAoZmlyc3Q6ICRwZXJQYWdlICkgeyBub2RlcyB7IHRpdGxlIGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gLi4uIG9uIERpc2NvdW50UHJvZHVjdHMgeyBwcm9kdWN0cyAoZmlyc3Q6ICRwZXJQYWdlICkgeyBub2RlcyB7IGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gfSB2YWx1ZSB7IF9fdHlwZW5hbWUgLi4uIG9uIERpc2NvdW50UHVyY2hhc2VBbW91bnQgeyBhbW91bnQgfSAuLi4gb24gRGlzY291bnRRdWFudGl0eSB7IHF1YW50aXR5IH0gfSB9IH0gLi4uIG9uIERpc2NvdW50QXV0b21hdGljQmFzaWMgeyBzdGFydHNBdCBlbmRzQXQgdGl0bGUgZGlzY291bnRDbGFzcyBjdXN0b21lckdldHMgeyBpdGVtcyB7IC4uLiBvbiBEaXNjb3VudENvbGxlY3Rpb25zIHsgY29sbGVjdGlvbnMgKGZpcnN0OiAkcGVyUGFnZSApIHsgbm9kZXMgeyB0aXRsZSBpZCB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IC4uLiBvbiBEaXNjb3VudFByb2R1Y3RzIHsgcHJvZHVjdHMgKGZpcnN0OiAkcGVyUGFnZSApIHsgbm9kZXMgeyBpZCB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IH0gdmFsdWUgeyBfX3R5cGVuYW1lIC4uLiBvbiBEaXNjb3VudEFtb3VudCB7IGFtb3VudCB7IGFtb3VudCBjdXJyZW5jeUNvZGUgfSB9IC4uLiBvbiBEaXNjb3VudE9uUXVhbnRpdHkgeyBxdWFudGl0eSB7IHF1YW50aXR5IH0gZWZmZWN0IHsgX190eXBlbmFtZSAuLi4gb24gRGlzY291bnRQZXJjZW50YWdlIHsgcGVyY2VudGFnZSB9IH0gfSAuLi4gb24gRGlzY291bnRQZXJjZW50YWdlIHsgcGVyY2VudGFnZSB9IH0gfSBtaW5pbXVtUmVxdWlyZW1lbnQgeyBfX3R5cGVuYW1lIC4uLiBvbiBEaXNjb3VudE1pbmltdW1RdWFudGl0eSB7IGdyZWF0ZXJUaGFuT3JFcXVhbFRvUXVhbnRpdHkgfSAuLi4gb24gRGlzY291bnRNaW5pbXVtU3VidG90YWwgeyBncmVhdGVyVGhhbk9yRXF1YWxUb1N1YnRvdGFsIHsgYW1vdW50IGN1cnJlbmN5Q29kZSB9IH0gfSB9IC4uLiBvbiBEaXNjb3VudEF1dG9tYXRpY0FwcCB7IHN0YXJ0c0F0IGVuZHNBdCB0aXRsZSBkaXNjb3VudENsYXNzIHN0YXR1cyBjb21iaW5lc1dpdGggeyBwcm9kdWN0RGlzY291bnRzIH0gfSB9IH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0i'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IGRpc2NvdW50QnhneVF1ZXJ5QnlEaXNjb3VudElkKCRwZXJQYWdlOiBJbnQhLCAkZGlzY291bnRJZDogSUQhKSB7IGF1dG9tYXRpY0Rpc2NvdW50Tm9kZSAoaWQ6ICRkaXNjb3VudElkKSB7IGF1dG9tYXRpY0Rpc2NvdW50IHsgLi4uIG9uIERpc2NvdW50QXV0b21hdGljQnhneSB7IGN1c3RvbWVyQnV5cyB7IGl0ZW1zIHsgLi4uIG9uIERpc2NvdW50Q29sbGVjdGlvbnMgeyBjb2xsZWN0aW9ucyAoZmlyc3Q6ICRwZXJQYWdlICkgeyBub2RlcyB7IHRpdGxlIGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gfSB9IH0gfSB9IH0i'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IGNvbW1lbnRFdmVudFF1ZXJ5KCRwZXJQYWdlOiBJbnQhLCAkZGlzY291bnRJZDogSUQhKSB7IGF1dG9tYXRpY0Rpc2NvdW50Tm9kZSAoaWQ6ICRkaXNjb3VudElkKSB7IGV2ZW50cyAoZmlyc3Q6ICRwZXJQYWdlKSB7IG5vZGVzIHsgbWVzc2FnZSB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IH0i'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IGNvbGxlY3Rpb25RdWVyeUJ5Q29sbGVjdGlvbklkKCRjb2xsZWN0aW9uSWQ6IElEISwgJHBlclBhZ2U6IEludCEpIHsgY29sbGVjdGlvbihpZDogJGNvbGxlY3Rpb25JZCkgeyBwcm9kdWN0cyhmaXJzdDogJHBlclBhZ2UpIHsgbm9kZXMgeyBpZCB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IH0i'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IHByb2R1Y3RzQnlDb2xsZWN0aW9uKCRjb2xsZWN0aW9uTmFtZTogU3RyaW5nISwgJHBlclBhZ2U6IEludCEpIHsgY29sbGVjdGlvbkJ5SGFuZGxlKGhhbmRsZTogJGNvbGxlY3Rpb25OYW1lKSB7IHByb2R1Y3RzKGZpcnN0OiAkcGVyUGFnZSkgeyBub2RlcyB7IGlkIHZlbmRvciBlbGlnaWJsZUZvckRpc2NvdW50OiBtZXRhZmllbGQobmFtZXNwYWNlOiBcIm13X21hcmtldGluZ1wiIGtleTogXCJlbGlnaWJsZV9mb3JfZGlzY291bnRcIikgeyB2YWx1ZSB9IH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gfSI='));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsIm11dGF0aW9uIE1ldGFmaWVsZHNTZXQoJG1ldGFmaWVsZHM6IFtNZXRhZmllbGRzU2V0SW5wdXQhXSEpIHsgbWV0YWZpZWxkc1NldChtZXRhZmllbGRzOiAkbWV0YWZpZWxkcykgeyBtZXRhZmllbGRzIHsga2V5IG5hbWVzcGFjZSB2YWx1ZSBjcmVhdGVkQXQgdXBkYXRlZEF0IH0gdXNlckVycm9ycyB7IGZpZWxkIG1lc3NhZ2UgY29kZSB9IH0gfSI='));

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[0]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query automaticDiscountQuery($perPage: Int!) {
            discountNodes (first: $perPage, query: "method:automatic AND (status:active OR status:scheduled)" ) {
              nodes {
                id
                discount {
                  __typename
                  ... on DiscountAutomaticBxgy {
                    startsAt
                    endsAt
                    title
                    discountClass
                    combinesWith {
                      productDiscounts
                    }
                    customerGets {
                      items {
                        ... on DiscountCollections {
                          collections (first: 10 ) {
                            nodes {
                              title
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                        ... on DiscountProducts {
                          products (first: 10 ) {
                            nodes {
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                      }
                      value {
                        __typename
                        ... on DiscountAmount {
                          amount {
                            amount
                            currencyCode
                          }
                        }
                        ... on DiscountOnQuantity {
                          quantity {
                            quantity
                          }
                          effect {
                            __typename
                            ... on DiscountPercentage {
                              percentage
                            }
                          }
                        }
                        ... on DiscountPercentage {
                          percentage
                        }
                      }
                    }
                    customerBuys {
                      items {
                        ... on DiscountCollections {
                          collections (first: $perPage ) {
                            nodes {
                              title
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                        ... on DiscountProducts {
                          products (first: $perPage ) {
                            nodes {
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                      }
                      value {
                        __typename
                        ... on DiscountPurchaseAmount {
                          amount
                        }
                        ... on DiscountQuantity {
                          quantity
                        }
                      }
                    }
                  }
                  ... on DiscountAutomaticBasic {
                    startsAt
                    endsAt
                    title
                    discountClass
                    customerGets {
                      items {
                        ... on DiscountCollections {
                          collections (first: $perPage ) {
                            nodes {
                              title
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                        ... on DiscountProducts {
                          products (first: $perPage ) {
                            nodes {
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                      }
                      value {
                        __typename
                        ... on DiscountAmount {
                          amount {
                            amount
                            currencyCode
                          }
                        }
                        ... on DiscountOnQuantity {
                          quantity {
                            quantity
                          }
                          effect {
                            __typename
                            ... on DiscountPercentage {
                              percentage
                            }
                          }
                        }
                        ... on DiscountPercentage {
                          percentage
                        }
                      }
                    }
                    minimumRequirement {
                      __typename
                      ... on DiscountMinimumQuantity {
                        greaterThanOrEqualToQuantity
                      }
                      ... on DiscountMinimumSubtotal {
                        greaterThanOrEqualToSubtotal {
                          amount
                          currencyCode
                        }
                      }
                    }
                  }
                  ... on DiscountAutomaticApp {
                    startsAt
                    endsAt
                    title
                    discountClass
                    status
                    combinesWith {
                      productDiscounts
                    }
                  }
                }
              }
              pageInfo {
                hasNextPage
                endCursor
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[0]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 20,
            'endCursor' => null,
        ], $this->getLogger()->records[0]['context']['request']['variables']);
        //$this->assertEquals('2000000001', $this->getLogger()->records[0]['context']['response']['data']['automaticDiscountNodes']['nodes'][0]['id']);  // MWS-642
        $this->assertEquals('2000000001', $this->getLogger()->records[0]['context']['response']['data']['discountNodes']['nodes'][0]['id']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[1]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query discountBxgyQueryByDiscountId($perPage: Int!, $discountId: ID!) {
            automaticDiscountNode (id: $discountId) {
              automaticDiscount {
                ... on DiscountAutomaticBxgy {
                  customerBuys {
                    items {
                      ... on DiscountCollections {
                        collections (first: $perPage ) {
                          nodes {
                            title
                            id
                          }
                          pageInfo {
                            hasNextPage
                            endCursor
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[1]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 250,
            'endCursor' => null,
            'discountId' => '2000000001',
        ], $this->getLogger()->records[1]['context']['request']['variables']);
        $this->assertEquals('CollectionTitle142', $this->getLogger()->records[1]['context']['response']['data']['automaticDiscountNode']['automaticDiscount']['customerBuys']['items']['collections']['nodes'][0]['title']);
        $this->assertEquals('1400000002', $this->getLogger()->records[1]['context']['response']['data']['automaticDiscountNode']['automaticDiscount']['customerBuys']['items']['collections']['nodes'][0]['id']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[2]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query commentEventQuery($perPage: Int!, $discountId: ID!) {
            automaticDiscountNode (id: $discountId) {
              events (first: $perPage) {
                nodes {
                  message
                }
                pageInfo {
                  hasNextPage
                  endCursor
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[2]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 50,
            'discountId' => '2000000001',
            'endCursor' => null,
        ], $this->getLogger()->records[2]['context']['request']['variables']);
        $this->assertEquals('PROMO_TITLE: CommentEvent801', $this->getLogger()->records[2]['context']['response']['data']['automaticDiscountNode']['events']['nodes'][0]['message']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[3]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query collectionQueryByCollectionId($collectionId: ID!, $perPage: Int!) {
            collection(id: $collectionId) {
              products(first: $perPage) {
                nodes {
                  id
                }
                pageInfo {
                  hasNextPage
                  endCursor
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[3]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 20,
            'collectionId' => '1400000002',
            'endCursor' => null,
        ], $this->getLogger()->records[3]['context']['request']['variables']);
        $this->assertEquals('7000000001', $this->getLogger()->records[3]['context']['response']['data']['collection']['products']['nodes'][0]['id']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[4]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query productsByCollection($collectionName: String!, $perPage: Int!) {
            collectionByHandle(handle: $collectionName) {
              products(first: $perPage) {
                nodes {
                  id
                  vendor
                  eligibleForDiscount: metafield(namespace: "mw_marketing" key: "eligible_for_discount") {
                    value
                  }
                }
                pageInfo {
                  hasNextPage
                  endCursor
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[4]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 20,
            'endCursor' => null,
            'collectionName' => 'eligible-for-discount',
        ], $this->getLogger()->records[4]['context']['request']['variables']);
        $this->assertEquals('1000000001', $this->getLogger()->records[4]['context']['response']['data']['collectionByHandle']['products']['nodes'][0]['id']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[5]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          mutation MetafieldsSet($metafields: [MetafieldsSetInput!]!) {
            metafieldsSet(metafields: $metafields) {
              metafields {
                key
                namespace
                value
                createdAt
                updatedAt
              }
              userErrors {
                field
                message
                code
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[5]['context']['request']['query']));
        $this->assertSame([
            'metafields' => [
                0 => [
                    'key' => 'applicable_discounts',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '2000000003',
                    'type' => 'json',
                    'value' => '[{"discountTitle":"DiscountTitle201","discountId":"2000000001","collectionTitle":"","collectionBody":"","qualified":"","unQualified":"","promoTitle":"CommentEvent801","promoBody":"CommentEvent802","combinesWithProductDiscounts":true,"startsAt":null,"endsAt":null,"timezone":"UTC","discountType":"DiscountAutomaticBxgy","minimumRequirement":{"__typename":"DiscountMinimumQuantity","greaterThanOrEqualToQuantity":"211","greaterThanOrEqualToSubtotal":{"amount":"2.12","currencyCode":"USD"}},"discountValue":{"__typename":"DiscountAmount","amount":{"amount":"2.07","currencyCode":"USD"},"quantity":{"quantity":"208"},"effect":{"__typename":"DiscountPercentage","percentage":"2.09"},"percentage":"2.10"},"customerBuysValue":{"__typename":"DiscountPurchaseAmount","amount":"2.05","quantity":"206"},"customerGetsItems":[{"title":"CollectionTitle204","id":"2000000004"},{"id":"2000000005"}]}]',
                ],
                1 => [
                    'key' => 'eligible_for_discount',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '2000000003',
                    'type' => 'boolean',
                    'value' => 'true',
                ],
                2 => [
                    'key' => 'applicable_discounts',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '7000000001',
                    'type' => 'json',
                    'value' => '[{"discountTitle":"DiscountTitle201","discountId":"2000000001","collectionTitle":"","collectionBody":"","qualified":"","unQualified":"","promoTitle":"CommentEvent801","promoBody":"CommentEvent802","combinesWithProductDiscounts":true,"startsAt":null,"endsAt":null,"timezone":"UTC","discountType":"DiscountAutomaticBxgy","minimumRequirement":{"__typename":"DiscountMinimumQuantity","greaterThanOrEqualToQuantity":"211","greaterThanOrEqualToSubtotal":{"amount":"2.12","currencyCode":"USD"}},"discountValue":{"__typename":"DiscountAmount","amount":{"amount":"2.07","currencyCode":"USD"},"quantity":{"quantity":"208"},"effect":{"__typename":"DiscountPercentage","percentage":"2.09"},"percentage":"2.10"},"customerBuysValue":{"__typename":"DiscountPurchaseAmount","amount":"2.05","quantity":"206"},"customerGetsItems":[{"title":"CollectionTitle204","id":"2000000004"},{"id":"2000000005"}]}]',
                ],
                3 => [
                    'key' => 'eligible_for_discount',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '7000000001',
                    'type' => 'boolean',
                    'value' => 'true',
                ],
                4 => [
                    'key' => 'eligible_for_discount',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '1000000001',
                    'type' => 'boolean',
                    'value' => 'false',
                ],
                5 => [
                    'key' => 'applicable_discounts',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '1000000001',
                    'type' => 'json',
                    'value' => '[]',
                ],
            ],
        ], $this->getLogger()->records[5]['context']['request']['variables']);
        $this->assertEquals([], $this->getLogger()->records[5]['context']['response']['data']['metafieldsSet']['userErrors']);
    }

    public function testExecuteAddDiscountsToProductsForDiscountAutomaticAppWithStatusActive()
    {
        $kernel = static::createKernel();

        $multiscountClient = new MultiscountClient($this->getHttpClient([
            '/apps/multiscount/v2/discounts|POST|title=DiscountTitle201&type=volume' => [
                0 => [
                    'products' => [
                        'gid://shopify/Collection/2000000004',
                        'gid://shopify/Product/2000000005',
                    ],
                ],
            ],
        ]));
        $graphQLQueryHelper = new GraphQLQueryHelper();
        $shopifySdk = new ShopifySDK([
            'ShopUrl' => '',
            'AccessToken' => '',
        ]);
        $mockMethods = new MockMethods();
        $mockMethods->setLogger($this->getLogger());
        $mockShopify = new AddDiscountsToProductsCommandTestLocalMockShopify();
        $mockShopify->setMockMethods($mockMethods);
        $mockShopify->setMockData([
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getProductsQueryByCollection()) => [
                'data' => [
                    'collectionByHandle' => [
                        'products' => [
                            'nodes' => [
                                0 => [
                                    'id' => '1000000001',
                                    'vendor' => 'GreatVendor',
                                    'eligibleForDiscount' => [
                                        'value' => 'true',
                                    ],
                                ],
                            ],
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountQuery()) => [
                'data' => [
                    //'automaticDiscountNodes' => [  // MWS-642
                    'discountNodes' => [
                        'nodes' => [
                            0 => [
                                'id' => '2000000001',
                                //'automaticDiscount' => [  // MWS-642
                                'discount' => [
                                    '__typename' => 'DiscountAutomaticApp',
                                    // ... on DiscountAutomaticBxgy
                                    // ... on DiscountAutomaticBasic
                                    // ... on DiscountAutomaticApp
                                    'startsAt' => '',
                                    'endsAt' => '',
                                    'title' => 'DiscountTitle201',
                                    'discountClass' => 'PRODUCT',
                                    // ... on DiscountAutomaticApp
                                    'status' => 'ACTIVE',
                                    'combinesWith' => [
                                        'productDiscounts' => true,
                                    ],
                                    // ... on DiscountAutomaticBxgy
                                    /*
                                    'customerGets' => [
                                        'items' => [
                                            // ... on DiscountCollections
                                            'collections' => [
                                                'nodes' => [
                                                    0 => [
                                                        'title' => 'CollectionTitle204',
                                                        'id' => '2000000004',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                            // ... on DiscountProducts
                                            'products' => [
                                                'nodes' => [
                                                    0 => [
                                                        'id' => '2000000005',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '__typename' => 'DiscountAmount',
                                            // ... on DiscountAmount
                                            'amount' => [
                                                'amount' => '2.01',
                                                'currencyCode' => 'USD',
                                            ],
                                            // ... on DiscountOnQuantity
                                            'quantity' => [
                                                'quantity' => '202',
                                            ],
                                            'effect' => [
                                                '__typename' => 'DiscountPercentage',
                                                // ... on DiscountPercentage
                                                'percentage' => '2.03',
                                            ],
                                            // ... on DiscountPercentage
                                            'percentage' => '2.04',
                                        ],
                                    ],
                                    */
                                    /*
                                    'customerBuys' => [
                                        'items' => [
                                            // ... on DiscountCollections
                                            'collections' => [
                                                'nodes' => [
                                                    0 => [
                                                        'title' => 'CollectionTitle202',
                                                        'id' => '2000000002',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                            // ... on DiscountProducts
                                            'products' => [
                                                'nodes' => [
                                                    0 => [
                                                        'id' => '2000000003',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '__typename' => 'DiscountPurchaseAmount',
                                            // ... on DiscountPurchaseAmount
                                            'amount' => '2.05',
                                            // ... on DiscountQuantity
                                            'quantity' => '206',
                                        ],
                                    ],
                                    */
                                    /*
                                    // ... on DiscountAutomaticBasic
                                    'customerGets' => [
                                        'items' => [
                                            // ... on DiscountCollections
                                            'collections' => [
                                                'nodes' => [
                                                    0 => [
                                                        'title' => 'CollectionTitle204',
                                                        'id' => '2000000004',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                            // ... on DiscountProducts
                                            'products' => [
                                                'nodes' => [
                                                    0 => [
                                                        'id' => '2000000005',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '__typename' => 'DiscountAmount',
                                            // ... on DiscountAmount
                                            'amount' => [
                                                'amount' => '2.07',
                                                'currencyCode' => 'USD',
                                            ],
                                            // ... on DiscountOnQuantity
                                            'quantity' => [
                                                'quantity' => '208',
                                            ],
                                            'effect' => [
                                                '__typename' => 'DiscountPercentage',
                                                // ... on DiscountPercentage
                                                'percentage' => '2.09',
                                            ],
                                            // ... on DiscountPercentage
                                            'percentage' => '2.10',
                                        ],
                                    ],
                                    'minimumRequirement' => [
                                        '__typename' => 'DiscountMinimumQuantity',
                                        // ... on DiscountMinimumQuantity
                                        'greaterThanOrEqualToQuantity' => '211',
                                        // ... on DiscountMinimumSubtotal
                                        'greaterThanOrEqualToSubtotal' => [
                                            'amount' => '2.12',
                                            'currencyCode' => 'USD',
                                        ],
                                    ],
                                    */
                                ],
                            ],
                        ],
                        'pageInfo' => [
                            'hasNextPage' => false,
                            'endCursor' => null,
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getMetafieldsSetQuery()) => [
                'data' => [
                    'metafieldsSet' => [
                        'metafields' => [
                            0 => [
                                'key' => '',
                                'namespace' => '',
                                'value' => '',
                                'createdAt' => '',
                                'updatedAt' => '',
                            ],
                        ],
                        'userErrors' => [
                            /*
                            0 => [
                                'field' => '',
                                'message' => '',
                                'code' => '',
                            ],
                            */
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBasicQueryByDiscountId(null, true)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '3000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBasic',
                            // ... on DiscountAutomaticBasic
                            'id' => '3000000002',
                            'title' => 'DiscountTitle302',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '3000000003',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '3.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '302',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '3.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '3.04',
                                ],
                            ],
                            'minimumRequirement' => [
                                '__typename' => 'DiscountMinimumQuantity',
                                // ... on DiscountMinimumQuantity
                                'greaterThanOrEqualToQuantity' => '305',
                                // ... on DiscountMinimumSubtotal
                                'greaterThanOrEqualToSubtotal' => [
                                    'amount' => '3.06',
                                    'currencyCode' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBasicQueryByDiscountId(null, false)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '4000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBasic',
                            // ... on DiscountAutomaticBasic
                            'id' => '4000000002',
                            'title' => 'DiscountTitle402',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle403',
                                                'id' => '4000000003',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '4.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '402',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '4.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '4.04',
                                ],
                            ],
                            'minimumRequirement' => [
                                '__typename' => 'DiscountMinimumQuantity',
                                // ... on DiscountMinimumQuantity
                                'greaterThanOrEqualToQuantity' => '405',
                                // ... on DiscountMinimumSubtotal
                                'greaterThanOrEqualToSubtotal' => [
                                    'amount' => '4.06',
                                    'currencyCode' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBxgyQueryByDiscountId(null, true)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '5000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBxgy',
                            // ... on DiscountAutomaticBxgy
                            'title' => 'DiscountTitle501',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '5.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '502',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '5.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '5.04',
                                ],
                            ],
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '5000000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountPurchaseAmount',
                                    // ... on DiscountPurchaseAmount
                                    'amount' => '5.05',
                                    // ... on DiscountQuantity
                                    'quantity' => '506',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBxgyQueryByDiscountId(null, false)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '6000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBxgy',
                            // ... on DiscountAutomaticBxgy
                            'title' => 'DiscountTitle601',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '6.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '602',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '6.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '6.04',
                                ],
                            ],
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle602',
                                                'id' => '6000000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountPurchaseAmount',
                                    // ... on DiscountPurchaseAmount
                                    'amount' => '6.05',
                                    // ... on DiscountQuantity
                                    'quantity' => '606',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getCollectionQueryByCollectionId()) => [
                'data' => [
                    'collection' => [
                        'products' => [
                            'nodes' => [
                                0 => [
                                    'id' => '7000000001',
                                ],
                            ],
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getCommentEventQuery()) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'events' => [
                            'nodes' => [
                                0 => [
                                    'message' => 'PROMO_TITLE: CommentEvent801',
                                ],
                                1 => [
                                    'message' => 'PROMO_BODY: CommentEvent802',
                                ],
                            ],
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBasicQueryByDiscountId('products', null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1100000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBasic
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '1100000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBasicQueryByDiscountId(null, null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1200000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBasic
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle122',
                                                'id' => '1200000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBxgyQueryByDiscountId('products', null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1300000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBxgy
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '1300000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBxgyQueryByDiscountId(null, null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1400000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBxgy
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle142',
                                                'id' => '1400000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getProductVariantQueryByIds()) => [
                'data' => [
                    'nodes' => [
                        0 => [
                            'id' => '1500000101',
                            'product' => [
                                'id' => '1500000111',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $command = new AddDiscountsToProductsCommand($graphQLQueryHelper, $shopifySdk, $multiscountClient);

        $refl = new \ReflectionProperty(get_class($command), 'shopifySDK');
        $refl->setAccessible(true);
        $refl->setValue($command, $mockShopify);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Start applying discounts to products', $output);
        $this->assertStringContainsString('Start querying products in "Eligible for Discount" collection...', $output);
        $this->assertStringContainsString('Successfully updated 3 products...', $output);
        $this->assertStringContainsString('Successfully apply discounts to 3 products', $output);

        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IGF1dG9tYXRpY0Rpc2NvdW50UXVlcnkoJHBlclBhZ2U6IEludCEpIHsgZGlzY291bnROb2RlcyAoZmlyc3Q6ICRwZXJQYWdlLCBxdWVyeTogXCJtZXRob2Q6YXV0b21hdGljIEFORCAoc3RhdHVzOmFjdGl2ZSBPUiBzdGF0dXM6c2NoZWR1bGVkKVwiICkgeyBub2RlcyB7IGlkIGRpc2NvdW50IHsgX190eXBlbmFtZSAuLi4gb24gRGlzY291bnRBdXRvbWF0aWNCeGd5IHsgc3RhcnRzQXQgZW5kc0F0IHRpdGxlIGRpc2NvdW50Q2xhc3MgY29tYmluZXNXaXRoIHsgcHJvZHVjdERpc2NvdW50cyB9IGN1c3RvbWVyR2V0cyB7IGl0ZW1zIHsgLi4uIG9uIERpc2NvdW50Q29sbGVjdGlvbnMgeyBjb2xsZWN0aW9ucyAoZmlyc3Q6IDEwICkgeyBub2RlcyB7IHRpdGxlIGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gLi4uIG9uIERpc2NvdW50UHJvZHVjdHMgeyBwcm9kdWN0cyAoZmlyc3Q6IDEwICkgeyBub2RlcyB7IGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gfSB2YWx1ZSB7IF9fdHlwZW5hbWUgLi4uIG9uIERpc2NvdW50QW1vdW50IHsgYW1vdW50IHsgYW1vdW50IGN1cnJlbmN5Q29kZSB9IH0gLi4uIG9uIERpc2NvdW50T25RdWFudGl0eSB7IHF1YW50aXR5IHsgcXVhbnRpdHkgfSBlZmZlY3QgeyBfX3R5cGVuYW1lIC4uLiBvbiBEaXNjb3VudFBlcmNlbnRhZ2UgeyBwZXJjZW50YWdlIH0gfSB9IC4uLiBvbiBEaXNjb3VudFBlcmNlbnRhZ2UgeyBwZXJjZW50YWdlIH0gfSB9IGN1c3RvbWVyQnV5cyB7IGl0ZW1zIHsgLi4uIG9uIERpc2NvdW50Q29sbGVjdGlvbnMgeyBjb2xsZWN0aW9ucyAoZmlyc3Q6ICRwZXJQYWdlICkgeyBub2RlcyB7IHRpdGxlIGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gLi4uIG9uIERpc2NvdW50UHJvZHVjdHMgeyBwcm9kdWN0cyAoZmlyc3Q6ICRwZXJQYWdlICkgeyBub2RlcyB7IGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gfSB2YWx1ZSB7IF9fdHlwZW5hbWUgLi4uIG9uIERpc2NvdW50UHVyY2hhc2VBbW91bnQgeyBhbW91bnQgfSAuLi4gb24gRGlzY291bnRRdWFudGl0eSB7IHF1YW50aXR5IH0gfSB9IH0gLi4uIG9uIERpc2NvdW50QXV0b21hdGljQmFzaWMgeyBzdGFydHNBdCBlbmRzQXQgdGl0bGUgZGlzY291bnRDbGFzcyBjdXN0b21lckdldHMgeyBpdGVtcyB7IC4uLiBvbiBEaXNjb3VudENvbGxlY3Rpb25zIHsgY29sbGVjdGlvbnMgKGZpcnN0OiAkcGVyUGFnZSApIHsgbm9kZXMgeyB0aXRsZSBpZCB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IC4uLiBvbiBEaXNjb3VudFByb2R1Y3RzIHsgcHJvZHVjdHMgKGZpcnN0OiAkcGVyUGFnZSApIHsgbm9kZXMgeyBpZCB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IH0gdmFsdWUgeyBfX3R5cGVuYW1lIC4uLiBvbiBEaXNjb3VudEFtb3VudCB7IGFtb3VudCB7IGFtb3VudCBjdXJyZW5jeUNvZGUgfSB9IC4uLiBvbiBEaXNjb3VudE9uUXVhbnRpdHkgeyBxdWFudGl0eSB7IHF1YW50aXR5IH0gZWZmZWN0IHsgX190eXBlbmFtZSAuLi4gb24gRGlzY291bnRQZXJjZW50YWdlIHsgcGVyY2VudGFnZSB9IH0gfSAuLi4gb24gRGlzY291bnRQZXJjZW50YWdlIHsgcGVyY2VudGFnZSB9IH0gfSBtaW5pbXVtUmVxdWlyZW1lbnQgeyBfX3R5cGVuYW1lIC4uLiBvbiBEaXNjb3VudE1pbmltdW1RdWFudGl0eSB7IGdyZWF0ZXJUaGFuT3JFcXVhbFRvUXVhbnRpdHkgfSAuLi4gb24gRGlzY291bnRNaW5pbXVtU3VidG90YWwgeyBncmVhdGVyVGhhbk9yRXF1YWxUb1N1YnRvdGFsIHsgYW1vdW50IGN1cnJlbmN5Q29kZSB9IH0gfSB9IC4uLiBvbiBEaXNjb3VudEF1dG9tYXRpY0FwcCB7IHN0YXJ0c0F0IGVuZHNBdCB0aXRsZSBkaXNjb3VudENsYXNzIHN0YXR1cyBjb21iaW5lc1dpdGggeyBwcm9kdWN0RGlzY291bnRzIH0gfSB9IH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0i'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IGNvbW1lbnRFdmVudFF1ZXJ5KCRwZXJQYWdlOiBJbnQhLCAkZGlzY291bnRJZDogSUQhKSB7IGF1dG9tYXRpY0Rpc2NvdW50Tm9kZSAoaWQ6ICRkaXNjb3VudElkKSB7IGV2ZW50cyAoZmlyc3Q6ICRwZXJQYWdlKSB7IG5vZGVzIHsgbWVzc2FnZSB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IH0i'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IHByb2R1Y3RzQnlDb2xsZWN0aW9uKCRjb2xsZWN0aW9uTmFtZTogU3RyaW5nISwgJHBlclBhZ2U6IEludCEpIHsgY29sbGVjdGlvbkJ5SGFuZGxlKGhhbmRsZTogJGNvbGxlY3Rpb25OYW1lKSB7IHByb2R1Y3RzKGZpcnN0OiAkcGVyUGFnZSkgeyBub2RlcyB7IGlkIHZlbmRvciBlbGlnaWJsZUZvckRpc2NvdW50OiBtZXRhZmllbGQobmFtZXNwYWNlOiBcIm13X21hcmtldGluZ1wiIGtleTogXCJlbGlnaWJsZV9mb3JfZGlzY291bnRcIikgeyB2YWx1ZSB9IH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gfSI='));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsIm11dGF0aW9uIE1ldGFmaWVsZHNTZXQoJG1ldGFmaWVsZHM6IFtNZXRhZmllbGRzU2V0SW5wdXQhXSEpIHsgbWV0YWZpZWxkc1NldChtZXRhZmllbGRzOiAkbWV0YWZpZWxkcykgeyBtZXRhZmllbGRzIHsga2V5IG5hbWVzcGFjZSB2YWx1ZSBjcmVhdGVkQXQgdXBkYXRlZEF0IH0gdXNlckVycm9ycyB7IGZpZWxkIG1lc3NhZ2UgY29kZSB9IH0gfSI='));

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[0]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query automaticDiscountQuery($perPage: Int!) {
            discountNodes (first: $perPage, query: "method:automatic AND (status:active OR status:scheduled)" ) {
              nodes {
                id
                discount {
                  __typename
                  ... on DiscountAutomaticBxgy {
                    startsAt
                    endsAt
                    title
                    discountClass
                    combinesWith {
                      productDiscounts
                    }
                    customerGets {
                      items {
                        ... on DiscountCollections {
                          collections (first: 10 ) {
                            nodes {
                              title
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                        ... on DiscountProducts {
                          products (first: 10 ) {
                            nodes {
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                      }
                      value {
                        __typename
                        ... on DiscountAmount {
                          amount {
                            amount
                            currencyCode
                          }
                        }
                        ... on DiscountOnQuantity {
                          quantity {
                            quantity
                          }
                          effect {
                            __typename
                            ... on DiscountPercentage {
                              percentage
                            }
                          }
                        }
                        ... on DiscountPercentage {
                          percentage
                        }
                      }
                    }
                    customerBuys {
                      items {
                        ... on DiscountCollections {
                          collections (first: $perPage ) {
                            nodes {
                              title
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                        ... on DiscountProducts {
                          products (first: $perPage ) {
                            nodes {
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                      }
                      value {
                        __typename
                        ... on DiscountPurchaseAmount {
                          amount
                        }
                        ... on DiscountQuantity {
                          quantity
                        }
                      }
                    }
                  }
                  ... on DiscountAutomaticBasic {
                    startsAt
                    endsAt
                    title
                    discountClass
                    customerGets {
                      items {
                        ... on DiscountCollections {
                          collections (first: $perPage ) {
                            nodes {
                              title
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                        ... on DiscountProducts {
                          products (first: $perPage ) {
                            nodes {
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                      }
                      value {
                        __typename
                        ... on DiscountAmount {
                          amount {
                            amount
                            currencyCode
                          }
                        }
                        ... on DiscountOnQuantity {
                          quantity {
                            quantity
                          }
                          effect {
                            __typename
                            ... on DiscountPercentage {
                              percentage
                            }
                          }
                        }
                        ... on DiscountPercentage {
                          percentage
                        }
                      }
                    }
                    minimumRequirement {
                      __typename
                      ... on DiscountMinimumQuantity {
                        greaterThanOrEqualToQuantity
                      }
                      ... on DiscountMinimumSubtotal {
                        greaterThanOrEqualToSubtotal {
                          amount
                          currencyCode
                        }
                      }
                    }
                  }
                  ... on DiscountAutomaticApp {
                    startsAt
                    endsAt
                    title
                    discountClass
                    status
                    combinesWith {
                      productDiscounts
                    }
                  }
                }
              }
              pageInfo {
                hasNextPage
                endCursor
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[0]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 20,
            'endCursor' => null,
        ], $this->getLogger()->records[0]['context']['request']['variables']);
        //$this->assertEquals('2000000001', $this->getLogger()->records[0]['context']['response']['data']['automaticDiscountNodes']['nodes'][0]['id']);  // MWS-642
        $this->assertEquals('2000000001', $this->getLogger()->records[0]['context']['response']['data']['discountNodes']['nodes'][0]['id']);

        //$this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[1]['message']);
        //$this->assertEquals($mockShopify->formatGraphQLQueryString('
        //  query discountBxgyQueryByDiscountId($perPage: Int!, $discountId: ID!) {
        //    automaticDiscountNode (id: $discountId) {
        //      automaticDiscount {
        //        ... on DiscountAutomaticBxgy {
        //          customerBuys {
        //            items {
        //              ... on DiscountCollections {
        //                collections (first: $perPage ) {
        //                  nodes {
        //                    title
        //                    id
        //                  }
        //                  pageInfo {
        //                    hasNextPage
        //                    endCursor
        //                  }
        //                }
        //              }
        //            }
        //          }
        //        }
        //      }
        //    }
        //  }
        //'), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[1]['context']['request']['query']));
        //$this->assertSame([
        //    'perPage' => 250,
        //    'endCursor' => null,
        //    'discountId' => '2000000001',
        //], $this->getLogger()->records[1]['context']['request']['variables']);
        //$this->assertEquals('CollectionTitle142', $this->getLogger()->records[1]['context']['response']['data']['automaticDiscountNode']['automaticDiscount']['customerBuys']['items']['collections']['nodes'][0]['title']);
        //$this->assertEquals('1400000002', $this->getLogger()->records[1]['context']['response']['data']['automaticDiscountNode']['automaticDiscount']['customerBuys']['items']['collections']['nodes'][0]['id']);
        //
        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[1]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query commentEventQuery($perPage: Int!, $discountId: ID!) {
            automaticDiscountNode (id: $discountId) {
              events (first: $perPage) {
                nodes {
                  message
                }
                pageInfo {
                  hasNextPage
                  endCursor
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[1]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 50,
            'discountId' => '2000000001',
            'endCursor' => null,
        ], $this->getLogger()->records[1]['context']['request']['variables']);
        $this->assertEquals('PROMO_TITLE: CommentEvent801', $this->getLogger()->records[1]['context']['response']['data']['automaticDiscountNode']['events']['nodes'][0]['message']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[2]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query collectionQueryByCollectionId($collectionId: ID!, $perPage: Int!) {
            collection(id: $collectionId) {
              products(first: $perPage) {
                nodes {
                  id
                }
                pageInfo {
                  hasNextPage
                  endCursor
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[2]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 20,
            'collectionId' => 'gid://shopify/Collection/2000000004',
            'endCursor' => null,
        ], $this->getLogger()->records[2]['context']['request']['variables']);
        $this->assertEquals('7000000001', $this->getLogger()->records[2]['context']['response']['data']['collection']['products']['nodes'][0]['id']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[3]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query productsByCollection($collectionName: String!, $perPage: Int!) {
            collectionByHandle(handle: $collectionName) {
              products(first: $perPage) {
                nodes {
                  id
                  vendor
                  eligibleForDiscount: metafield(namespace: "mw_marketing" key: "eligible_for_discount") {
                    value
                  }
                }
                pageInfo {
                  hasNextPage
                  endCursor
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[3]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 20,
            'endCursor' => null,
            'collectionName' => 'eligible-for-discount',
        ], $this->getLogger()->records[3]['context']['request']['variables']);
        $this->assertEquals('1000000001', $this->getLogger()->records[3]['context']['response']['data']['collectionByHandle']['products']['nodes'][0]['id']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[4]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          mutation MetafieldsSet($metafields: [MetafieldsSetInput!]!) {
            metafieldsSet(metafields: $metafields) {
              metafields {
                key
                namespace
                value
                createdAt
                updatedAt
              }
              userErrors {
                field
                message
                code
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[4]['context']['request']['query']));
        $this->assertSame([
            'metafields' => [
                0 => [
                    'key' => 'applicable_discounts',
                    'namespace' => 'mw_marketing',
                    'ownerId' => 'gid://shopify/Product/2000000005',
                    'type' => 'json',
                    'value' => '[{"discountTitle":"DiscountTitle201","discountId":"2000000001","collectionTitle":"","collectionBody":"","qualified":"","unQualified":"","promoTitle":"CommentEvent801","promoBody":"CommentEvent802","combinesWithProductDiscounts":true,"startsAt":null,"endsAt":null,"timezone":"UTC","discountType":"DiscountAutomaticApp","minimumRequirement":null,"discountValue":null,"customerBuysValue":null,"customerGetsItems":null}]',
                ],
                1 => [
                    'key' => 'eligible_for_discount',
                    'namespace' => 'mw_marketing',
                    'ownerId' => 'gid://shopify/Product/2000000005',
                    'type' => 'boolean',
                    'value' => 'true',
                ],
                2 => [
                    'key' => 'applicable_discounts',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '7000000001',
                    'type' => 'json',
                    'value' => '[{"discountTitle":"DiscountTitle201","discountId":"2000000001","collectionTitle":"","collectionBody":"","qualified":"","unQualified":"","promoTitle":"CommentEvent801","promoBody":"CommentEvent802","combinesWithProductDiscounts":true,"startsAt":null,"endsAt":null,"timezone":"UTC","discountType":"DiscountAutomaticApp","minimumRequirement":null,"discountValue":null,"customerBuysValue":null,"customerGetsItems":null}]',
                ],
                3 => [
                    'key' => 'eligible_for_discount',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '7000000001',
                    'type' => 'boolean',
                    'value' => 'true',
                ],
                4 => [
                    'key' => 'eligible_for_discount',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '1000000001',
                    'type' => 'boolean',
                    'value' => 'false',
                ],
                5 => [
                    'key' => 'applicable_discounts',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '1000000001',
                    'type' => 'json',
                    'value' => '[]',
                ],
            ],
        ], $this->getLogger()->records[4]['context']['request']['variables']);
        $this->assertEquals([], $this->getLogger()->records[4]['context']['response']['data']['metafieldsSet']['userErrors']);
    }

    public function testExecuteAddDiscountsToProductsForDiscountAutomaticAppWithStatusActiveAndContainsProductVariant()
    {
        $kernel = static::createKernel();

        $multiscountClient = new MultiscountClient($this->getHttpClient([
            '/apps/multiscount/v2/discounts|POST|title=DiscountTitle201&type=volume' => [
                0 => [
                    'products' => [
                        'gid://shopify/Collection/2000000004',
                        'gid://shopify/Product/2000000005',
                        'gid://shopify/ProductVariant/2000000006',
                    ],
                ],
            ],
        ]));
        $graphQLQueryHelper = new GraphQLQueryHelper();
        $shopifySdk = new ShopifySDK([
            'ShopUrl' => '',
            'AccessToken' => '',
        ]);
        $mockMethods = new MockMethods();
        $mockMethods->setLogger($this->getLogger());
        $mockShopify = new AddDiscountsToProductsCommandTestLocalMockShopify();
        $mockShopify->setMockMethods($mockMethods);
        $mockShopify->setMockData([
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getProductsQueryByCollection()) => [
                'data' => [
                    'collectionByHandle' => [
                        'products' => [
                            'nodes' => [
                                0 => [
                                    'id' => '1000000001',
                                    'vendor' => 'GreatVendor',
                                    'eligibleForDiscount' => [
                                        'value' => 'true',
                                    ],
                                ],
                            ],
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountQuery()) => [
                'data' => [
                    //'automaticDiscountNodes' => [  // MWS-642
                    'discountNodes' => [
                        'nodes' => [
                            0 => [
                                'id' => '2000000001',
                                //'automaticDiscount' => [  // MWS-642
                                'discount' => [
                                    '__typename' => 'DiscountAutomaticApp',
                                    // ... on DiscountAutomaticBxgy
                                    // ... on DiscountAutomaticBasic
                                    // ... on DiscountAutomaticApp
                                    'startsAt' => '',
                                    'endsAt' => '',
                                    'title' => 'DiscountTitle201',
                                    'discountClass' => 'PRODUCT',
                                    // ... on DiscountAutomaticApp
                                    'status' => 'ACTIVE',
                                    'combinesWith' => [
                                        'productDiscounts' => true,
                                    ],
                                    // ... on DiscountAutomaticBxgy
                                    /*
                                    'customerGets' => [
                                        'items' => [
                                            // ... on DiscountCollections
                                            'collections' => [
                                                'nodes' => [
                                                    0 => [
                                                        'title' => 'CollectionTitle204',
                                                        'id' => '2000000004',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                            // ... on DiscountProducts
                                            'products' => [
                                                'nodes' => [
                                                    0 => [
                                                        'id' => '2000000005',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '__typename' => 'DiscountAmount',
                                            // ... on DiscountAmount
                                            'amount' => [
                                                'amount' => '2.01',
                                                'currencyCode' => 'USD',
                                            ],
                                            // ... on DiscountOnQuantity
                                            'quantity' => [
                                                'quantity' => '202',
                                            ],
                                            'effect' => [
                                                '__typename' => 'DiscountPercentage',
                                                // ... on DiscountPercentage
                                                'percentage' => '2.03',
                                            ],
                                            // ... on DiscountPercentage
                                            'percentage' => '2.04',
                                        ],
                                    ],
                                    */
                                    /*
                                    'customerBuys' => [
                                        'items' => [
                                            // ... on DiscountCollections
                                            'collections' => [
                                                'nodes' => [
                                                    0 => [
                                                        'title' => 'CollectionTitle202',
                                                        'id' => '2000000002',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                            // ... on DiscountProducts
                                            'products' => [
                                                'nodes' => [
                                                    0 => [
                                                        'id' => '2000000003',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '__typename' => 'DiscountPurchaseAmount',
                                            // ... on DiscountPurchaseAmount
                                            'amount' => '2.05',
                                            // ... on DiscountQuantity
                                            'quantity' => '206',
                                        ],
                                    ],
                                    */
                                    /*
                                    // ... on DiscountAutomaticBasic
                                    'customerGets' => [
                                        'items' => [
                                            // ... on DiscountCollections
                                            'collections' => [
                                                'nodes' => [
                                                    0 => [
                                                        'title' => 'CollectionTitle204',
                                                        'id' => '2000000004',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                            // ... on DiscountProducts
                                            'products' => [
                                                'nodes' => [
                                                    0 => [
                                                        'id' => '2000000005',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '__typename' => 'DiscountAmount',
                                            // ... on DiscountAmount
                                            'amount' => [
                                                'amount' => '2.07',
                                                'currencyCode' => 'USD',
                                            ],
                                            // ... on DiscountOnQuantity
                                            'quantity' => [
                                                'quantity' => '208',
                                            ],
                                            'effect' => [
                                                '__typename' => 'DiscountPercentage',
                                                // ... on DiscountPercentage
                                                'percentage' => '2.09',
                                            ],
                                            // ... on DiscountPercentage
                                            'percentage' => '2.10',
                                        ],
                                    ],
                                    'minimumRequirement' => [
                                        '__typename' => 'DiscountMinimumQuantity',
                                        // ... on DiscountMinimumQuantity
                                        'greaterThanOrEqualToQuantity' => '211',
                                        // ... on DiscountMinimumSubtotal
                                        'greaterThanOrEqualToSubtotal' => [
                                            'amount' => '2.12',
                                            'currencyCode' => 'USD',
                                        ],
                                    ],
                                    */
                                ],
                            ],
                        ],
                        'pageInfo' => [
                            'hasNextPage' => false,
                            'endCursor' => null,
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getMetafieldsSetQuery()) => [
                'data' => [
                    'metafieldsSet' => [
                        'metafields' => [
                            0 => [
                                'key' => '',
                                'namespace' => '',
                                'value' => '',
                                'createdAt' => '',
                                'updatedAt' => '',
                            ],
                        ],
                        'userErrors' => [
                            /*
                            0 => [
                                'field' => '',
                                'message' => '',
                                'code' => '',
                            ],
                            */
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBasicQueryByDiscountId(null, true)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '3000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBasic',
                            // ... on DiscountAutomaticBasic
                            'id' => '3000000002',
                            'title' => 'DiscountTitle302',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '3000000003',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '3.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '302',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '3.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '3.04',
                                ],
                            ],
                            'minimumRequirement' => [
                                '__typename' => 'DiscountMinimumQuantity',
                                // ... on DiscountMinimumQuantity
                                'greaterThanOrEqualToQuantity' => '305',
                                // ... on DiscountMinimumSubtotal
                                'greaterThanOrEqualToSubtotal' => [
                                    'amount' => '3.06',
                                    'currencyCode' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBasicQueryByDiscountId(null, false)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '4000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBasic',
                            // ... on DiscountAutomaticBasic
                            'id' => '4000000002',
                            'title' => 'DiscountTitle402',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle403',
                                                'id' => '4000000003',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '4.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '402',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '4.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '4.04',
                                ],
                            ],
                            'minimumRequirement' => [
                                '__typename' => 'DiscountMinimumQuantity',
                                // ... on DiscountMinimumQuantity
                                'greaterThanOrEqualToQuantity' => '405',
                                // ... on DiscountMinimumSubtotal
                                'greaterThanOrEqualToSubtotal' => [
                                    'amount' => '4.06',
                                    'currencyCode' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBxgyQueryByDiscountId(null, true)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '5000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBxgy',
                            // ... on DiscountAutomaticBxgy
                            'title' => 'DiscountTitle501',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '5.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '502',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '5.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '5.04',
                                ],
                            ],
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '5000000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountPurchaseAmount',
                                    // ... on DiscountPurchaseAmount
                                    'amount' => '5.05',
                                    // ... on DiscountQuantity
                                    'quantity' => '506',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBxgyQueryByDiscountId(null, false)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '6000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBxgy',
                            // ... on DiscountAutomaticBxgy
                            'title' => 'DiscountTitle601',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '6.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '602',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '6.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '6.04',
                                ],
                            ],
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle602',
                                                'id' => '6000000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountPurchaseAmount',
                                    // ... on DiscountPurchaseAmount
                                    'amount' => '6.05',
                                    // ... on DiscountQuantity
                                    'quantity' => '606',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getCollectionQueryByCollectionId()) => [
                'data' => [
                    'collection' => [
                        'products' => [
                            'nodes' => [
                                0 => [
                                    'id' => '7000000001',
                                ],
                            ],
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getCommentEventQuery()) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'events' => [
                            'nodes' => [
                                0 => [
                                    'message' => 'PROMO_TITLE: CommentEvent801',
                                ],
                                1 => [
                                    'message' => 'PROMO_BODY: CommentEvent802',
                                ],
                            ],
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBasicQueryByDiscountId('products', null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1100000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBasic
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '1100000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBasicQueryByDiscountId(null, null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1200000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBasic
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle122',
                                                'id' => '1200000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBxgyQueryByDiscountId('products', null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1300000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBxgy
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '1300000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBxgyQueryByDiscountId(null, null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1400000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBxgy
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle142',
                                                'id' => '1400000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getProductVariantQueryByIds()) => [
                'data' => [
                    'nodes' => [
                        0 => [
                            'id' => 'gid://shopify/ProductVariant/2000000006',
                            'product' => [
                                'id' => 'gid://shopify/Product/2000000005', // same as initial data, checking for duplicate product IDs
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $command = new AddDiscountsToProductsCommand($graphQLQueryHelper, $shopifySdk, $multiscountClient);

        $refl = new \ReflectionProperty(get_class($command), 'shopifySDK');
        $refl->setAccessible(true);
        $refl->setValue($command, $mockShopify);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Start applying discounts to products', $output);
        $this->assertStringContainsString('Start querying products in "Eligible for Discount" collection...', $output);
        $this->assertStringContainsString('Successfully updated 3 products...', $output);
        $this->assertStringContainsString('Successfully apply discounts to 3 products', $output);

        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IGF1dG9tYXRpY0Rpc2NvdW50UXVlcnkoJHBlclBhZ2U6IEludCEpIHsgZGlzY291bnROb2RlcyAoZmlyc3Q6ICRwZXJQYWdlLCBxdWVyeTogXCJtZXRob2Q6YXV0b21hdGljIEFORCAoc3RhdHVzOmFjdGl2ZSBPUiBzdGF0dXM6c2NoZWR1bGVkKVwiICkgeyBub2RlcyB7IGlkIGRpc2NvdW50IHsgX190eXBlbmFtZSAuLi4gb24gRGlzY291bnRBdXRvbWF0aWNCeGd5IHsgc3RhcnRzQXQgZW5kc0F0IHRpdGxlIGRpc2NvdW50Q2xhc3MgY29tYmluZXNXaXRoIHsgcHJvZHVjdERpc2NvdW50cyB9IGN1c3RvbWVyR2V0cyB7IGl0ZW1zIHsgLi4uIG9uIERpc2NvdW50Q29sbGVjdGlvbnMgeyBjb2xsZWN0aW9ucyAoZmlyc3Q6IDEwICkgeyBub2RlcyB7IHRpdGxlIGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gLi4uIG9uIERpc2NvdW50UHJvZHVjdHMgeyBwcm9kdWN0cyAoZmlyc3Q6IDEwICkgeyBub2RlcyB7IGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gfSB2YWx1ZSB7IF9fdHlwZW5hbWUgLi4uIG9uIERpc2NvdW50QW1vdW50IHsgYW1vdW50IHsgYW1vdW50IGN1cnJlbmN5Q29kZSB9IH0gLi4uIG9uIERpc2NvdW50T25RdWFudGl0eSB7IHF1YW50aXR5IHsgcXVhbnRpdHkgfSBlZmZlY3QgeyBfX3R5cGVuYW1lIC4uLiBvbiBEaXNjb3VudFBlcmNlbnRhZ2UgeyBwZXJjZW50YWdlIH0gfSB9IC4uLiBvbiBEaXNjb3VudFBlcmNlbnRhZ2UgeyBwZXJjZW50YWdlIH0gfSB9IGN1c3RvbWVyQnV5cyB7IGl0ZW1zIHsgLi4uIG9uIERpc2NvdW50Q29sbGVjdGlvbnMgeyBjb2xsZWN0aW9ucyAoZmlyc3Q6ICRwZXJQYWdlICkgeyBub2RlcyB7IHRpdGxlIGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gLi4uIG9uIERpc2NvdW50UHJvZHVjdHMgeyBwcm9kdWN0cyAoZmlyc3Q6ICRwZXJQYWdlICkgeyBub2RlcyB7IGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gfSB2YWx1ZSB7IF9fdHlwZW5hbWUgLi4uIG9uIERpc2NvdW50UHVyY2hhc2VBbW91bnQgeyBhbW91bnQgfSAuLi4gb24gRGlzY291bnRRdWFudGl0eSB7IHF1YW50aXR5IH0gfSB9IH0gLi4uIG9uIERpc2NvdW50QXV0b21hdGljQmFzaWMgeyBzdGFydHNBdCBlbmRzQXQgdGl0bGUgZGlzY291bnRDbGFzcyBjdXN0b21lckdldHMgeyBpdGVtcyB7IC4uLiBvbiBEaXNjb3VudENvbGxlY3Rpb25zIHsgY29sbGVjdGlvbnMgKGZpcnN0OiAkcGVyUGFnZSApIHsgbm9kZXMgeyB0aXRsZSBpZCB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IC4uLiBvbiBEaXNjb3VudFByb2R1Y3RzIHsgcHJvZHVjdHMgKGZpcnN0OiAkcGVyUGFnZSApIHsgbm9kZXMgeyBpZCB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IH0gdmFsdWUgeyBfX3R5cGVuYW1lIC4uLiBvbiBEaXNjb3VudEFtb3VudCB7IGFtb3VudCB7IGFtb3VudCBjdXJyZW5jeUNvZGUgfSB9IC4uLiBvbiBEaXNjb3VudE9uUXVhbnRpdHkgeyBxdWFudGl0eSB7IHF1YW50aXR5IH0gZWZmZWN0IHsgX190eXBlbmFtZSAuLi4gb24gRGlzY291bnRQZXJjZW50YWdlIHsgcGVyY2VudGFnZSB9IH0gfSAuLi4gb24gRGlzY291bnRQZXJjZW50YWdlIHsgcGVyY2VudGFnZSB9IH0gfSBtaW5pbXVtUmVxdWlyZW1lbnQgeyBfX3R5cGVuYW1lIC4uLiBvbiBEaXNjb3VudE1pbmltdW1RdWFudGl0eSB7IGdyZWF0ZXJUaGFuT3JFcXVhbFRvUXVhbnRpdHkgfSAuLi4gb24gRGlzY291bnRNaW5pbXVtU3VidG90YWwgeyBncmVhdGVyVGhhbk9yRXF1YWxUb1N1YnRvdGFsIHsgYW1vdW50IGN1cnJlbmN5Q29kZSB9IH0gfSB9IC4uLiBvbiBEaXNjb3VudEF1dG9tYXRpY0FwcCB7IHN0YXJ0c0F0IGVuZHNBdCB0aXRsZSBkaXNjb3VudENsYXNzIHN0YXR1cyBjb21iaW5lc1dpdGggeyBwcm9kdWN0RGlzY291bnRzIH0gfSB9IH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0i'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IGNvbW1lbnRFdmVudFF1ZXJ5KCRwZXJQYWdlOiBJbnQhLCAkZGlzY291bnRJZDogSUQhKSB7IGF1dG9tYXRpY0Rpc2NvdW50Tm9kZSAoaWQ6ICRkaXNjb3VudElkKSB7IGV2ZW50cyAoZmlyc3Q6ICRwZXJQYWdlKSB7IG5vZGVzIHsgbWVzc2FnZSB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IH0i'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IHByb2R1Y3RWYXJpYW50c0J5SWRzKCRpZHM6IFtJRCFdISkgeyBub2RlcyhpZHM6ICRpZHMpIHsgaWQgLi4uIG9uIFByb2R1Y3QgeyB2YXJpYW50cyhmaXJzdDogMjUwKSB7IG5vZGVzIHsgaWQgc2t1IH0gfSB9IC4uLiBvbiBQcm9kdWN0VmFyaWFudCB7IHNrdSBwcm9kdWN0IHsgaWQgfSB9IH0gfSI='));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IHByb2R1Y3RzQnlDb2xsZWN0aW9uKCRjb2xsZWN0aW9uTmFtZTogU3RyaW5nISwgJHBlclBhZ2U6IEludCEpIHsgY29sbGVjdGlvbkJ5SGFuZGxlKGhhbmRsZTogJGNvbGxlY3Rpb25OYW1lKSB7IHByb2R1Y3RzKGZpcnN0OiAkcGVyUGFnZSkgeyBub2RlcyB7IGlkIHZlbmRvciBlbGlnaWJsZUZvckRpc2NvdW50OiBtZXRhZmllbGQobmFtZXNwYWNlOiBcIm13X21hcmtldGluZ1wiIGtleTogXCJlbGlnaWJsZV9mb3JfZGlzY291bnRcIikgeyB2YWx1ZSB9IH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gfSI='));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsIm11dGF0aW9uIE1ldGFmaWVsZHNTZXQoJG1ldGFmaWVsZHM6IFtNZXRhZmllbGRzU2V0SW5wdXQhXSEpIHsgbWV0YWZpZWxkc1NldChtZXRhZmllbGRzOiAkbWV0YWZpZWxkcykgeyBtZXRhZmllbGRzIHsga2V5IG5hbWVzcGFjZSB2YWx1ZSBjcmVhdGVkQXQgdXBkYXRlZEF0IH0gdXNlckVycm9ycyB7IGZpZWxkIG1lc3NhZ2UgY29kZSB9IH0gfSI='));

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[0]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query automaticDiscountQuery($perPage: Int!) {
            discountNodes (first: $perPage, query: "method:automatic AND (status:active OR status:scheduled)" ) {
              nodes {
                id
                discount {
                  __typename
                  ... on DiscountAutomaticBxgy {
                    startsAt
                    endsAt
                    title
                    discountClass
                    combinesWith {
                      productDiscounts
                    }
                    customerGets {
                      items {
                        ... on DiscountCollections {
                          collections (first: 10 ) {
                            nodes {
                              title
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                        ... on DiscountProducts {
                          products (first: 10 ) {
                            nodes {
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                      }
                      value {
                        __typename
                        ... on DiscountAmount {
                          amount {
                            amount
                            currencyCode
                          }
                        }
                        ... on DiscountOnQuantity {
                          quantity {
                            quantity
                          }
                          effect {
                            __typename
                            ... on DiscountPercentage {
                              percentage
                            }
                          }
                        }
                        ... on DiscountPercentage {
                          percentage
                        }
                      }
                    }
                    customerBuys {
                      items {
                        ... on DiscountCollections {
                          collections (first: $perPage ) {
                            nodes {
                              title
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                        ... on DiscountProducts {
                          products (first: $perPage ) {
                            nodes {
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                      }
                      value {
                        __typename
                        ... on DiscountPurchaseAmount {
                          amount
                        }
                        ... on DiscountQuantity {
                          quantity
                        }
                      }
                    }
                  }
                  ... on DiscountAutomaticBasic {
                    startsAt
                    endsAt
                    title
                    discountClass
                    customerGets {
                      items {
                        ... on DiscountCollections {
                          collections (first: $perPage ) {
                            nodes {
                              title
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                        ... on DiscountProducts {
                          products (first: $perPage ) {
                            nodes {
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                      }
                      value {
                        __typename
                        ... on DiscountAmount {
                          amount {
                            amount
                            currencyCode
                          }
                        }
                        ... on DiscountOnQuantity {
                          quantity {
                            quantity
                          }
                          effect {
                            __typename
                            ... on DiscountPercentage {
                              percentage
                            }
                          }
                        }
                        ... on DiscountPercentage {
                          percentage
                        }
                      }
                    }
                    minimumRequirement {
                      __typename
                      ... on DiscountMinimumQuantity {
                        greaterThanOrEqualToQuantity
                      }
                      ... on DiscountMinimumSubtotal {
                        greaterThanOrEqualToSubtotal {
                          amount
                          currencyCode
                        }
                      }
                    }
                  }
                  ... on DiscountAutomaticApp {
                    startsAt
                    endsAt
                    title
                    discountClass
                    status
                    combinesWith {
                      productDiscounts
                    }
                  }
                }
              }
              pageInfo {
                hasNextPage
                endCursor
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[0]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 20,
            'endCursor' => null,
        ], $this->getLogger()->records[0]['context']['request']['variables']);
        //$this->assertEquals('2000000001', $this->getLogger()->records[0]['context']['response']['data']['automaticDiscountNodes']['nodes'][0]['id']);  // MWS-642
        $this->assertEquals('2000000001', $this->getLogger()->records[0]['context']['response']['data']['discountNodes']['nodes'][0]['id']);

        //$this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[1]['message']);
        //$this->assertEquals($mockShopify->formatGraphQLQueryString('
        //  query discountBxgyQueryByDiscountId($perPage: Int!, $discountId: ID!) {
        //    automaticDiscountNode (id: $discountId) {
        //      automaticDiscount {
        //        ... on DiscountAutomaticBxgy {
        //          customerBuys {
        //            items {
        //              ... on DiscountCollections {
        //                collections (first: $perPage ) {
        //                  nodes {
        //                    title
        //                    id
        //                  }
        //                  pageInfo {
        //                    hasNextPage
        //                    endCursor
        //                  }
        //                }
        //              }
        //            }
        //          }
        //        }
        //      }
        //    }
        //  }
        //'), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[1]['context']['request']['query']));
        //$this->assertSame([
        //    'perPage' => 250,
        //    'endCursor' => null,
        //    'discountId' => '2000000001',
        //], $this->getLogger()->records[1]['context']['request']['variables']);
        //$this->assertEquals('CollectionTitle142', $this->getLogger()->records[1]['context']['response']['data']['automaticDiscountNode']['automaticDiscount']['customerBuys']['items']['collections']['nodes'][0]['title']);
        //$this->assertEquals('1400000002', $this->getLogger()->records[1]['context']['response']['data']['automaticDiscountNode']['automaticDiscount']['customerBuys']['items']['collections']['nodes'][0]['id']);
        //
        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[1]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query commentEventQuery($perPage: Int!, $discountId: ID!) {
            automaticDiscountNode (id: $discountId) {
              events (first: $perPage) {
                nodes {
                  message
                }
                pageInfo {
                  hasNextPage
                  endCursor
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[1]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 50,
            'discountId' => '2000000001',
            'endCursor' => null,
        ], $this->getLogger()->records[1]['context']['request']['variables']);
        $this->assertEquals('PROMO_TITLE: CommentEvent801', $this->getLogger()->records[1]['context']['response']['data']['automaticDiscountNode']['events']['nodes'][0]['message']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[2]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query productVariantsByIds($ids: [ID!]!) {
            nodes(ids: $ids) {
              id
              ... on Product {
                variants(first: 250) {
                  nodes {
                    id
                    sku
                  }
                }
              }
              ... on ProductVariant {
                sku
                product {
                  id
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[2]['context']['request']['query']));
        $this->assertSame([
            'ids' => [
                0 => 'gid://shopify/Product/2000000005',
                1 => 'gid://shopify/ProductVariant/2000000006',
            ],
        ], $this->getLogger()->records[2]['context']['request']['variables']);
        $this->assertEquals('gid://shopify/ProductVariant/2000000006', $this->getLogger()->records[2]['context']['response']['data']['nodes'][0]['id']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[3]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query collectionQueryByCollectionId($collectionId: ID!, $perPage: Int!) {
            collection(id: $collectionId) {
              products(first: $perPage) {
                nodes {
                  id
                }
                pageInfo {
                  hasNextPage
                  endCursor
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[3]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 20,
            'collectionId' => 'gid://shopify/Collection/2000000004',
            'endCursor' => null,
        ], $this->getLogger()->records[3]['context']['request']['variables']);
        $this->assertEquals('7000000001', $this->getLogger()->records[3]['context']['response']['data']['collection']['products']['nodes'][0]['id']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[4]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query productsByCollection($collectionName: String!, $perPage: Int!) {
            collectionByHandle(handle: $collectionName) {
              products(first: $perPage) {
                nodes {
                  id
                  vendor
                  eligibleForDiscount: metafield(namespace: "mw_marketing" key: "eligible_for_discount") {
                    value
                  }
                }
                pageInfo {
                  hasNextPage
                  endCursor
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[4]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 20,
            'endCursor' => null,
            'collectionName' => 'eligible-for-discount',
        ], $this->getLogger()->records[4]['context']['request']['variables']);
        $this->assertEquals('1000000001', $this->getLogger()->records[4]['context']['response']['data']['collectionByHandle']['products']['nodes'][0]['id']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[5]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          mutation MetafieldsSet($metafields: [MetafieldsSetInput!]!) {
            metafieldsSet(metafields: $metafields) {
              metafields {
                key
                namespace
                value
                createdAt
                updatedAt
              }
              userErrors {
                field
                message
                code
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[5]['context']['request']['query']));
        $this->assertSame([
            'metafields' => [
                0 => [
                    'key' => 'applicable_discounts',
                    'namespace' => 'mw_marketing',
                    'ownerId' => 'gid://shopify/Product/2000000005',
                    'type' => 'json',
                    'value' => '[{"discountTitle":"DiscountTitle201","discountId":"2000000001","collectionTitle":"","collectionBody":"","qualified":"","unQualified":"","promoTitle":"CommentEvent801","promoBody":"CommentEvent802","combinesWithProductDiscounts":true,"startsAt":null,"endsAt":null,"timezone":"UTC","discountType":"DiscountAutomaticApp","minimumRequirement":null,"discountValue":null,"customerBuysValue":null,"customerGetsItems":null}]',
                ],
                1 => [
                    'key' => 'eligible_for_discount',
                    'namespace' => 'mw_marketing',
                    'ownerId' => 'gid://shopify/Product/2000000005',
                    'type' => 'boolean',
                    'value' => 'true',
                ],
                2 => [
                    'key' => 'applicable_discounts',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '7000000001',
                    'type' => 'json',
                    'value' => '[{"discountTitle":"DiscountTitle201","discountId":"2000000001","collectionTitle":"","collectionBody":"","qualified":"","unQualified":"","promoTitle":"CommentEvent801","promoBody":"CommentEvent802","combinesWithProductDiscounts":true,"startsAt":null,"endsAt":null,"timezone":"UTC","discountType":"DiscountAutomaticApp","minimumRequirement":null,"discountValue":null,"customerBuysValue":null,"customerGetsItems":null}]',
                ],
                3 => [
                    'key' => 'eligible_for_discount',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '7000000001',
                    'type' => 'boolean',
                    'value' => 'true',
                ],
                4 => [
                    'key' => 'eligible_for_discount',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '1000000001',
                    'type' => 'boolean',
                    'value' => 'false',
                ],
                5 => [
                    'key' => 'applicable_discounts',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '1000000001',
                    'type' => 'json',
                    'value' => '[]',
                ],
            ],
        ], $this->getLogger()->records[5]['context']['request']['variables']);
        $this->assertEquals([], $this->getLogger()->records[5]['context']['response']['data']['metafieldsSet']['userErrors']);
    }

    public function testExecuteAddDiscountsToProductsForDiscountAutomaticAppWithStatusExpired()
    {
        $kernel = static::createKernel();

        $multiscountClient = new MultiscountClient($this->getHttpClient([
            '/apps/multiscount/v2/discounts|POST|title=DiscountTitle201&type=volume' => [
                0 => [
                    'products' => [
                        'gid://shopify/Collection/2000000004',
                        'gid://shopify/Product/2000000005',
                    ],
                ],
            ],
        ]));
        $graphQLQueryHelper = new GraphQLQueryHelper();
        $shopifySdk = new ShopifySDK([
            'ShopUrl' => '',
            'AccessToken' => '',
        ]);
        $mockMethods = new MockMethods();
        $mockMethods->setLogger($this->getLogger());
        $mockShopify = new AddDiscountsToProductsCommandTestLocalMockShopify();
        $mockShopify->setMockMethods($mockMethods);
        $mockShopify->setMockData([
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getProductsQueryByCollection()) => [
                'data' => [
                    'collectionByHandle' => [
                        'products' => [
                            'nodes' => [
                                0 => [
                                    'id' => '1000000001',
                                    'vendor' => 'GreatVendor',
                                    'eligibleForDiscount' => [
                                        'value' => 'true',
                                    ],
                                ],
                            ],
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountQuery()) => [
                'data' => [
                    //'automaticDiscountNodes' => [  // MWS-642
                    'discountNodes' => [
                        'nodes' => [
                            0 => [
                                'id' => '2000000001',
                                //'automaticDiscount' => [  // MWS-642
                                'discount' => [
                                    '__typename' => 'DiscountAutomaticApp',
                                    // ... on DiscountAutomaticBxgy
                                    // ... on DiscountAutomaticBasic
                                    // ... on DiscountAutomaticApp
                                    'startsAt' => '',
                                    'endsAt' => '',
                                    'title' => 'DiscountTitle201',
                                    'discountClass' => 'PRODUCT',
                                    // ... on DiscountAutomaticApp
                                    'status' => 'EXPIRED',
                                    'combinesWith' => [
                                        'productDiscounts' => true,
                                    ],
                                    // ... on DiscountAutomaticBxgy
                                    /*
                                    'customerGets' => [
                                        'items' => [
                                            // ... on DiscountCollections
                                            'collections' => [
                                                'nodes' => [
                                                    0 => [
                                                        'title' => 'CollectionTitle204',
                                                        'id' => '2000000004',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                            // ... on DiscountProducts
                                            'products' => [
                                                'nodes' => [
                                                    0 => [
                                                        'id' => '2000000005',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '__typename' => 'DiscountAmount',
                                            // ... on DiscountAmount
                                            'amount' => [
                                                'amount' => '2.01',
                                                'currencyCode' => 'USD',
                                            ],
                                            // ... on DiscountOnQuantity
                                            'quantity' => [
                                                'quantity' => '202',
                                            ],
                                            'effect' => [
                                                '__typename' => 'DiscountPercentage',
                                                // ... on DiscountPercentage
                                                'percentage' => '2.03',
                                            ],
                                            // ... on DiscountPercentage
                                            'percentage' => '2.04',
                                        ],
                                    ],
                                    */
                                    /*
                                    'customerBuys' => [
                                        'items' => [
                                            // ... on DiscountCollections
                                            'collections' => [
                                                'nodes' => [
                                                    0 => [
                                                        'title' => 'CollectionTitle202',
                                                        'id' => '2000000002',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                            // ... on DiscountProducts
                                            'products' => [
                                                'nodes' => [
                                                    0 => [
                                                        'id' => '2000000003',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '__typename' => 'DiscountPurchaseAmount',
                                            // ... on DiscountPurchaseAmount
                                            'amount' => '2.05',
                                            // ... on DiscountQuantity
                                            'quantity' => '206',
                                        ],
                                    ],
                                    */
                                    /*
                                    // ... on DiscountAutomaticBasic
                                    'customerGets' => [
                                        'items' => [
                                            // ... on DiscountCollections
                                            'collections' => [
                                                'nodes' => [
                                                    0 => [
                                                        'title' => 'CollectionTitle204',
                                                        'id' => '2000000004',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                            // ... on DiscountProducts
                                            'products' => [
                                                'nodes' => [
                                                    0 => [
                                                        'id' => '2000000005',
                                                    ],
                                                ],
                                                'pageInfo' => [
                                                    'hasNextPage' => false,
                                                    'endCursor' => null,
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '__typename' => 'DiscountAmount',
                                            // ... on DiscountAmount
                                            'amount' => [
                                                'amount' => '2.07',
                                                'currencyCode' => 'USD',
                                            ],
                                            // ... on DiscountOnQuantity
                                            'quantity' => [
                                                'quantity' => '208',
                                            ],
                                            'effect' => [
                                                '__typename' => 'DiscountPercentage',
                                                // ... on DiscountPercentage
                                                'percentage' => '2.09',
                                            ],
                                            // ... on DiscountPercentage
                                            'percentage' => '2.10',
                                        ],
                                    ],
                                    'minimumRequirement' => [
                                        '__typename' => 'DiscountMinimumQuantity',
                                        // ... on DiscountMinimumQuantity
                                        'greaterThanOrEqualToQuantity' => '211',
                                        // ... on DiscountMinimumSubtotal
                                        'greaterThanOrEqualToSubtotal' => [
                                            'amount' => '2.12',
                                            'currencyCode' => 'USD',
                                        ],
                                    ],
                                    */
                                ],
                            ],
                        ],
                        'pageInfo' => [
                            'hasNextPage' => false,
                            'endCursor' => null,
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getMetafieldsSetQuery()) => [
                'data' => [
                    'metafieldsSet' => [
                        'metafields' => [
                            0 => [
                                'key' => '',
                                'namespace' => '',
                                'value' => '',
                                'createdAt' => '',
                                'updatedAt' => '',
                            ],
                        ],
                        'userErrors' => [
                            /*
                            0 => [
                                'field' => '',
                                'message' => '',
                                'code' => '',
                            ],
                            */
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBasicQueryByDiscountId(null, true)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '3000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBasic',
                            // ... on DiscountAutomaticBasic
                            'id' => '3000000002',
                            'title' => 'DiscountTitle302',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '3000000003',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '3.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '302',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '3.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '3.04',
                                ],
                            ],
                            'minimumRequirement' => [
                                '__typename' => 'DiscountMinimumQuantity',
                                // ... on DiscountMinimumQuantity
                                'greaterThanOrEqualToQuantity' => '305',
                                // ... on DiscountMinimumSubtotal
                                'greaterThanOrEqualToSubtotal' => [
                                    'amount' => '3.06',
                                    'currencyCode' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBasicQueryByDiscountId(null, false)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '4000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBasic',
                            // ... on DiscountAutomaticBasic
                            'id' => '4000000002',
                            'title' => 'DiscountTitle402',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle403',
                                                'id' => '4000000003',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '4.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '402',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '4.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '4.04',
                                ],
                            ],
                            'minimumRequirement' => [
                                '__typename' => 'DiscountMinimumQuantity',
                                // ... on DiscountMinimumQuantity
                                'greaterThanOrEqualToQuantity' => '405',
                                // ... on DiscountMinimumSubtotal
                                'greaterThanOrEqualToSubtotal' => [
                                    'amount' => '4.06',
                                    'currencyCode' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBxgyQueryByDiscountId(null, true)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '5000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBxgy',
                            // ... on DiscountAutomaticBxgy
                            'title' => 'DiscountTitle501',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '5.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '502',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '5.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '5.04',
                                ],
                            ],
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '5000000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountPurchaseAmount',
                                    // ... on DiscountPurchaseAmount
                                    'amount' => '5.05',
                                    // ... on DiscountQuantity
                                    'quantity' => '506',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getAutomaticDiscountBxgyQueryByDiscountId(null, false)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '6000000001',
                        'automaticDiscount' => [
                            '__typename' => 'DiscountAutomaticBxgy',
                            // ... on DiscountAutomaticBxgy
                            'title' => 'DiscountTitle601',
                            'startsAt' => '',
                            'endsAt' => '',
                            'customerGets' => [
                                'value' => [
                                    '__typename' => 'DiscountAmount',
                                    // ... on DiscountAmount
                                    'amount' => [
                                        'amount' => '6.01',
                                        'currencyCode' => 'USD',
                                    ],
                                    // ... on DiscountOnQuantity
                                    'quantity' => [
                                        'quantity' => '602',
                                    ],
                                    'effect' => [
                                        '__typename' => 'DiscountPercentage',
                                        // ... on DiscountPercentage
                                        'percentage' => '6.03',
                                    ],
                                    // ... on DiscountPercentage
                                    'percentage' => '6.04',
                                ],
                            ],
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle602',
                                                'id' => '6000000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                                'value' => [
                                    '__typename' => 'DiscountPurchaseAmount',
                                    // ... on DiscountPurchaseAmount
                                    'amount' => '6.05',
                                    // ... on DiscountQuantity
                                    'quantity' => '606',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getCollectionQueryByCollectionId()) => [
                'data' => [
                    'collection' => [
                        'products' => [
                            'nodes' => [
                                0 => [
                                    'id' => '7000000001',
                                ],
                            ],
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getCommentEventQuery()) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'events' => [
                            'nodes' => [
                                0 => [
                                    'message' => 'PROMO_TITLE: CommentEvent801',
                                ],
                                1 => [
                                    'message' => 'PROMO_BODY: CommentEvent802',
                                ],
                            ],
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBasicQueryByDiscountId('products', null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1100000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBasic
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '1100000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBasicQueryByDiscountId(null, null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1200000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBasic
                            'customerGets' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle122',
                                                'id' => '1200000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBxgyQueryByDiscountId('products', null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1300000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBxgy
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountProducts
                                    'products' => [
                                        'nodes' => [
                                            0 => [
                                                'id' => '1300000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getDiscountBxgyQueryByDiscountId(null, null)) => [
                'data' => [
                    'automaticDiscountNode' => [
                        'id' => '1400000001',
                        'automaticDiscount' => [
                            // ... on DiscountAutomaticBxgy
                            'customerBuys' => [
                                'items' => [
                                    // ... on DiscountCollections
                                    'collections' => [
                                        'nodes' => [
                                            0 => [
                                                'title' => 'CollectionTitle142',
                                                'id' => '1400000002',
                                            ],
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage' => false,
                                            'endCursor' => null,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/graph_qls'.$mockShopify->encodeParams($graphQLQueryHelper->getProductVariantQueryByIds()) => [
                'data' => [
                    'nodes' => [
                        0 => [
                            'id' => '1500000101',
                            'product' => [
                                'id' => '1500000111',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $command = new AddDiscountsToProductsCommand($graphQLQueryHelper, $shopifySdk, $multiscountClient);

        $refl = new \ReflectionProperty(get_class($command), 'shopifySDK');
        $refl->setAccessible(true);
        $refl->setValue($command, $mockShopify);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Start applying discounts to products', $output);
        $this->assertStringContainsString('Start querying products in "Eligible for Discount" collection...', $output);
        $this->assertStringContainsString('Successfully updated 3 products...', $output);
        $this->assertStringContainsString('Successfully apply discounts to 3 products', $output);

        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IGF1dG9tYXRpY0Rpc2NvdW50UXVlcnkoJHBlclBhZ2U6IEludCEpIHsgZGlzY291bnROb2RlcyAoZmlyc3Q6ICRwZXJQYWdlLCBxdWVyeTogXCJtZXRob2Q6YXV0b21hdGljIEFORCAoc3RhdHVzOmFjdGl2ZSBPUiBzdGF0dXM6c2NoZWR1bGVkKVwiICkgeyBub2RlcyB7IGlkIGRpc2NvdW50IHsgX190eXBlbmFtZSAuLi4gb24gRGlzY291bnRBdXRvbWF0aWNCeGd5IHsgc3RhcnRzQXQgZW5kc0F0IHRpdGxlIGRpc2NvdW50Q2xhc3MgY29tYmluZXNXaXRoIHsgcHJvZHVjdERpc2NvdW50cyB9IGN1c3RvbWVyR2V0cyB7IGl0ZW1zIHsgLi4uIG9uIERpc2NvdW50Q29sbGVjdGlvbnMgeyBjb2xsZWN0aW9ucyAoZmlyc3Q6IDEwICkgeyBub2RlcyB7IHRpdGxlIGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gLi4uIG9uIERpc2NvdW50UHJvZHVjdHMgeyBwcm9kdWN0cyAoZmlyc3Q6IDEwICkgeyBub2RlcyB7IGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gfSB2YWx1ZSB7IF9fdHlwZW5hbWUgLi4uIG9uIERpc2NvdW50QW1vdW50IHsgYW1vdW50IHsgYW1vdW50IGN1cnJlbmN5Q29kZSB9IH0gLi4uIG9uIERpc2NvdW50T25RdWFudGl0eSB7IHF1YW50aXR5IHsgcXVhbnRpdHkgfSBlZmZlY3QgeyBfX3R5cGVuYW1lIC4uLiBvbiBEaXNjb3VudFBlcmNlbnRhZ2UgeyBwZXJjZW50YWdlIH0gfSB9IC4uLiBvbiBEaXNjb3VudFBlcmNlbnRhZ2UgeyBwZXJjZW50YWdlIH0gfSB9IGN1c3RvbWVyQnV5cyB7IGl0ZW1zIHsgLi4uIG9uIERpc2NvdW50Q29sbGVjdGlvbnMgeyBjb2xsZWN0aW9ucyAoZmlyc3Q6ICRwZXJQYWdlICkgeyBub2RlcyB7IHRpdGxlIGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gLi4uIG9uIERpc2NvdW50UHJvZHVjdHMgeyBwcm9kdWN0cyAoZmlyc3Q6ICRwZXJQYWdlICkgeyBub2RlcyB7IGlkIH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gfSB2YWx1ZSB7IF9fdHlwZW5hbWUgLi4uIG9uIERpc2NvdW50UHVyY2hhc2VBbW91bnQgeyBhbW91bnQgfSAuLi4gb24gRGlzY291bnRRdWFudGl0eSB7IHF1YW50aXR5IH0gfSB9IH0gLi4uIG9uIERpc2NvdW50QXV0b21hdGljQmFzaWMgeyBzdGFydHNBdCBlbmRzQXQgdGl0bGUgZGlzY291bnRDbGFzcyBjdXN0b21lckdldHMgeyBpdGVtcyB7IC4uLiBvbiBEaXNjb3VudENvbGxlY3Rpb25zIHsgY29sbGVjdGlvbnMgKGZpcnN0OiAkcGVyUGFnZSApIHsgbm9kZXMgeyB0aXRsZSBpZCB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IC4uLiBvbiBEaXNjb3VudFByb2R1Y3RzIHsgcHJvZHVjdHMgKGZpcnN0OiAkcGVyUGFnZSApIHsgbm9kZXMgeyBpZCB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IH0gdmFsdWUgeyBfX3R5cGVuYW1lIC4uLiBvbiBEaXNjb3VudEFtb3VudCB7IGFtb3VudCB7IGFtb3VudCBjdXJyZW5jeUNvZGUgfSB9IC4uLiBvbiBEaXNjb3VudE9uUXVhbnRpdHkgeyBxdWFudGl0eSB7IHF1YW50aXR5IH0gZWZmZWN0IHsgX190eXBlbmFtZSAuLi4gb24gRGlzY291bnRQZXJjZW50YWdlIHsgcGVyY2VudGFnZSB9IH0gfSAuLi4gb24gRGlzY291bnRQZXJjZW50YWdlIHsgcGVyY2VudGFnZSB9IH0gfSBtaW5pbXVtUmVxdWlyZW1lbnQgeyBfX3R5cGVuYW1lIC4uLiBvbiBEaXNjb3VudE1pbmltdW1RdWFudGl0eSB7IGdyZWF0ZXJUaGFuT3JFcXVhbFRvUXVhbnRpdHkgfSAuLi4gb24gRGlzY291bnRNaW5pbXVtU3VidG90YWwgeyBncmVhdGVyVGhhbk9yRXF1YWxUb1N1YnRvdGFsIHsgYW1vdW50IGN1cnJlbmN5Q29kZSB9IH0gfSB9IC4uLiBvbiBEaXNjb3VudEF1dG9tYXRpY0FwcCB7IHN0YXJ0c0F0IGVuZHNBdCB0aXRsZSBkaXNjb3VudENsYXNzIHN0YXR1cyBjb21iaW5lc1dpdGggeyBwcm9kdWN0RGlzY291bnRzIH0gfSB9IH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0i'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IGNvbW1lbnRFdmVudFF1ZXJ5KCRwZXJQYWdlOiBJbnQhLCAkZGlzY291bnRJZDogSUQhKSB7IGF1dG9tYXRpY0Rpc2NvdW50Tm9kZSAoaWQ6ICRkaXNjb3VudElkKSB7IGV2ZW50cyAoZmlyc3Q6ICRwZXJQYWdlKSB7IG5vZGVzIHsgbWVzc2FnZSB9IHBhZ2VJbmZvIHsgaGFzTmV4dFBhZ2UgZW5kQ3Vyc29yIH0gfSB9IH0i'));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsInF1ZXJ5IHByb2R1Y3RzQnlDb2xsZWN0aW9uKCRjb2xsZWN0aW9uTmFtZTogU3RyaW5nISwgJHBlclBhZ2U6IEludCEpIHsgY29sbGVjdGlvbkJ5SGFuZGxlKGhhbmRsZTogJGNvbGxlY3Rpb25OYW1lKSB7IHByb2R1Y3RzKGZpcnN0OiAkcGVyUGFnZSkgeyBub2RlcyB7IGlkIHZlbmRvciBlbGlnaWJsZUZvckRpc2NvdW50OiBtZXRhZmllbGQobmFtZXNwYWNlOiBcIm13X21hcmtldGluZ1wiIGtleTogXCJlbGlnaWJsZV9mb3JfZGlzY291bnRcIikgeyB2YWx1ZSB9IH0gcGFnZUluZm8geyBoYXNOZXh0UGFnZSBlbmRDdXJzb3IgfSB9IH0gfSI='));
        $this->assertTrue($this->getLogger()->hasDebug('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qlsIm11dGF0aW9uIE1ldGFmaWVsZHNTZXQoJG1ldGFmaWVsZHM6IFtNZXRhZmllbGRzU2V0SW5wdXQhXSEpIHsgbWV0YWZpZWxkc1NldChtZXRhZmllbGRzOiAkbWV0YWZpZWxkcykgeyBtZXRhZmllbGRzIHsga2V5IG5hbWVzcGFjZSB2YWx1ZSBjcmVhdGVkQXQgdXBkYXRlZEF0IH0gdXNlckVycm9ycyB7IGZpZWxkIG1lc3NhZ2UgY29kZSB9IH0gfSI='));

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[0]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query automaticDiscountQuery($perPage: Int!) {
            discountNodes (first: $perPage, query: "method:automatic AND (status:active OR status:scheduled)" ) {
              nodes {
                id
                discount {
                  __typename
                  ... on DiscountAutomaticBxgy {
                    startsAt
                    endsAt
                    title
                    discountClass
                    combinesWith {
                      productDiscounts
                    }
                    customerGets {
                      items {
                        ... on DiscountCollections {
                          collections (first: 10 ) {
                            nodes {
                              title
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                        ... on DiscountProducts {
                          products (first: 10 ) {
                            nodes {
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                      }
                      value {
                        __typename
                        ... on DiscountAmount {
                          amount {
                            amount
                            currencyCode
                          }
                        }
                        ... on DiscountOnQuantity {
                          quantity {
                            quantity
                          }
                          effect {
                            __typename
                            ... on DiscountPercentage {
                              percentage
                            }
                          }
                        }
                        ... on DiscountPercentage {
                          percentage
                        }
                      }
                    }
                    customerBuys {
                      items {
                        ... on DiscountCollections {
                          collections (first: $perPage ) {
                            nodes {
                              title
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                        ... on DiscountProducts {
                          products (first: $perPage ) {
                            nodes {
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                      }
                      value {
                        __typename
                        ... on DiscountPurchaseAmount {
                          amount
                        }
                        ... on DiscountQuantity {
                          quantity
                        }
                      }
                    }
                  }
                  ... on DiscountAutomaticBasic {
                    startsAt
                    endsAt
                    title
                    discountClass
                    customerGets {
                      items {
                        ... on DiscountCollections {
                          collections (first: $perPage ) {
                            nodes {
                              title
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                        ... on DiscountProducts {
                          products (first: $perPage ) {
                            nodes {
                              id
                            }
                            pageInfo {
                              hasNextPage
                              endCursor
                            }
                          }
                        }
                      }
                      value {
                        __typename
                        ... on DiscountAmount {
                          amount {
                            amount
                            currencyCode
                          }
                        }
                        ... on DiscountOnQuantity {
                          quantity {
                            quantity
                          }
                          effect {
                            __typename
                            ... on DiscountPercentage {
                              percentage
                            }
                          }
                        }
                        ... on DiscountPercentage {
                          percentage
                        }
                      }
                    }
                    minimumRequirement {
                      __typename
                      ... on DiscountMinimumQuantity {
                        greaterThanOrEqualToQuantity
                      }
                      ... on DiscountMinimumSubtotal {
                        greaterThanOrEqualToSubtotal {
                          amount
                          currencyCode
                        }
                      }
                    }
                  }
                  ... on DiscountAutomaticApp {
                    startsAt
                    endsAt
                    title
                    discountClass
                    status
                    combinesWith {
                      productDiscounts
                    }
                  }
                }
              }
              pageInfo {
                hasNextPage
                endCursor
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[0]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 20,
            'endCursor' => null,
        ], $this->getLogger()->records[0]['context']['request']['variables']);
        //$this->assertEquals('2000000001', $this->getLogger()->records[0]['context']['response']['data']['automaticDiscountNodes']['nodes'][0]['id']);  // MWS-642
        $this->assertEquals('2000000001', $this->getLogger()->records[0]['context']['response']['data']['discountNodes']['nodes'][0]['id']);

        //$this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[1]['message']);
        //$this->assertEquals($mockShopify->formatGraphQLQueryString('
        //  query discountBxgyQueryByDiscountId($perPage: Int!, $discountId: ID!) {
        //    automaticDiscountNode (id: $discountId) {
        //      automaticDiscount {
        //        ... on DiscountAutomaticBxgy {
        //          customerBuys {
        //            items {
        //              ... on DiscountCollections {
        //                collections (first: $perPage ) {
        //                  nodes {
        //                    title
        //                    id
        //                  }
        //                  pageInfo {
        //                    hasNextPage
        //                    endCursor
        //                  }
        //                }
        //              }
        //            }
        //          }
        //        }
        //      }
        //    }
        //  }
        //'), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[1]['context']['request']['query']));
        //$this->assertSame([
        //    'perPage' => 250,
        //    'endCursor' => null,
        //    'discountId' => '2000000001',
        //], $this->getLogger()->records[1]['context']['request']['variables']);
        //$this->assertEquals('CollectionTitle142', $this->getLogger()->records[1]['context']['response']['data']['automaticDiscountNode']['automaticDiscount']['customerBuys']['items']['collections']['nodes'][0]['title']);
        //$this->assertEquals('1400000002', $this->getLogger()->records[1]['context']['response']['data']['automaticDiscountNode']['automaticDiscount']['customerBuys']['items']['collections']['nodes'][0]['id']);
        //
        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[1]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query commentEventQuery($perPage: Int!, $discountId: ID!) {
            automaticDiscountNode (id: $discountId) {
              events (first: $perPage) {
                nodes {
                  message
                }
                pageInfo {
                  hasNextPage
                  endCursor
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[1]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 50,
            'discountId' => '2000000001',
            'endCursor' => null,
        ], $this->getLogger()->records[1]['context']['request']['variables']);
        $this->assertEquals('PROMO_TITLE: CommentEvent801', $this->getLogger()->records[1]['context']['response']['data']['automaticDiscountNode']['events']['nodes'][0]['message']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[2]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query collectionQueryByCollectionId($collectionId: ID!, $perPage: Int!) {
            collection(id: $collectionId) {
              products(first: $perPage) {
                nodes {
                  id
                }
                pageInfo {
                  hasNextPage
                  endCursor
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[2]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 20,
            'collectionId' => 'gid://shopify/Collection/2000000004',
            'endCursor' => null,
        ], $this->getLogger()->records[2]['context']['request']['variables']);
        $this->assertEquals('7000000001', $this->getLogger()->records[2]['context']['response']['data']['collection']['products']['nodes'][0]['id']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[3]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          query productsByCollection($collectionName: String!, $perPage: Int!) {
            collectionByHandle(handle: $collectionName) {
              products(first: $perPage) {
                nodes {
                  id
                  vendor
                  eligibleForDiscount: metafield(namespace: "mw_marketing" key: "eligible_for_discount") {
                    value
                  }
                }
                pageInfo {
                  hasNextPage
                  endCursor
                }
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[3]['context']['request']['query']));
        $this->assertSame([
            'perPage' => 20,
            'endCursor' => null,
            'collectionName' => 'eligible-for-discount',
        ], $this->getLogger()->records[3]['context']['request']['variables']);
        $this->assertEquals('1000000001', $this->getLogger()->records[3]['context']['response']['data']['collectionByHandle']['products']['nodes'][0]['id']);

        $this->assertStringContainsString('Executed Shopify API Request - Status Code: 200 | Method: POST | URL: /graph_qls', $this->getLogger()->records[4]['message']);
        $this->assertEquals($mockShopify->formatGraphQLQueryString('
          mutation MetafieldsSet($metafields: [MetafieldsSetInput!]!) {
            metafieldsSet(metafields: $metafields) {
              metafields {
                key
                namespace
                value
                createdAt
                updatedAt
              }
              userErrors {
                field
                message
                code
              }
            }
          }
        '), $mockShopify->formatGraphQLQueryString($this->getLogger()->records[4]['context']['request']['query']));
        $this->assertSame([
            'metafields' => [
                0 => [
                    'key' => 'applicable_discounts',
                    'namespace' => 'mw_marketing',
                    'ownerId' => 'gid://shopify/Product/2000000005',
                    'type' => 'json',
                    'value' => '[]',
                ],
                1 => [
                    'key' => 'eligible_for_discount',
                    'namespace' => 'mw_marketing',
                    'ownerId' => 'gid://shopify/Product/2000000005',
                    'type' => 'boolean',
                    'value' => 'false',
                ],
                2 => [
                    'key' => 'applicable_discounts',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '7000000001',
                    'type' => 'json',
                    'value' => '[]',
                ],
                3 => [
                    'key' => 'eligible_for_discount',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '7000000001',
                    'type' => 'boolean',
                    'value' => 'false',
                ],
                4 => [
                    'key' => 'eligible_for_discount',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '1000000001',
                    'type' => 'boolean',
                    'value' => 'false',
                ],
                5 => [
                    'key' => 'applicable_discounts',
                    'namespace' => 'mw_marketing',
                    'ownerId' => '1000000001',
                    'type' => 'json',
                    'value' => '[]',
                ],
            ],
        ], $this->getLogger()->records[4]['context']['request']['variables']);
        $this->assertEquals([], $this->getLogger()->records[4]['context']['response']['data']['metafieldsSet']['userErrors']);
    }

    protected function configureTask(BaseTask $task, $repositoryResults = null, $dataSourceApiResults = null, $dataDestinationApiResults = null, $dataSourceApiConnector = null, $dataDestinationApiConnector = null, $dataSourceHttpClient = null, $dataDestinationHttpClient = null, $objectManager = null, $doctrine = null, $options = [])
    {
        $options['fqcn']['api_connector']['data_source'] = AppShopifyApiConnector::class;
        $options['fqcn']['order']['configured_middleware'] = AppOrderMiddleware::class;
        $options['fqcn']['order']['data_source'] = AppOrderShopifySource::class;
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

class AddDiscountsToProductsCommandTestLocalMockShopify extends MockShopify
{
    public function formatGraphQLQueryString($query)
    {
        if (is_string($query)) {
            $query = str_replace(["\n", "\r"], ' ', $query);
            $query = preg_replace('`[ ]+`', ' ', $query);
            $query = trim($query);
        }

        return $query;
    }

    public function encodeParams($params)
    {
        return base64_encode(json_encode($this->formatGraphQLQueryString($params)));
    }

    public function sendResponse($method = null)
    {
        if ('/graph_qls' === $this->currentRequestUrl) {
            $this->currentRequestUrl .= $this->encodeParams($this->currentParams);
        }

        return parent::sendResponse($method);
    }

    public function post($params = null)
    {
        $args = func_get_args();
        if (isset($args[0]) && isset($args[3]) && $this->getMockMethods()) {
            // capture the GraphQL query and variables
            if (method_exists($this->getMockMethods(), 'addLastRequestOption')) {
                $this->getMockMethods()->addLastRequestOption('query', $args[0]);
                $this->getMockMethods()->addLastRequestOption('variables', $args[3]);
            }
        }

        return parent::post($params);
    }
}
