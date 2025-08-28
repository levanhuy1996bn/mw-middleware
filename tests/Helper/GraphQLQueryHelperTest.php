<?php

namespace App\Tests\Helper;

use App\Helper\GraphQLQueryHelper;
use Endertech\EcommerceMiddleware\Core\Traits\Connector\GraphQLTrait;
use Endertech\EcommerceMiddleware\Driver\Shopify\Traits\Metafield\ShopifyGraphQLMetafieldTrait;
use Endertech\EcommerceMiddleware\Driver\Shopify\Traits\Order\ShopifyGraphQLDraftOrderTrait;
use Endertech\EcommerceMiddleware\Driver\Shopify\Traits\Variant\ShopifyGraphQLVariantTrait;
use PHPUnit\Framework\TestCase;

class GraphQLQueryHelperTest extends TestCase
{
    use GraphQLTrait;
    use ShopifyGraphQLDraftOrderTrait;
    use ShopifyGraphQLMetafieldTrait;
    use ShopifyGraphQLVariantTrait;

    public function testGetProductsQueryByMattressesCollection()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query productsByCollection($collectionName: String!, $perPage: Int!) {
            collectionByHandle(handle: $collectionName) {
                products(first: $perPage) {
                  nodes {
                     handle
                     productType
                     featuredImage {
                        url
                      }
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
               }
            }
        }',
            $graphqlQueryHelper->getProductsQueryByMattressesCollection()
        );

        $this->assertEquals(
            'query productsByCollection($collectionName: String!, $perPage: Int!, $endCursor: String) {
            collectionByHandle(handle: $collectionName) {
                products(first: $perPage, after: $endCursor) {
                  nodes {
                     handle
                     productType
                     featuredImage {
                        url
                      }
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
               }
            }
        }',
            $graphqlQueryHelper->getProductsQueryByMattressesCollection('ABC-123')
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'collectionByHandle', 'products', [
//            'collectionName' => [
//                'type' => 'String!',
//                'variable' => 'handle',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            'nodes' => [
                'handle',
                'productType',
                'featuredImage' => [
                    'url',
                ],
            ],
            'pageInfo' => [
                'hasNextPage',
                'endCursor',
            ],
        ], [
            'wrapperFunctionName' => 'productsByCollection',
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id'], ['$collectionName: String!', 'handle: $collectionName'], $gql);

        $this->assertEquals('query productsByCollection(
  $perPage: Int!,
  $endCursor: String,
  $collectionName: String!
) {
  collectionByHandle(
    handle: $collectionName
  ) {
    id
    __typename
    products(
      first: $perPage,
      after: $endCursor
    ) {
      nodes {
        handle
        productType
        featuredImage {
          url
        }
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getProductsQueryByMattressesCollection('ABC-123'))
//        );
    }

    public function testGetProductsQuery()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query getProducts($perPage: Int!) {
            products(first: $perPage) {
                nodes {
                     id
                     handle
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
            }
        }',
            $graphqlQueryHelper->getProductQuery()
        );

        $this->assertEquals(
            'query getProducts($perPage: Int!, $endCursor: String) {
            products(first: $perPage, after: $endCursor) {
                nodes {
                     id
                     handle
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
            }
        }',
            $graphqlQueryHelper->getProductQuery('ABC-123')
        );

        $gql = $this->generateGraphQL('query', 'products', [
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            'nodes' => [
                'id',
                'handle',
            ],
            'pageInfo' => [
                'hasNextPage',
                'endCursor',
            ],
        ], [
            'wrapperFunctionName' => 'getProducts',
        ]);

        $this->assertEquals('query getProducts(
  $perPage: Int!,
  $endCursor: String
) {
  products(
    first: $perPage,
    after: $endCursor
  ) {
    nodes {
      id
      handle
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getProductQuery('ABC-123'))
//        );
    }

    public function testGetProductsQueryInMattressesCollection()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query productsInMattressesCollection($handle: String!, $perPage: Int!) {
            collectionByHandle(handle: $handle) {
                products(first: $perPage) {
                  nodes {
                     id
                     status
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
               }
            }
        }',
            $graphqlQueryHelper->getProductsQueryInMattressesCollection()
        );

        $this->assertEquals(
            'query productsInMattressesCollection($handle: String!, $perPage: Int!, $endCursor: String) {
            collectionByHandle(handle: $handle) {
                products(first: $perPage, after: $endCursor) {
                  nodes {
                     id
                     status
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
               }
            }
        }',
            $graphqlQueryHelper->getProductsQueryInMattressesCollection('ABC-123')
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'collectionByHandle', 'products', [
//            'handle' => [
//                'type' => 'String!',
//                'variable' => 'handle',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            'nodes' => [
                'id',
                'status',
            ],
            'pageInfo' => [
                'hasNextPage',
                'endCursor',
            ],
        ], [
            'wrapperFunctionName' => 'productsInMattressesCollection',
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id'], ['$handle: String!', 'handle: $handle'], $gql);

        $this->assertEquals('query productsInMattressesCollection(
  $perPage: Int!,
  $endCursor: String,
  $handle: String!
) {
  collectionByHandle(
    handle: $handle
  ) {
    id
    __typename
    products(
      first: $perPage,
      after: $endCursor
    ) {
      nodes {
        id
        status
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getProductsQueryInMattressesCollection('ABC-123'))
//        );
    }

    public function testGetProductVariantsByMattressProductId()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query productVariantsByProductId($productId: ID!, $perPage: Int!) {
            product(id: $productId) {
                
            variants(first: $perPage) {
                  nodes {
                     id
                     price
                     compareAtPrice
                     selectedOptions {
                        name
                        value
                     }
                     product {
                        vendor
                        productType
                        featuredImage {
                            id
                        }
                        mattressType: metafield(namespace: "mw_specifications" key: "mattress_type") {
                           value
                        }
                        comfortLevel: metafield(namespace: "mw_specifications" key: "comfort_level") {
                           value
                        }
                     }
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
               }
        
            }
        }',
            $graphqlQueryHelper->getProductVariantsByMattressProductId()
        );

        $this->assertEquals(
            'query productVariantsByProductId($productId: ID!, $perPage: Int!, $endCursor: String) {
            product(id: $productId) {
                
            variants(first: $perPage, after: $endCursor) {
                  nodes {
                     id
                     price
                     compareAtPrice
                     selectedOptions {
                        name
                        value
                     }
                     product {
                        vendor
                        productType
                        featuredImage {
                            id
                        }
                        mattressType: metafield(namespace: "mw_specifications" key: "mattress_type") {
                           value
                        }
                        comfortLevel: metafield(namespace: "mw_specifications" key: "comfort_level") {
                           value
                        }
                     }
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
               }
        
            }
        }',
            $graphqlQueryHelper->getProductVariantsByMattressProductId('ABC-123')
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'product', 'variants', [
//            'productId' => [
//                'type' => 'ID!',
//                'variable' => 'id',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            'nodes' => [
                'id',
                'price',
                'compareAtPrice',
                'selectedOptions' => [
                    'name',
                    'value',
                ],
                'product' => [
                    'vendor',
                    'productType',
                    'featuredImage' => [
                        'id',
                    ],
                    'mattressType: metafield' => [
                        '__arguments__' => [
                            'namespace' => 'mw_specifications',
                            'key' => 'mattress_type',
                        ],
                        'value',
                    ],
                    'comfortLevel: metafield' => [
                        '__arguments__' => [
                            'namespace' => 'mw_specifications',
                            'key' => 'comfort_level',
                        ],
                        'value',
                    ],
                ],
            ],
            'pageInfo' => [
                'hasNextPage',
                'endCursor',
            ],
        ], [
            'wrapperFunctionName' => 'productVariantsByProductId',
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id'], ['$productId: ID!', 'id: $productId'], $gql);

        $this->assertEquals('query productVariantsByProductId(
  $perPage: Int!,
  $endCursor: String,
  $productId: ID!
) {
  product(
    id: $productId
  ) {
    id
    __typename
    variants(
      first: $perPage,
      after: $endCursor
    ) {
      nodes {
        id
        price
        compareAtPrice
        selectedOptions {
          name
          value
        }
        product {
          vendor
          productType
          featuredImage {
            id
          }
          mattressType: metafield (namespace:"mw_specifications", key:"mattress_type") {
            value
          }
          comfortLevel: metafield (namespace:"mw_specifications", key:"comfort_level") {
            value
          }
        }
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getProductVariantsByMattressProductId('ABC-123'))
//        );
    }

    public function testGetProductsQueryByCollectionHandle()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query getProductsInPillowsCollection($collectionName: String!, $perPage: Int!) {
            collection: collectionByHandle(handle: $collectionName) {
                products(first: $perPage) {
                  nodes {
                     id
                     status
                     productType
                     pillowType: metafield(namespace: "mw_specifications" key: "pillow_type") {
                        value
                     }
                    featuredImage {
                        id
                    }
                    productCategory {
                        productTaxonomyNode {
                            name
                        }
                    }
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
               }
            }
        }',
            $graphqlQueryHelper->getProductsQueryByCollectionHandle()
        );

        $this->assertEquals(
            'query getProductsInPillowsCollection($collectionName: String!, $perPage: Int!, $endCursor: String) {
            collection: collectionByHandle(handle: $collectionName) {
                products(first: $perPage, after: $endCursor) {
                  nodes {
                     id
                     status
                     productType
                     pillowType: metafield(namespace: "mw_specifications" key: "pillow_type") {
                        value
                     }
                    featuredImage {
                        id
                    }
                    productCategory {
                        productTaxonomyNode {
                            name
                        }
                    }
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
               }
            }
        }',
            $graphqlQueryHelper->getProductsQueryByCollectionHandle('ABC-123')
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'collection: collectionByHandle', 'products', [
//            'collectionName' => [
//                'type' => 'String!',
//                'variable' => 'handle',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            'nodes' => [
                'id',
                'status',
                'productType',
                'pillowType: metafield' => [
                    '__arguments__' => [
                        'namespace' => 'mw_specifications',
                        'key' => 'pillow_type',
                    ],
                    'value',
                ],
                'featuredImage' => [
                    'id',
                ],
                'productCategory' => [
                    // TODO is this deprecated in the 2025-01 version?
                    'productTaxonomyNode' => [
                        'name',
                    ],
                ],
            ],
            'pageInfo' => [
                'hasNextPage',
                'endCursor',
            ],
        ], [
            'wrapperFunctionName' => 'getProductsInPillowsCollection',
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id'], ['$collectionName: String!', 'handle: $collectionName'], $gql);

        $this->assertEquals('query getProductsInPillowsCollection(
  $perPage: Int!,
  $endCursor: String,
  $collectionName: String!
) {
  collection: collectionByHandle(
    handle: $collectionName
  ) {
    id
    __typename
    products(
      first: $perPage,
      after: $endCursor
    ) {
      nodes {
        id
        status
        productType
        pillowType: metafield (namespace:"mw_specifications", key:"pillow_type") {
          value
        }
        featuredImage {
          id
        }
        productCategory {
          productTaxonomyNode {
            name
          }
        }
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getProductsQueryByCollectionHandle('ABC-123'))
//        );
    }

    public function testGetMattressAccessoriesVariantsQueryByProductId()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query mattressAccessoriesVariantsQueryByProductId($productId: ID!, $perPage: Int!) {
            product(id: $productId) {
                handle
                variants(first: $perPage) {
                  nodes {
                     product {
                        handle
                        title
                        productType
                     }
                     id
                     price
                     selectedOptions {
                        name
                        value
                     }
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
               }
            }
        }',
            $graphqlQueryHelper->getMattressAccessoriesVariantsQueryByProductId()
        );

        $this->assertEquals(
            'query mattressAccessoriesVariantsQueryByProductId($productId: ID!, $perPage: Int!, $endCursor: String) {
            product(id: $productId) {
                handle
                variants(first: $perPage, after: $endCursor) {
                  nodes {
                     product {
                        handle
                        title
                        productType
                     }
                     id
                     price
                     selectedOptions {
                        name
                        value
                     }
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
               }
            }
        }',
            $graphqlQueryHelper->getMattressAccessoriesVariantsQueryByProductId('ABC-123')
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'product', 'variants', [
//            'productId' => [
//                'type' => 'ID!',
//                'variable' => 'id',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            'nodes' => [
                'product' => [
                    'handle',
                    'title',
                    'productType',
                ],
                'id',
                'price',
                'selectedOptions' => [
                    'name',
                    'value',
                ],
            ],
            'pageInfo' => [
                'hasNextPage',
                'endCursor',
            ],
        ], [
            'wrapperFunctionName' => 'mattressAccessoriesVariantsQueryByProductId',
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id', '__typename'], ['$productId: ID!', 'id: $productId', 'handle'], $gql);

        $this->assertEquals('query mattressAccessoriesVariantsQueryByProductId(
  $perPage: Int!,
  $endCursor: String,
  $productId: ID!
) {
  product(
    id: $productId
  ) {
    id
    handle
    variants(
      first: $perPage,
      after: $endCursor
    ) {
      nodes {
        product {
          handle
          title
          productType
        }
        id
        price
        selectedOptions {
          name
          value
        }
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getMattressAccessoriesVariantsQueryByProductId('ABC-123'))
//        );
    }

    public function testGetBedMatchProductVariantsQueryByProductIds()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query bedMatchProductVariantsQueryByProductIds($ids: [ID!]!) {
          nodes(ids: $ids) {
            id
            ... on Product {
              variants(first: 250) {
                nodes {
                  id
                  sku
                  title
                  price
                  compareAtPrice
                  selectedOptions {
                    name
                    value
                  }
                  product {
                    id
                    handle
                    title
                    productType
                  }
                }
              }
            }
          }
        }',
            $graphqlQueryHelper->getBedMatchProductVariantsQueryByProductIds()
        );

        $gql = $this->generateGraphQL('query', 'nodes', [
            'ids' => [
                'type' => '[ID!]!',
            ],
        ], [
            'id',
            '... on Product' => [
                'variants' => [
                    '__arguments__' => [
                        'first' => 250,
                    ],
                    'nodes' => [
                        'id',
                        'sku',
                        'title',
                        'price',
                        'compareAtPrice',
                        'selectedOptions' => [
                            'name',
                            'value',
                        ],
                        'product' => [
                            'id',
                            'handle',
                            'title',
                            'productType',
                        ],
                    ],
                ],
            ],
        ], [
            'wrapperFunctionName' => 'bedMatchProductVariantsQueryByProductIds',
        ]);

        $this->assertEquals('query bedMatchProductVariantsQueryByProductIds(
  $ids: [ID!]!
) {
  nodes(
    ids: $ids
  ) {
    id
    ... on Product {
      variants (first:250) {
        nodes {
          id
          sku
          title
          price
          compareAtPrice
          selectedOptions {
            name
            value
          }
          product {
            id
            handle
            title
            productType
          }
        }
      }
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getBedMatchProductVariantsQueryByProductIds())
//        );
    }

    public function testGetProductsQueryByCollection()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query productsByCollection($collectionName: String!, $perPage: Int!) {
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
        }',
            $graphqlQueryHelper->getProductsQueryByCollection()
        );

        $this->assertEquals(
            'query productsByCollection($collectionName: String!, $perPage: Int!, $endCursor: String) {
            collectionByHandle(handle: $collectionName) {
                products(first: $perPage, after: $endCursor) {
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
        }',
            $graphqlQueryHelper->getProductsQueryByCollection('ABC-123')
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'collectionByHandle', 'products', [
//            'collectionName' => [
//                'type' => 'String!',
//                'variable' => 'handle',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            'nodes' => [
                'id',
                'vendor',
                'eligibleForDiscount: metafield' => [
                    '__arguments__' => [
                        'namespace' => 'mw_marketing',
                        'key' => 'eligible_for_discount',
                    ],
                    'value',
                ],
            ],
            'pageInfo' => [
                'hasNextPage',
                'endCursor',
            ],
        ], [
            'wrapperFunctionName' => 'productsByCollection',
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id'], ['$collectionName: String!', 'handle: $collectionName'], $gql);

        $this->assertEquals('query productsByCollection(
  $perPage: Int!,
  $endCursor: String,
  $collectionName: String!
) {
  collectionByHandle(
    handle: $collectionName
  ) {
    id
    __typename
    products(
      first: $perPage,
      after: $endCursor
    ) {
      nodes {
        id
        vendor
        eligibleForDiscount: metafield (namespace:"mw_marketing", key:"eligible_for_discount") {
          value
        }
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getProductsQueryByCollection('ABC-123'))
//        );
    }

    public function testGetProductVariantsQueryByProductHandle()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query productVariantsByProductHandle($productHandle: String!, $perPage: Int!) {
            product: productByHandle(handle: $productHandle) {
                
            productType
            variants(first: $perPage) {
                  nodes {
                     product {
                        title
                        vendor
                     }
                     id
                     title
                     selectedOptions {
                        name
                        value
                     }
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
               }
        
            }
        }',
            $graphqlQueryHelper->getProductVariantsQueryByProductHandle()
        );

        $this->assertEquals(
            'query productVariantsByProductHandle($productHandle: String!, $perPage: Int!, $endCursor: String) {
            product: productByHandle(handle: $productHandle) {
                
            productType
            variants(first: $perPage, after: $endCursor) {
                  nodes {
                     product {
                        title
                        vendor
                     }
                     id
                     title
                     selectedOptions {
                        name
                        value
                     }
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
               }
        
            }
        }',
            $graphqlQueryHelper->getProductVariantsQueryByProductHandle('ABC-123')
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'product: productByHandle', 'variants', [
//            'productHandle' => [
//                'type' => 'String!',
//                'variable' => 'handle',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            'nodes' => [
                'product' => [
                    'title',
                    'vendor',
                ],
                'id',
                'title',
                'selectedOptions' => [
                    'name',
                    'value',
                ],
            ],
            'pageInfo' => [
                'hasNextPage',
                'endCursor',
            ],
        ], [
            'wrapperFunctionName' => 'productVariantsByProductHandle',
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id', '__typename'], ['$productHandle: String!', 'handle: $productHandle', 'productType'], $gql);

        $this->assertEquals('query productVariantsByProductHandle(
  $perPage: Int!,
  $endCursor: String,
  $productHandle: String!
) {
  product: productByHandle(
    handle: $productHandle
  ) {
    id
    productType
    variants(
      first: $perPage,
      after: $endCursor
    ) {
      nodes {
        product {
          title
          vendor
        }
        id
        title
        selectedOptions {
          name
          value
        }
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getProductVariantsQueryByProductHandle('ABC-123'))
//        );
    }

    public function testGetCommentEventQuery()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query commentEventQuery($perPage: Int!, $discountId: ID!) {
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
        }',
            $graphqlQueryHelper->getCommentEventQuery()
        );

        $this->assertEquals(
            'query commentEventQuery($perPage: Int!, $discountId: ID!, $endCursor: String) {
            automaticDiscountNode (id: $discountId) {
                events (first: $perPage, after: $endCursor) {
                  nodes {
                    message
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
                }
            }
        }',
            $graphqlQueryHelper->getCommentEventQuery('ABC-123')
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'automaticDiscountNode', 'events', [
//            'discountId' => [
//                'type' => 'ID!',
//                'variable' => 'id',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            'nodes' => [
                'message',
            ],
            'pageInfo' => [
                'hasNextPage',
                'endCursor',
            ],
        ], [
            'wrapperFunctionName' => 'commentEventQuery',
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id'], ['$discountId: ID!', 'id: $discountId'], $gql);

        $this->assertEquals('query commentEventQuery(
  $perPage: Int!,
  $endCursor: String,
  $discountId: ID!
) {
  automaticDiscountNode(
    id: $discountId
  ) {
    id
    __typename
    events(
      first: $perPage,
      after: $endCursor
    ) {
      nodes {
        message
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getCommentEventQuery('ABC-123'))
//        );
    }

    public function testGetAutomaticDiscountQuery()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query automaticDiscountQuery($perPage: Int!) {
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
          }',
            $graphqlQueryHelper->getAutomaticDiscountQuery()
        );

        $this->assertEquals(
            'query automaticDiscountQuery($perPage: Int!, $endCursor: String) {
            discountNodes (first: $perPage, query: "method:automatic AND (status:active OR status:scheduled)" , after: $endCursor) {
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
          }',
            $graphqlQueryHelper->getAutomaticDiscountQuery('ABC-123')
        );

        $gql = $this->generateGraphQL('query', 'discountNodes', [
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
            'query' => [
                'type' => 'String',
                'variable' => 'query',
                'optional' => true,
                'directValue' => 'method:automatic AND (status:active OR status:scheduled)',
            ],
        ], [
            'nodes' => [
                'id',
                'discount' => [
                    '__typename',
                    '... on DiscountAutomaticBxgy' => [
                        'startsAt',
                        'endsAt',
                        'title',
                        'discountClass',
                        'combinesWith' => [
                            'productDiscounts',
                        ],
                        'customerGets' => [
                            'items' => [
                                '... on DiscountCollections' => [
                                    'collections' => [
                                        '__arguments__' => [
                                            'first' => 10,
                                        ],
                                        'nodes' => [
                                            'title',
                                            'id',
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage',
                                            'endCursor',
                                        ],
                                    ],
                                ],
                                '... on DiscountProducts' => [
                                    'products' => [
                                        '__arguments__' => [
                                            'first' => 10,
                                        ],
                                        'nodes' => [
                                            'id',
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage',
                                            'endCursor',
                                        ],
                                    ],
                                ],
                            ],
                            'value' => [
                                '__typename',
                                '... on DiscountAmount' => [
                                    'amount' => [
                                        'amount',
                                        'currencyCode',
                                    ],
                                ],
                                '... on DiscountOnQuantity' => [
                                    'quantity' => [
                                        'quantity',
                                    ],
                                    'effect' => [
                                        '__typename',
                                        '... on DiscountPercentage' => [
                                            'percentage',
                                        ],
                                    ],
                                ],
                                '... on DiscountPercentage' => [
                                    'percentage',
                                ],
                            ],
                        ],
                        'customerBuys' => [
                            'items' => [
                                '... on DiscountCollections' => [
                                    'collections' => [
                                        '__arguments__' => [
                                            'first' => '$perPage',
                                        ],
                                        'nodes' => [
                                            'title',
                                            'id',
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage',
                                            'endCursor',
                                        ],
                                    ],
                                ],
                                '... on DiscountProducts' => [
                                    'products' => [
                                        '__arguments__' => [
                                            'first' => '$perPage',
                                        ],
                                        'nodes' => [
                                            'id',
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage',
                                            'endCursor',
                                        ],
                                    ],
                                ],
                            ],
                            'value' => [
                                '__typename',
                                '... on DiscountPurchaseAmount' => [
                                    'amount',
                                ],
                                '... on DiscountQuantity' => [
                                    'quantity',
                                ],
                            ],
                        ],
                    ],
                    '... on DiscountAutomaticBasic' => [
                        'startsAt',
                        'endsAt',
                        'title',
                        'discountClass',
                        'customerGets' => [
                            'items' => [
                                '... on DiscountCollections' => [
                                    'collections' => [
                                        '__arguments__' => [
                                            'first' => '$perPage',
                                        ],
                                        'nodes' => [
                                            'title',
                                            'id',
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage',
                                            'endCursor',
                                        ],
                                    ],
                                ],
                                '... on DiscountProducts' => [
                                    'products' => [
                                        '__arguments__' => [
                                            'first' => '$perPage',
                                        ],
                                        'nodes' => [
                                            'id',
                                        ],
                                        'pageInfo' => [
                                            'hasNextPage',
                                            'endCursor',
                                        ],
                                    ],
                                ],
                            ],
                            'value' => [
                                '__typename',
                                '... on DiscountAmount' => [
                                    'amount' => [
                                        'amount',
                                        'currencyCode',
                                    ],
                                ],
                                '... on DiscountOnQuantity' => [
                                    'quantity' => [
                                        'quantity',
                                    ],
                                    'effect' => [
                                        '__typename',
                                        '... on DiscountPercentage' => [
                                            'percentage',
                                        ],
                                    ],
                                ],
                                '... on DiscountPercentage' => [
                                    'percentage',
                                ],
                            ],
                        ],
                        'minimumRequirement' => [
                            '__typename',
                            '... on DiscountMinimumQuantity' => [
                                'greaterThanOrEqualToQuantity',
                            ],
                            '... on DiscountMinimumSubtotal' => [
                                'greaterThanOrEqualToSubtotal' => [
                                    'amount',
                                    'currencyCode',
                                ],
                            ],
                        ],
                    ],
                    '... on DiscountAutomaticApp' => [
                        'startsAt',
                        'endsAt',
                        'title',
                        'discountClass',
                        'status',
                        'combinesWith' => [
                            'productDiscounts',
                        ],
                    ],
                ],
            ],
            'pageInfo' => [
                'hasNextPage',
                'endCursor',
            ],
        ], [
            'wrapperFunctionName' => 'automaticDiscountQuery',
        ]);
        $gql = str_replace('"$perPage"', '$perPage', $gql);

        $this->assertEquals('query automaticDiscountQuery(
  $perPage: Int!,
  $endCursor: String
) {
  discountNodes(
    first: $perPage,
    after: $endCursor,
    query: "method:automatic AND (status:active OR status:scheduled)"
  ) {
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
                collections (first:10) {
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
                products (first:10) {
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
                collections (first:$perPage) {
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
                products (first:$perPage) {
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
                collections (first:$perPage) {
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
                products (first:$perPage) {
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
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getAutomaticDiscountQuery('ABC-123'))
//        );
    }

    public function testGetAutomaticDiscountBasicQueryByDiscountId()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query automaticDiscountBasicQueryByDiscountId($perPage: Int!, $discountId: ID!, $endCursor: String) {
            automaticDiscountNode (id: $discountId) {
               id
               automaticDiscount {
                 __typename
                 ... on DiscountAutomaticBasic {
                    id
                    title
                    startsAt
                    endsAt
                    customerGets {
                       items {
                          
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
               }
            }
        }',
            $graphqlQueryHelper->getAutomaticDiscountBasicQueryByDiscountId(null, true)
        );

        $this->assertEquals(
            'query automaticDiscountBasicQueryByDiscountId($perPage: Int!, $discountId: ID!, $endCursor: String) {
            automaticDiscountNode (id: $discountId) {
               id
               automaticDiscount {
                 __typename
                 ... on DiscountAutomaticBasic {
                    id
                    title
                    startsAt
                    endsAt
                    customerGets {
                       items {
                          
            ... on DiscountProducts {
               products (first: $perPage , after: $endCursor) {
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
               }
            }
        }',
            $graphqlQueryHelper->getAutomaticDiscountBasicQueryByDiscountId('ABC-123', true)
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'automaticDiscountNode', 'automaticDiscount', [
//            'discountId' => [
//                'type' => 'ID!',
//                'variable' => 'id',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            '__typename',
            '... on DiscountAutomaticBasic' => [
                'id',
                'title',
                'startsAt',
                'endsAt',
                'customerGets' => [
                    'items' => [
                        '... on DiscountProducts' => [
                            'products' => [
                                '__arguments__' => [
                                    'first' => '$perPage',
                                    'after' => '$endCursor',
                                ],
                                'nodes' => [
                                    'id',
                                ],
                                'pageInfo' => [
                                    'hasNextPage',
                                    'endCursor',
                                ],
                            ],
                        ],
                    ],
                    'value' => [
                        '__typename',
                        '... on DiscountAmount' => [
                            'amount' => [
                                'amount',
                                'currencyCode',
                            ],
                        ],
                        '... on DiscountOnQuantity' => [
                            'quantity' => [
                                'quantity',
                            ],
                            'effect' => [
                                '__typename',
                                '... on DiscountPercentage' => [
                                    'percentage',
                                ],
                            ],
                        ],
                        '... on DiscountPercentage' => [
                            'percentage',
                        ],
                    ],
                ],
                'minimumRequirement' => [
                    '__typename',
                    '... on DiscountMinimumQuantity' => [
                        'greaterThanOrEqualToQuantity',
                    ],
                    '... on DiscountMinimumSubtotal' => [
                        'greaterThanOrEqualToSubtotal' => [
                            'amount',
                            'currencyCode',
                        ],
                    ],
                ],
            ],
        ], [
            'wrapperFunctionName' => 'automaticDiscountBasicQueryByDiscountId',
            'functionHasNoArguments' => ['automaticDiscount'],
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id', '"$perPage"', '"$endCursor"'], ['$discountId: ID!', 'id: $discountId', '$perPage', '$endCursor'], $gql);

        $this->assertEquals('query automaticDiscountBasicQueryByDiscountId(
  $perPage: Int!,
  $endCursor: String,
  $discountId: ID!
) {
  automaticDiscountNode(
    id: $discountId
  ) {
    id
    __typename
    automaticDiscount {
      __typename
      ... on DiscountAutomaticBasic {
        id
        title
        startsAt
        endsAt
        customerGets {
          items {
            ... on DiscountProducts {
              products (first:$perPage, after:$endCursor) {
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
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getAutomaticDiscountBasicQueryByDiscountId('ABC-123', true))
//        );

        $this->assertEquals(
            'query automaticDiscountBasicQueryByDiscountId($perPage: Int!, $discountId: ID!, $endCursor: String) {
            automaticDiscountNode (id: $discountId) {
               id
               automaticDiscount {
                 __typename
                 ... on DiscountAutomaticBasic {
                    id
                    title
                    startsAt
                    endsAt
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
               }
            }
        }',
            $graphqlQueryHelper->getAutomaticDiscountBasicQueryByDiscountId(null, false)
        );

        $this->assertEquals(
            'query automaticDiscountBasicQueryByDiscountId($perPage: Int!, $discountId: ID!, $endCursor: String) {
            automaticDiscountNode (id: $discountId) {
               id
               automaticDiscount {
                 __typename
                 ... on DiscountAutomaticBasic {
                    id
                    title
                    startsAt
                    endsAt
                    customerGets {
                       items {
                          
            ... on DiscountCollections {
               collections (first: $perPage , after: $endCursor) {
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
               }
            }
        }',
            $graphqlQueryHelper->getAutomaticDiscountBasicQueryByDiscountId('ABC-123', false)
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'automaticDiscountNode', 'automaticDiscount', [
//            'discountId' => [
//                'type' => 'ID!',
//                'variable' => 'id',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            '__typename',
            '... on DiscountAutomaticBasic' => [
                'id',
                'title',
                'startsAt',
                'endsAt',
                'customerGets' => [
                    'items' => [
                        '... on DiscountCollections' => [
                            'collections' => [
                                '__arguments__' => [
                                    'first' => '$perPage',
                                    'after' => '$endCursor',
                                ],
                                'nodes' => [
                                    'title',
                                    'id',
                                ],
                                'pageInfo' => [
                                    'hasNextPage',
                                    'endCursor',
                                ],
                            ],
                        ],
                    ],
                    'value' => [
                        '__typename',
                        '... on DiscountAmount' => [
                            'amount' => [
                                'amount',
                                'currencyCode',
                            ],
                        ],
                        '... on DiscountOnQuantity' => [
                            'quantity' => [
                                'quantity',
                            ],
                            'effect' => [
                                '__typename',
                                '... on DiscountPercentage' => [
                                    'percentage',
                                ],
                            ],
                        ],
                        '... on DiscountPercentage' => [
                            'percentage',
                        ],
                    ],
                ],
                'minimumRequirement' => [
                    '__typename',
                    '... on DiscountMinimumQuantity' => [
                        'greaterThanOrEqualToQuantity',
                    ],
                    '... on DiscountMinimumSubtotal' => [
                        'greaterThanOrEqualToSubtotal' => [
                            'amount',
                            'currencyCode',
                        ],
                    ],
                ],
            ],
        ], [
            'wrapperFunctionName' => 'automaticDiscountBasicQueryByDiscountId',
            'functionHasNoArguments' => ['automaticDiscount'],
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id', '"$perPage"', '"$endCursor"'], ['$discountId: ID!', 'id: $discountId', '$perPage', '$endCursor'], $gql);

        $this->assertEquals('query automaticDiscountBasicQueryByDiscountId(
  $perPage: Int!,
  $endCursor: String,
  $discountId: ID!
) {
  automaticDiscountNode(
    id: $discountId
  ) {
    id
    __typename
    automaticDiscount {
      __typename
      ... on DiscountAutomaticBasic {
        id
        title
        startsAt
        endsAt
        customerGets {
          items {
            ... on DiscountCollections {
              collections (first:$perPage, after:$endCursor) {
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
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getAutomaticDiscountBasicQueryByDiscountId('ABC-123', false))
//        );
    }

    public function testGetDiscountBasicQueryByDiscountId()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query discountBasicQueryByDiscountId($perPage: Int!, $discountId: ID!) {
            automaticDiscountNode (id: $discountId) {
               automaticDiscount {
                 ... on DiscountAutomaticBasic {
                    customerGets {
                        items {
                            
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
                    }
                 }
               }
            }
        }',
            $graphqlQueryHelper->getDiscountBasicQueryByDiscountId('products', null)
        );

        $this->assertEquals(
            'query discountBasicQueryByDiscountId($perPage: Int!, $discountId: ID!, $endCursor: String) {
            automaticDiscountNode (id: $discountId) {
               automaticDiscount {
                 ... on DiscountAutomaticBasic {
                    customerGets {
                        items {
                            
            ... on DiscountProducts {
               products (first: $perPage , after: $endCursor) {
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
                    }
                 }
               }
            }
        }',
            $graphqlQueryHelper->getDiscountBasicQueryByDiscountId('products', 'ABC-123')
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'automaticDiscountNode', 'automaticDiscount', [
//            'discountId' => [
//                'type' => 'ID!',
//                'variable' => 'id',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            '... on DiscountAutomaticBasic' => [
                'customerGets' => [
                    'items' => [
                        '... on DiscountProducts' => [
                            'products' => [
                                '__arguments__' => [
                                    'first' => '$perPage',
                                    'after' => '$endCursor',
                                ],
                                'nodes' => [
                                    'id',
                                ],
                                'pageInfo' => [
                                    'hasNextPage',
                                    'endCursor',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], [
            'wrapperFunctionName' => 'discountBasicQueryByDiscountId',
            'functionHasNoArguments' => ['automaticDiscount'],
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id', '"$perPage"', '"$endCursor"'], ['$discountId: ID!', 'id: $discountId', '$perPage', '$endCursor'], $gql);

        $this->assertEquals('query discountBasicQueryByDiscountId(
  $perPage: Int!,
  $endCursor: String,
  $discountId: ID!
) {
  automaticDiscountNode(
    id: $discountId
  ) {
    id
    __typename
    automaticDiscount {
      ... on DiscountAutomaticBasic {
        customerGets {
          items {
            ... on DiscountProducts {
              products (first:$perPage, after:$endCursor) {
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
        }
      }
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getDiscountBasicQueryByDiscountId('products', 'ABC-123'))
//        );

        $this->assertEquals(
            'query discountBasicQueryByDiscountId($perPage: Int!, $discountId: ID!) {
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
        }',
            $graphqlQueryHelper->getDiscountBasicQueryByDiscountId(null, null)
        );

        $this->assertEquals(
            'query discountBasicQueryByDiscountId($perPage: Int!, $discountId: ID!, $endCursor: String) {
            automaticDiscountNode (id: $discountId) {
               automaticDiscount {
                 ... on DiscountAutomaticBasic {
                    customerGets {
                        items {
                            
            ... on DiscountCollections {
               collections (first: $perPage , after: $endCursor) {
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
        }',
            $graphqlQueryHelper->getDiscountBasicQueryByDiscountId(null, 'ABC-123')
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'automaticDiscountNode', 'automaticDiscount', [
//            'discountId' => [
//                'type' => 'ID!',
//                'variable' => 'id',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            '... on DiscountAutomaticBasic' => [
                'customerGets' => [
                    'items' => [
                        '... on DiscountCollections' => [
                            'collections' => [
                                '__arguments__' => [
                                    'first' => '$perPage',
                                    'after' => '$endCursor',
                                ],
                                'nodes' => [
                                    'title',
                                    'id',
                                ],
                                'pageInfo' => [
                                    'hasNextPage',
                                    'endCursor',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], [
            'wrapperFunctionName' => 'discountBasicQueryByDiscountId',
            'functionHasNoArguments' => ['automaticDiscount'],
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id', '"$perPage"', '"$endCursor"'], ['$discountId: ID!', 'id: $discountId', '$perPage', '$endCursor'], $gql);

        $this->assertEquals('query discountBasicQueryByDiscountId(
  $perPage: Int!,
  $endCursor: String,
  $discountId: ID!
) {
  automaticDiscountNode(
    id: $discountId
  ) {
    id
    __typename
    automaticDiscount {
      ... on DiscountAutomaticBasic {
        customerGets {
          items {
            ... on DiscountCollections {
              collections (first:$perPage, after:$endCursor) {
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
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getDiscountBasicQueryByDiscountId(null, 'ABC-123'))
//        );
    }

    public function testGetDiscountBxgyQueryByDiscountId()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query discountBxgyQueryByDiscountId($perPage: Int!, $discountId: ID!) {
            automaticDiscountNode (id: $discountId) {
               automaticDiscount {
                 ... on DiscountAutomaticBxgy {
                    customerBuys {
                        items {
                            
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
                    }
                 }
               }
            }
        }',
            $graphqlQueryHelper->getDiscountBxgyQueryByDiscountId('products', null)
        );

        $this->assertEquals(
            'query discountBxgyQueryByDiscountId($perPage: Int!, $discountId: ID!, $endCursor: String) {
            automaticDiscountNode (id: $discountId) {
               automaticDiscount {
                 ... on DiscountAutomaticBxgy {
                    customerBuys {
                        items {
                            
            ... on DiscountProducts {
               products (first: $perPage , after: $endCursor) {
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
                    }
                 }
               }
            }
        }',
            $graphqlQueryHelper->getDiscountBxgyQueryByDiscountId('products', 'ABC-123')
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'automaticDiscountNode', 'automaticDiscount', [
//            'discountId' => [
//                'type' => 'ID!',
//                'variable' => 'id',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            '... on DiscountAutomaticBxgy' => [
                'customerBuys' => [
                    'items' => [
                        '... on DiscountProducts' => [
                            'products' => [
                                '__arguments__' => [
                                    'first' => '$perPage',
                                    'after' => '$endCursor',
                                ],
                                'nodes' => [
                                    'id',
                                ],
                                'pageInfo' => [
                                    'hasNextPage',
                                    'endCursor',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], [
            'wrapperFunctionName' => 'discountBxgyQueryByDiscountId',
            'functionHasNoArguments' => ['automaticDiscount'],
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id', '"$perPage"', '"$endCursor"'], ['$discountId: ID!', 'id: $discountId', '$perPage', '$endCursor'], $gql);

        $this->assertEquals('query discountBxgyQueryByDiscountId(
  $perPage: Int!,
  $endCursor: String,
  $discountId: ID!
) {
  automaticDiscountNode(
    id: $discountId
  ) {
    id
    __typename
    automaticDiscount {
      ... on DiscountAutomaticBxgy {
        customerBuys {
          items {
            ... on DiscountProducts {
              products (first:$perPage, after:$endCursor) {
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
        }
      }
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getDiscountBxgyQueryByDiscountId('products', 'ABC-123'))
//        );

        $this->assertEquals(
            'query discountBxgyQueryByDiscountId($perPage: Int!, $discountId: ID!) {
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
        }',
            $graphqlQueryHelper->getDiscountBxgyQueryByDiscountId(null, null)
        );

        $this->assertEquals(
            'query discountBxgyQueryByDiscountId($perPage: Int!, $discountId: ID!, $endCursor: String) {
            automaticDiscountNode (id: $discountId) {
               automaticDiscount {
                 ... on DiscountAutomaticBxgy {
                    customerBuys {
                        items {
                            
            ... on DiscountCollections {
               collections (first: $perPage , after: $endCursor) {
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
        }',
            $graphqlQueryHelper->getDiscountBxgyQueryByDiscountId(null, 'ABC-123')
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'automaticDiscountNode', 'automaticDiscount', [
//            'discountId' => [
//                'type' => 'ID!',
//                'variable' => 'id',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            '... on DiscountAutomaticBxgy' => [
                'customerBuys' => [
                    'items' => [
                        '... on DiscountCollections' => [
                            'collections' => [
                                '__arguments__' => [
                                    'first' => '$perPage',
                                    'after' => '$endCursor',
                                ],
                                'nodes' => [
                                    'title',
                                    'id',
                                ],
                                'pageInfo' => [
                                    'hasNextPage',
                                    'endCursor',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], [
            'wrapperFunctionName' => 'discountBxgyQueryByDiscountId',
            'functionHasNoArguments' => ['automaticDiscount'],
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id', '"$perPage"', '"$endCursor"'], ['$discountId: ID!', 'id: $discountId', '$perPage', '$endCursor'], $gql);

        $this->assertEquals('query discountBxgyQueryByDiscountId(
  $perPage: Int!,
  $endCursor: String,
  $discountId: ID!
) {
  automaticDiscountNode(
    id: $discountId
  ) {
    id
    __typename
    automaticDiscount {
      ... on DiscountAutomaticBxgy {
        customerBuys {
          items {
            ... on DiscountCollections {
              collections (first:$perPage, after:$endCursor) {
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
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getDiscountBxgyQueryByDiscountId(null, 'ABC-123'))
//        );
    }

    public function testGetAutomaticDiscountBxgyQueryByDiscountId()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query automaticDiscountBxgyQueryByDiscountId($perPage: Int!, $discountId: ID!, $endCursor: String) {
            automaticDiscountNode (id: $discountId) {
               id
               automaticDiscount {
                 __typename
                 ... on DiscountAutomaticBxgy {
                    combinesWith {
                       productDiscounts
                    }                 
                    id
                    title
                    startsAt
                    endsAt
                    customerGets {
                       
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
               }
            }
        }',
            $graphqlQueryHelper->getAutomaticDiscountBxgyQueryByDiscountId(null, true)
        );

        $this->assertEquals(
            'query automaticDiscountBxgyQueryByDiscountId($perPage: Int!, $discountId: ID!, $endCursor: String) {
            automaticDiscountNode (id: $discountId) {
               id
               automaticDiscount {
                 __typename
                 ... on DiscountAutomaticBxgy {
                    combinesWith {
                       productDiscounts
                    }                 
                    id
                    title
                    startsAt
                    endsAt
                    customerGets {
                       
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
                           
            ... on DiscountProducts {
               products (first: $perPage , after: $endCursor) {
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
               }
            }
        }',
            $graphqlQueryHelper->getAutomaticDiscountBxgyQueryByDiscountId('ABC-123', true)
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'automaticDiscountNode', 'automaticDiscount', [
//            'discountId' => [
//                'type' => 'ID!',
//                'variable' => 'id',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            '__typename',
            '... on DiscountAutomaticBxgy' => [
                'combinesWith' => [
                    'productDiscounts',
                ],
                'id',
                'title',
                'startsAt',
                'endsAt',
                'customerGets' => [
                    'value' => [
                        '__typename',
                        '... on DiscountAmount' => [
                            'amount' => [
                                'amount',
                                'currencyCode',
                            ],
                        ],
                        '... on DiscountOnQuantity' => [
                            'quantity' => [
                                'quantity',
                            ],
                            'effect' => [
                                '__typename',
                                '... on DiscountPercentage' => [
                                    'percentage',
                                ],
                            ],
                        ],
                        '... on DiscountPercentage' => [
                            'percentage',
                        ],
                    ],
                ],
                'customerBuys' => [
                    'items' => [
                        '... on DiscountProducts' => [
                            'products' => [
                                '__arguments__' => [
                                    'first' => '$perPage',
                                    'after' => '$endCursor',
                                ],
                                'nodes' => [
                                    'id',
                                ],
                                'pageInfo' => [
                                    'hasNextPage',
                                    'endCursor',
                                ],
                            ],
                        ],
                    ],
                    'value' => [
                        '__typename',
                        '... on DiscountPurchaseAmount' => [
                            'amount',
                        ],
                        '... on DiscountQuantity' => [
                            'quantity',
                        ],
                    ],
                ],
            ],
        ], [
            'wrapperFunctionName' => 'automaticDiscountBxgyQueryByDiscountId',
            'functionHasNoArguments' => ['automaticDiscount'],
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id', '"$perPage"', '"$endCursor"'], ['$discountId: ID!', 'id: $discountId', '$perPage', '$endCursor'], $gql);

        $this->assertEquals('query automaticDiscountBxgyQueryByDiscountId(
  $perPage: Int!,
  $endCursor: String,
  $discountId: ID!
) {
  automaticDiscountNode(
    id: $discountId
  ) {
    id
    __typename
    automaticDiscount {
      __typename
      ... on DiscountAutomaticBxgy {
        combinesWith {
          productDiscounts
        }
        id
        title
        startsAt
        endsAt
        customerGets {
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
            ... on DiscountProducts {
              products (first:$perPage, after:$endCursor) {
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
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getAutomaticDiscountBxgyQueryByDiscountId('ABC-123', true))
//        );

        $this->assertEquals(
            'query automaticDiscountBxgyQueryByDiscountId($perPage: Int!, $discountId: ID!, $endCursor: String) {
            automaticDiscountNode (id: $discountId) {
               id
               automaticDiscount {
                 __typename
                 ... on DiscountAutomaticBxgy {
                    combinesWith {
                       productDiscounts
                    }                 
                    id
                    title
                    startsAt
                    endsAt
                    customerGets {
                       
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
               }
            }
        }',
            $graphqlQueryHelper->getAutomaticDiscountBxgyQueryByDiscountId(null, false)
        );

        $this->assertEquals(
            'query automaticDiscountBxgyQueryByDiscountId($perPage: Int!, $discountId: ID!, $endCursor: String) {
            automaticDiscountNode (id: $discountId) {
               id
               automaticDiscount {
                 __typename
                 ... on DiscountAutomaticBxgy {
                    combinesWith {
                       productDiscounts
                    }                 
                    id
                    title
                    startsAt
                    endsAt
                    customerGets {
                       
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
               collections (first: $perPage , after: $endCursor) {
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
               }
            }
        }',
            $graphqlQueryHelper->getAutomaticDiscountBxgyQueryByDiscountId('ABC-123', false)
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'automaticDiscountNode', 'automaticDiscount', [
//            'discountId' => [
//                'type' => 'ID!',
//                'variable' => 'id',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            '__typename',
            '... on DiscountAutomaticBxgy' => [
                'combinesWith' => [
                    'productDiscounts',
                ],
                'id',
                'title',
                'startsAt',
                'endsAt',
                'customerGets' => [
                    'value' => [
                        '__typename',
                        '... on DiscountAmount' => [
                            'amount' => [
                                'amount',
                                'currencyCode',
                            ],
                        ],
                        '... on DiscountOnQuantity' => [
                            'quantity' => [
                                'quantity',
                            ],
                            'effect' => [
                                '__typename',
                                '... on DiscountPercentage' => [
                                    'percentage',
                                ],
                            ],
                        ],
                        '... on DiscountPercentage' => [
                            'percentage',
                        ],
                    ],
                ],
                'customerBuys' => [
                    'items' => [
                        '... on DiscountCollections' => [
                            'collections' => [
                                '__arguments__' => [
                                    'first' => '$perPage',
                                    'after' => '$endCursor',
                                ],
                                'nodes' => [
                                    'title',
                                    'id',
                                ],
                                'pageInfo' => [
                                    'hasNextPage',
                                    'endCursor',
                                ],
                            ],
                        ],
                    ],
                    'value' => [
                        '__typename',
                        '... on DiscountPurchaseAmount' => [
                            'amount',
                        ],
                        '... on DiscountQuantity' => [
                            'quantity',
                        ],
                    ],
                ],
            ],
        ], [
            'wrapperFunctionName' => 'automaticDiscountBxgyQueryByDiscountId',
            'functionHasNoArguments' => ['automaticDiscount'],
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id', '"$perPage"', '"$endCursor"'], ['$discountId: ID!', 'id: $discountId', '$perPage', '$endCursor'], $gql);

        $this->assertEquals('query automaticDiscountBxgyQueryByDiscountId(
  $perPage: Int!,
  $endCursor: String,
  $discountId: ID!
) {
  automaticDiscountNode(
    id: $discountId
  ) {
    id
    __typename
    automaticDiscount {
      __typename
      ... on DiscountAutomaticBxgy {
        combinesWith {
          productDiscounts
        }
        id
        title
        startsAt
        endsAt
        customerGets {
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
              collections (first:$perPage, after:$endCursor) {
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
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getAutomaticDiscountBxgyQueryByDiscountId('ABC-123', false))
//        );
    }

    public function testGetProductVariantQueryBySKUs()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query productVariantsById {
            productVariants(first: 100, query: "") {
               nodes {
                  sku
                  product {
                     averageReviewScore: metafield(
                        namespace: "mw_marketing"
                        key: "average_review_score"
                     ) {
                        value
                     }
                     reviewCount: metafield(namespace: "mw_marketing", key: "review_count") {
                        value
                     }
                     benefits: metafield(namespace: "mw_features", key: "benefits") {
                        value
                     }
                     featuredImage {
                        url
                        altText
                        width
                        height
                     }
                     handle
                     title
                     id
                  }
               }
            }
        }',
            $graphqlQueryHelper->getProductVariantQueryBySKUs([])
        );

        $this->assertEquals(
            'query productVariantsById {
            productVariants(first: 100, query: "sku:ABC-123") {
               nodes {
                  sku
                  product {
                     averageReviewScore: metafield(
                        namespace: "mw_marketing"
                        key: "average_review_score"
                     ) {
                        value
                     }
                     reviewCount: metafield(namespace: "mw_marketing", key: "review_count") {
                        value
                     }
                     benefits: metafield(namespace: "mw_features", key: "benefits") {
                        value
                     }
                     featuredImage {
                        url
                        altText
                        width
                        height
                     }
                     handle
                     title
                     id
                  }
               }
            }
        }',
            $graphqlQueryHelper->getProductVariantQueryBySKUs([
                0 => ['sku' => 'ABC-123'],
            ])
        );

        $this->assertEquals(
            'query productVariantsById {
            productVariants(first: 100, query: "sku:ABC-123 OR sku:DEF-456") {
               nodes {
                  sku
                  product {
                     averageReviewScore: metafield(
                        namespace: "mw_marketing"
                        key: "average_review_score"
                     ) {
                        value
                     }
                     reviewCount: metafield(namespace: "mw_marketing", key: "review_count") {
                        value
                     }
                     benefits: metafield(namespace: "mw_features", key: "benefits") {
                        value
                     }
                     featuredImage {
                        url
                        altText
                        width
                        height
                     }
                     handle
                     title
                     id
                  }
               }
            }
        }',
            $graphqlQueryHelper->getProductVariantQueryBySKUs([
                0 => ['sku' => 'ABC-123'],
                1 => ['sku' => 'DEF-456'],
            ])
        );

        $this->assertEquals(
            'query productVariantsById {
            productVariants(first: 100, query: "sku:ABC-123 OR sku:DEF-456 OR sku:GHI-789") {
               nodes {
                  sku
                  product {
                     averageReviewScore: metafield(
                        namespace: "mw_marketing"
                        key: "average_review_score"
                     ) {
                        value
                     }
                     reviewCount: metafield(namespace: "mw_marketing", key: "review_count") {
                        value
                     }
                     benefits: metafield(namespace: "mw_features", key: "benefits") {
                        value
                     }
                     featuredImage {
                        url
                        altText
                        width
                        height
                     }
                     handle
                     title
                     id
                  }
               }
            }
        }',
            $graphqlQueryHelper->getProductVariantQueryBySKUs([
                0 => ['sku' => 'ABC-123'],
                1 => ['sku' => 'DEF-456'],
                2 => ['sku' => 'GHI-789'],
            ])
        );

        $gql = $this->generateGraphQL('query', 'productVariants', [
            'perPage' => [
                'type' => 'String',
                'variable' => 'first',
                'optional' => true,
                'directValue' => 100,
            ],
            'query' => [
                'type' => 'String',
                'variable' => 'query',
                'optional' => true,
                'directValue' => 'sku:ABC-123 OR sku:DEF-456 OR sku:GHI-789',
            ],
        ], [
            'nodes' => [
                'sku',
                'product' => [
                    'averageReviewScore: metafield' => [
                        '__arguments__' => [
                            'namespace' => 'mw_marketing',
                            'key' => 'average_review_score',
                        ],
                        'value',
                    ],
                    'reviewCount: metafield' => [
                        '__arguments__' => [
                            'namespace' => 'mw_marketing',
                            'key' => 'review_count',
                        ],
                        'value',
                    ],
                    'benefits: metafield' => [
                        '__arguments__' => [
                            'namespace' => 'mw_features',
                            'key' => 'benefits',
                        ],
                        'value',
                    ],
                    'featuredImage' => [
                        'url',
                        'altText',
                        'width',
                        'height',
                    ],
                    'handle',
                    'title',
                    'id',
                ],
            ],
        ], [
            'wrapperFunctionName' => 'productVariantsById',
            'wrapperFunctionHasNoArguments' => ['productVariantsById'],
        ]);

        $this->assertEquals('query productVariantsById {
  productVariants(
    first: 100,
    query: "sku:ABC-123 OR sku:DEF-456 OR sku:GHI-789"
  ) {
    nodes {
      sku
      product {
        averageReviewScore: metafield (namespace:"mw_marketing", key:"average_review_score") {
          value
        }
        reviewCount: metafield (namespace:"mw_marketing", key:"review_count") {
          value
        }
        benefits: metafield (namespace:"mw_features", key:"benefits") {
          value
        }
        featuredImage {
          url
          altText
          width
          height
        }
        handle
        title
        id
      }
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getProductVariantQueryBySKUs([
//                0 => ['sku' => 'ABC-123'],
//                1 => ['sku' => 'DEF-456'],
//                2 => ['sku' => 'GHI-789'],
//            ]))
//        );
    }

    public function testGetProductVariantQueryByIds()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query productVariantsByIds($ids: [ID!]!) {
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
        }',
            $graphqlQueryHelper->getProductVariantQueryByIds()
        );

        $gql = $this->generateGraphQL('query', 'nodes', [
            'ids' => [
                'type' => '[ID!]!',
            ],
        ], [
            'id',
            '... on Product' => [
                'variants' => [
                    '__arguments__' => [
                        'first' => 250,
                    ],
                    'nodes' => [
                        'id',
                        'sku',
                    ],
                ],
            ],
            '... on ProductVariant' => [
                'sku',
                'product' => [
                    'id',
                ],
            ],
        ], [
            'wrapperFunctionName' => 'productVariantsByIds',
        ]);

        $this->assertEquals('query productVariantsByIds(
  $ids: [ID!]!
) {
  nodes(
    ids: $ids
  ) {
    id
    ... on Product {
      variants (first:250) {
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
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getProductVariantQueryByIds())
//        );
    }

    public function testGetProductQueryByIds()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query productsByIds($ids: [ID!]!) {
                nodes(ids: $ids) {
                  ... on Product {
                    id
                    variants(first: 250) {
                      nodes {
                        metafield_available_to_try: metafield(namespace: "available_to_try", key: "ABC-123") {
                          value
                        }
                      }
                    }
                  }
                }
              }',
            $graphqlQueryHelper->getProductQueryByIds('ABC-123')
        );

        $this->assertEquals(
            'query productsByIds($ids: [ID!]!) {
                nodes(ids: $ids) {
                  ... on Product {
                    id
                    variants(first: 250) {
                      nodes {
                        metafield_available_to_try: metafield(namespace: "available_to_try", key: "") {
                          value
                        }
                      }
                    }
                  }
                }
              }',
            $graphqlQueryHelper->getProductQueryByIds(null)
        );

        $this->assertEquals(
            'query productsByIds($ids: [ID!]!) {
                nodes(ids: $ids) {
                  ... on Product {
                    id
                    variants(first: 250) {
                      nodes {
                        metafield_available_to_try: metafield(namespace: "available_to_try", key: "ABC-123") {
                          value
                        }
                      }
                    }
                  }
                }
              }',
            $graphqlQueryHelper->getProductQueryByIds('ABC-123', ['allowEmptyLocationId' => true])
        );

        $locationId = 'ABC-123';

        $gql = $this->generateGraphQL('query', 'nodes', [
            'ids' => [
                'type' => '[ID!]!',
            ],
        ], [
            '... on Product' => [
                'id',
                'variants' => [
                    '__arguments__' => [
                        'first' => 250,
                    ],
                    'nodes' => [
                        'metafield_available_to_try: metafield' => [
                            '__arguments__' => [
                                'namespace' => 'available_to_try',
                                'key' => $locationId,
                            ],
                            'value',
                        ],
                    ],
                ],
            ],
        ], [
            'wrapperFunctionName' => 'productsByIds',
        ]);

        $this->assertEquals('query productsByIds(
  $ids: [ID!]!
) {
  nodes(
    ids: $ids
  ) {
    ... on Product {
      id
      variants (first:250) {
        nodes {
          metafield_available_to_try: metafield (namespace:"available_to_try", key:"ABC-123") {
            value
          }
        }
      }
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getProductQueryByIds('ABC-123', ['allowEmptyLocationId' => true]))
//        );

        $this->assertEquals(
            'query productsByIds($ids: [ID!]!) {
              nodes(ids: $ids) {
                ... on Product {
                  id
                }
              }
            }',
            $graphqlQueryHelper->getProductQueryByIds(null, ['allowEmptyLocationId' => true])
        );

        $gql = $this->generateGraphQL('query', 'nodes', [
            'ids' => [
                'type' => '[ID!]!',
            ],
        ], [
            '... on Product' => [
                'id',
            ],
        ], [
            'wrapperFunctionName' => 'productsByIds',
        ]);

        $this->assertEquals('query productsByIds(
  $ids: [ID!]!
) {
  nodes(
    ids: $ids
  ) {
    ... on Product {
      id
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getProductQueryByIds(null, ['allowEmptyLocationId' => true]))
//        );
    }

    public function testGetCollectionQueryByCollectionId()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query collectionQueryByCollectionId($collectionId: ID!, $perPage: Int!) {
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
        ',
            $graphqlQueryHelper->getCollectionQueryByCollectionId()
        );

        $this->assertEquals(
            'query collectionQueryByCollectionId($collectionId: ID!, $perPage: Int! $endCursor: String) {
            collection(id: $collectionId) {
               products(first: $perPage, after: $endCursor) {
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
        ',
            $graphqlQueryHelper->getCollectionQueryByCollectionId('ABC-123')
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'collection', 'products', [
//            'collectionId' => [
//                'type' => 'ID!',
//                'variable' => 'id',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            'nodes' => [
                'id',
            ],
            'pageInfo' => [
                'hasNextPage',
                'endCursor',
            ],
        ], [
            'wrapperFunctionName' => 'collectionQueryByCollectionId',
        ]);
        $gql = str_replace(['$id: ID!', 'id: $id'], ['$collectionId: ID!', 'id: $collectionId'], $gql);

        $this->assertEquals('query collectionQueryByCollectionId(
  $perPage: Int!,
  $endCursor: String,
  $collectionId: ID!
) {
  collection(
    id: $collectionId
  ) {
    id
    __typename
    products(
      first: $perPage,
      after: $endCursor
    ) {
      nodes {
        id
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getCollectionQueryByCollectionId('ABC-123'))
//        );
    }

    public function testGetMetafieldsSetQuery()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            '
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
        ',
            $graphqlQueryHelper->getMetafieldsSetQuery()
        );

        $gqlMutation = $this->getGraphQLMutationForMetafieldSet(null, null, null, [
            'fields' => [
                'key',
                'namespace',
                'value',
                'createdAt',
                'updatedAt',
            ],
        ]);

        $this->assertSame([
            0 => 'mutation shopMetafieldSet(
  $metafields: [MetafieldsSetInput!]!
) {
  metafieldsSet(
    metafields: $metafields
  ) {
    metafields {
      id
      __typename
      key
      namespace
      value
      createdAt
      updatedAt
    }
    userErrors {
      code
      elementIndex
      field
      message
    }
  }
}',
            1 => [
                'metafields' => [
                    0 => [
                        'ownerId' => '',
                        'key' => '',
                        'value' => '',
                    ],
                ],
            ],
            2 => 'metafieldsSet',
            3 => 'metafields',
        ], $gqlMutation);

        $gqlMutation = $this->getGraphQLMutationForMetafieldSet(null, null, 'ABC-123', [
            'fields' => [
                'key',
                'namespace',
                'value',
                'createdAt',
                'updatedAt',
            ],
            'metafield' => [
                'namespace' => 'DEF-456',
                'key' => 'GHI-789',
                'value' => 'testing1234',
            ],
        ]);

        $this->assertSame([
            0 => 'mutation shopMetafieldSet(
  $metafields: [MetafieldsSetInput!]!
) {
  metafieldsSet(
    metafields: $metafields
  ) {
    metafields {
      id
      __typename
      key
      namespace
      value
      createdAt
      updatedAt
    }
    userErrors {
      code
      elementIndex
      field
      message
    }
  }
}',
            1 => [
                'metafields' => [
                    0 => [
                        'ownerId' => 'ABC-123',
                        'namespace' => 'DEF-456',
                        'key' => 'GHI-789',
                        'value' => 'testing1234',
                    ],
                ],
            ],
            2 => 'metafieldsSet',
            3 => 'metafields',
        ], $gqlMutation);

        $gql = $gqlMutation[0];
        $gql = str_replace('shopMetafieldSet', 'MetafieldsSet', $gql);

        $this->assertEquals('mutation MetafieldsSet(
  $metafields: [MetafieldsSetInput!]!
) {
  metafieldsSet(
    metafields: $metafields
  ) {
    metafields {
      id
      __typename
      key
      namespace
      value
      createdAt
      updatedAt
    }
    userErrors {
      code
      elementIndex
      field
      message
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getMetafieldsSetQuery())
//        );
    }

    public function testGetProductVariantUpdateQuery()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            '
            mutation productVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
              productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                userErrors {
                  field
                  message
                }
              }
            }
        ',
            $graphqlQueryHelper->getProductVariantUpdateQuery()
        );

        $gqlMutation = $this->getGraphQLMutationForProductVariantUpdate(null, null, [
            'fields' => [
                'id',
            ],
        ]);

        $this->assertSame([
            0 => 'mutation productVariantUpdate(
  $productId: ID!,
  $variants: [ProductVariantsBulkInput!]!
) {
  productVariantsBulkUpdate(
    productId: $productId,
    variants: $variants
  ) {
    productVariants {
      id
      __typename
    }
    userErrors {
      code
      field
      message
    }
  }
}',
            1 => [
                'productId' => null,
                'variants' => [
                    0 => [
                        'id' => null,
                    ],
                ],
            ],
            2 => 'productVariantsBulkUpdate',
            3 => 'productVariants',
        ], $gqlMutation);

        $gqlMutation = $this->getGraphQLMutationForProductVariantUpdate('123', '456', [
            'fields' => [
                'id',
            ],
            'sku' => 'ABC-123',
        ]);

        $this->assertSame([
            0 => 'mutation productVariantUpdate(
  $productId: ID!,
  $variants: [ProductVariantsBulkInput!]!
) {
  productVariantsBulkUpdate(
    productId: $productId,
    variants: $variants
  ) {
    productVariants {
      id
      __typename
    }
    userErrors {
      code
      field
      message
    }
  }
}',
            1 => [
                'productId' => 'gid://shopify/Product/123',
                'variants' => [
                    0 => [
                        'id' => 'gid://shopify/ProductVariant/456',
                        'inventoryItem' => [
                            'sku' => 'ABC-123',
                        ],
                    ],
                ],
            ],
            2 => 'productVariantsBulkUpdate',
            3 => 'productVariants',
        ], $gqlMutation);

        $gql = $gqlMutation[0];
        $gql = str_replace('productVariantUpdate(', 'productVariantsBulkUpdate(', $gql);

        $this->assertEquals('mutation productVariantsBulkUpdate(
  $productId: ID!,
  $variants: [ProductVariantsBulkInput!]!
) {
  productVariantsBulkUpdate(
    productId: $productId,
    variants: $variants
  ) {
    productVariants {
      id
      __typename
    }
    userErrors {
      code
      field
      message
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getProductVariantUpdateQuery())
//        );
    }

    public function testGetDraftOrdersQueryByTag()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query queryDraftOrdersByTag($perPage: Int!) {
            draftOrders(first: $perPage, sortKey:UPDATED_AT, reverse:true, query: "customer_id:* (NOT status:COMPLETED AND NOT status:INVOICE_SENT AND NOT tag:StorisID:*)") {
                nodes {
                    id
                    name
                    status
                    invoiceUrl
                    tags
                    createdAt
                    updatedAt
                    purchasingEntity {
                        ... on Customer {
                            id
                            firstName
                            lastName
                            phone
                            email
                            emailMarketingConsent {
                                marketingState
                                marketingOptInLevel
                                consentUpdatedAt
                            }
                            smsMarketingConsent {
                                marketingState
                                marketingOptInLevel
                                consentUpdatedAt
                            }
                        }
                    }
                    lineItems(first: 50) {
                        nodes {
                            id
                        }
                    }
                }
                pageInfo {
                    hasNextPage
                    endCursor
                }
            }
        }',
            $graphqlQueryHelper->getDraftOrdersQueryByTag()
        );

        $this->assertEquals(
            'query queryDraftOrdersByTag($perPage: Int!, $endCursor: String) {
            draftOrders(first: $perPage, sortKey:UPDATED_AT, reverse:true, after: $endCursor, query: "customer_id:* (NOT status:COMPLETED AND NOT status:INVOICE_SENT AND NOT tag:StorisID:*)") {
                nodes {
                    id
                    name
                    status
                    invoiceUrl
                    tags
                    createdAt
                    updatedAt
                    purchasingEntity {
                        ... on Customer {
                            id
                            firstName
                            lastName
                            phone
                            email
                            emailMarketingConsent {
                                marketingState
                                marketingOptInLevel
                                consentUpdatedAt
                            }
                            smsMarketingConsent {
                                marketingState
                                marketingOptInLevel
                                consentUpdatedAt
                            }
                        }
                    }
                    lineItems(first: 50) {
                        nodes {
                            id
                        }
                    }
                }
                pageInfo {
                    hasNextPage
                    endCursor
                }
            }
        }',
            $graphqlQueryHelper->getDraftOrdersQueryByTag(null, null, null, 'ABC-123')
        );

        $gqlQuery = $this->getGraphQLQueryForDraftOrders([
            'wrapperFunctionName' => 'queryDraftOrdersByTag',
            'queryArguments' => [
                'sortKey' => 'UPDATED_AT',
                'reverse' => 'true',
            ],
            'searchFilter' => [
                // TODO avoid this workaround
                'customer_id' => '* (NOT status:COMPLETED AND NOT status:INVOICE_SENT AND NOT tag:StorisID:*)',
            ],
            'fields' => [
                'id',
                'name',
                'status',
                'invoiceUrl',
                'tags',
                'createdAt',
                'updatedAt',
                'purchasingEntity', // TODO 2024-12-20 need to add this field in the base middleware
                'customer', // TODO 2024-12-20 using this temporarily instead of 'purchasingEntity'
                'lineItems', // TODO 2024-12-20 maybe need a sub-field, something like 'lineItems.nodes.id'
            ],
        ]);

        $this->assertSame([
            0 => 'query queryDraftOrdersByTag(
  $perPage: Int!,
  $endCursor: String
) {
  draftOrders(
    first: $perPage,
    after: $endCursor,
    query: "customer_id:* (NOT status:COMPLETED AND NOT status:INVOICE_SENT AND NOT tag:StorisID:*)",
    sortKey: UPDATED_AT,
    reverse: true
  ) {
    nodes {
      id
      __typename
      name
      status
      invoiceUrl
      tags
      createdAt
      updatedAt
      customer {
        id
        __typename
        firstName
        lastName
        displayName
        email
        phone
        lifetimeDuration
        locale
        multipassIdentifier
        note
        numberOfOrders
        productSubscriberStatus
        state
        tags
        taxExempt
        taxExemptions
        canDelete
        dataSaleOptOut
        validEmailAddress
        verifiedEmail
        createdAt
        updatedAt
        defaultAddress {
          id
          __typename
          name
          firstName
          lastName
          address1
          address2
          city
          province
          provinceCode
          zip
          country
          countryCodeV2
          phone
          company
          coordinatesValidated
          latitude
          longitude
          timeZone
          formattedArea
        }
        addresses {
          id
          __typename
          name
          firstName
          lastName
          address1
          address2
          city
          province
          provinceCode
          zip
          country
          countryCodeV2
          phone
          company
          coordinatesValidated
          latitude
          longitude
          timeZone
          formattedArea
        }
        amountSpent {
          amount
          currencyCode
        }
        emailMarketingConsent {
          consentUpdatedAt
          marketingOptInLevel
          marketingState
        }
        smsMarketingConsent {
          consentCollectedFrom
          consentUpdatedAt
          marketingOptInLevel
          marketingState
        }
      }
      lineItems (first:250) {
        nodes {
          id
          __typename
          uuid
          sku
          name
          title
          variantTitle
          vendor
          quantity
          custom
          isGiftCard
          requiresShipping
          taxable
          product {
            id
            __typename
            handle
            title
          }
          variant {
            id
            __typename
            sku
            barcode
            title
            displayName
            price
            compareAtPrice
          }
          customAttributes {
            key
            value
          }
          bundleComponents {
            id
            __typename
          }
          fulfillmentService {
            id
            __typename
            handle
          }
          weight {
            value
            unit
          }
          taxLines {
            title
            source
            channelLiable
            rate
            ratePercentage
            priceSet {
              presentmentMoney {
                amount
                currencyCode
              }
              shopMoney {
                amount
                currencyCode
              }
            }
          }
          appliedDiscount {
            title
            description
            value
            valueType
            amountSet {
              presentmentMoney {
                amount
                currencyCode
              }
              shopMoney {
                amount
                currencyCode
              }
            }
          }
          approximateDiscountedUnitPriceSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
          discountedTotalSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
          totalDiscountSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
          originalUnitPriceWithCurrency {
            amount
            currencyCode
          }
          originalUnitPriceSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
          originalTotalSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
        }
        pageInfo {
          startCursor
          endCursor
          hasPreviousPage
          hasNextPage
        }
      }
    }
    pageInfo {
      startCursor
      endCursor
      hasPreviousPage
      hasNextPage
    }
  }
}',
            1 => [
                'perPage' => 10,
                'endCursor' => null,
            ],
            2 => 'draftOrders',
        ], $gqlQuery);

        $gql = $gqlQuery[0];

        $this->assertEquals('query queryDraftOrdersByTag(
  $perPage: Int!,
  $endCursor: String
) {
  draftOrders(
    first: $perPage,
    after: $endCursor,
    query: "customer_id:* (NOT status:COMPLETED AND NOT status:INVOICE_SENT AND NOT tag:StorisID:*)",
    sortKey: UPDATED_AT,
    reverse: true
  ) {
    nodes {
      id
      __typename
      name
      status
      invoiceUrl
      tags
      createdAt
      updatedAt
      customer {
        id
        __typename
        firstName
        lastName
        displayName
        email
        phone
        lifetimeDuration
        locale
        multipassIdentifier
        note
        numberOfOrders
        productSubscriberStatus
        state
        tags
        taxExempt
        taxExemptions
        canDelete
        dataSaleOptOut
        validEmailAddress
        verifiedEmail
        createdAt
        updatedAt
        defaultAddress {
          id
          __typename
          name
          firstName
          lastName
          address1
          address2
          city
          province
          provinceCode
          zip
          country
          countryCodeV2
          phone
          company
          coordinatesValidated
          latitude
          longitude
          timeZone
          formattedArea
        }
        addresses {
          id
          __typename
          name
          firstName
          lastName
          address1
          address2
          city
          province
          provinceCode
          zip
          country
          countryCodeV2
          phone
          company
          coordinatesValidated
          latitude
          longitude
          timeZone
          formattedArea
        }
        amountSpent {
          amount
          currencyCode
        }
        emailMarketingConsent {
          consentUpdatedAt
          marketingOptInLevel
          marketingState
        }
        smsMarketingConsent {
          consentCollectedFrom
          consentUpdatedAt
          marketingOptInLevel
          marketingState
        }
      }
      lineItems (first:250) {
        nodes {
          id
          __typename
          uuid
          sku
          name
          title
          variantTitle
          vendor
          quantity
          custom
          isGiftCard
          requiresShipping
          taxable
          product {
            id
            __typename
            handle
            title
          }
          variant {
            id
            __typename
            sku
            barcode
            title
            displayName
            price
            compareAtPrice
          }
          customAttributes {
            key
            value
          }
          bundleComponents {
            id
            __typename
          }
          fulfillmentService {
            id
            __typename
            handle
          }
          weight {
            value
            unit
          }
          taxLines {
            title
            source
            channelLiable
            rate
            ratePercentage
            priceSet {
              presentmentMoney {
                amount
                currencyCode
              }
              shopMoney {
                amount
                currencyCode
              }
            }
          }
          appliedDiscount {
            title
            description
            value
            valueType
            amountSet {
              presentmentMoney {
                amount
                currencyCode
              }
              shopMoney {
                amount
                currencyCode
              }
            }
          }
          approximateDiscountedUnitPriceSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
          discountedTotalSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
          totalDiscountSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
          originalUnitPriceWithCurrency {
            amount
            currencyCode
          }
          originalUnitPriceSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
          originalTotalSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
        }
        pageInfo {
          startCursor
          endCursor
          hasPreviousPage
          hasNextPage
        }
      }
    }
    pageInfo {
      startCursor
      endCursor
      hasPreviousPage
      hasNextPage
    }
  }
}', $gql);

        $this->assertEquals(
            'query queryDraftOrdersByTag($perPage: Int!, $endCursor: String) {
                draftOrders(first: $perPage, sortKey:UPDATED_AT, reverse:true, after: $endCursor, query: "customer_id:* (NOT status:COMPLETED AND NOT status:INVOICE_SENT AND NOT tag:StorisID:*)") {
                    nodes {
                        id
                        name
                        status
                        invoiceUrl
                        tags
                        createdAt
                        updatedAt
                        purchasingEntity {
                            ... on Customer {
                                id
                                firstName
                                lastName
                                phone
                                email
                                emailMarketingConsent {
                                    marketingState
                                    marketingOptInLevel
                                    consentUpdatedAt
                                }
                                smsMarketingConsent {
                                    marketingState
                                    marketingOptInLevel
                                    consentUpdatedAt
                                }
                            }
                        }
                        currencyCode
                        presentmentCurrencyCode
                        lineItems(first: 50) {
                            nodes {
                                id
                                sku
                                quantity
                                name
                                title
                                variantTitle
                                vendor
                                image {
                                    id
                                    url
                                    altText
                                    width
                                    height
                                }
                                product {
                                    id
                                    handle
                                    title
                                    productType
                                    onlineStoreUrl
                                    featuredImage {
                                        id
                                        url
                                        altText
                                        width
                                        height
                                    }
                                }
                                variant {
                                    id
                                    sku
                                    barcode
                                    title
                                    displayName
                                    image {
                                        id
                                        url
                                        altText
                                        width
                                        height
                                    }
                                    inventoryItem {
                                        id
                                    }
                                    selectedOptions {
                                        name
                                        value
                                    }
                                    price
                                    compareAtPrice
                                }
                                originalUnitPrice
                                originalUnitPriceSet {
                                    presentmentMoney {
                                        amount
                                        currencyCode
                                    }
                                    shopMoney {
                                        amount
                                        currencyCode
                                    }
                                }
                                originalTotal
                                originalTotalSet {
                                    presentmentMoney {
                                        amount
                                        currencyCode
                                    }
                                    shopMoney {
                                        amount
                                        currencyCode
                                    }
                                }
                                appliedDiscount {
                                    title
                                    description
                                    value
                                    valueType
                                    amountV2 {
                                        amount
                                        currencyCode
                                    }
                                    amountSet {
                                        presentmentMoney {
                                            amount
                                            currencyCode
                                        }
                                        shopMoney {
                                            amount
                                            currencyCode
                                        }
                                    }
                                }
                                discountedUnitPrice
                                discountedUnitPriceSet {
                                    presentmentMoney {
                                        amount
                                        currencyCode
                                    }
                                    shopMoney {
                                        amount
                                        currencyCode
                                    }
                                }
                                discountedTotal
                                discountedTotalSet {
                                    presentmentMoney {
                                        amount
                                        currencyCode
                                    }
                                    shopMoney {
                                        amount
                                        currencyCode
                                    }
                                }
                                totalDiscount
                                totalDiscountSet {
                                    presentmentMoney {
                                        amount
                                        currencyCode
                                    }
                                    shopMoney {
                                        amount
                                        currencyCode
                                    }
                                }
                                taxLines {
                                    title
                                    rate
                                    ratePercentage
                                    priceSet {
                                        presentmentMoney {
                                            amount
                                            currencyCode
                                        }
                                        shopMoney {
                                            amount
                                            currencyCode
                                        }
                                    }
                                }
                            }
                        }
                        lineItemsSubtotalPrice {
                            presentmentMoney {
                                amount
                                currencyCode
                            }
                            shopMoney {
                                amount
                                currencyCode
                            }
                        }
                        totalLineItemsPriceSet {
                            presentmentMoney {
                                amount
                                currencyCode
                            }
                            shopMoney {
                                amount
                                currencyCode
                            }
                        }
                        appliedDiscount {
                            title
                            description
                            value
                            valueType
                            amountV2 {
                                amount
                                currencyCode
                            }
                            amountSet {
                                presentmentMoney {
                                    amount
                                    currencyCode
                                }
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                            }
                        }
                        totalDiscountsSet {
                            presentmentMoney {
                                amount
                                currencyCode
                            }
                            shopMoney {
                                amount
                                currencyCode
                            }
                        }
                        subtotalPrice
                        subtotalPriceSet {
                            presentmentMoney {
                                amount
                                currencyCode
                            }
                            shopMoney {
                                amount
                                currencyCode
                            }
                        }
                        shippingLine {
                            id
                            code
                            title
                            originalPriceSet {
                                presentmentMoney {
                                    amount
                                    currencyCode
                                }
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                            }
                            discountAllocations {
                                allocatedAmountSet {
                                    presentmentMoney {
                                        amount
                                        currencyCode
                                    }
                                    shopMoney {
                                        amount
                                        currencyCode
                                    }
                                }
                            }
                            discountedPriceSet {
                                presentmentMoney {
                                    amount
                                    currencyCode
                                }
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                            }
                            taxLines {
                                title
                                rate
                                ratePercentage
                                priceSet {
                                    presentmentMoney {
                                        amount
                                        currencyCode
                                    }
                                    shopMoney {
                                        amount
                                        currencyCode
                                    }
                                }
                            }
                        }
                        totalShippingPrice
                        totalShippingPriceSet {
                            presentmentMoney {
                                amount
                                currencyCode
                            }
                            shopMoney {
                                amount
                                currencyCode
                            }
                        }
                        taxLines {
                            title
                            rate
                            ratePercentage
                            priceSet {
                                presentmentMoney {
                                    amount
                                    currencyCode
                                }
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                            }
                        }
                        totalTax
                        totalTaxSet {
                            presentmentMoney {
                                amount
                                currencyCode
                            }
                            shopMoney {
                                amount
                                currencyCode
                            }
                        }
                        totalPrice
                        totalPriceSet {
                            presentmentMoney {
                                amount
                                currencyCode
                            }
                            shopMoney {
                                amount
                                currencyCode
                            }
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }',
            $graphqlQueryHelper->getDraftOrdersQueryByTag(null, null, null, 'ABC-123', ['withExtendedDraftOrderData' => true])
        );

        $gqlQuery = $this->getGraphQLQueryForDraftOrders([
            'wrapperFunctionName' => 'queryDraftOrdersByTag',
            'queryArguments' => [
                'sortKey' => 'UPDATED_AT',
                'reverse' => 'true',
            ],
            'searchFilter' => [
                // TODO avoid this workaround
                'customer_id' => '* (NOT status:COMPLETED AND NOT status:INVOICE_SENT AND NOT tag:StorisID:*)',
            ],
            'fields' => [
                'id',
                'name',
                'status',
                'invoiceUrl',
                'tags',
                'createdAt',
                'updatedAt',
                'purchasingEntity', // TODO 2024-12-20 need to add this field in the base middleware
                'customer', // TODO 2024-12-20 using this temporarily instead of 'purchasingEntity'
                'currencyCode',
                'presentmentCurrencyCode',
                'lineItems',
            ],
        ]);

        $this->assertSame([
            0 => 'query queryDraftOrdersByTag(
  $perPage: Int!,
  $endCursor: String
) {
  draftOrders(
    first: $perPage,
    after: $endCursor,
    query: "customer_id:* (NOT status:COMPLETED AND NOT status:INVOICE_SENT AND NOT tag:StorisID:*)",
    sortKey: UPDATED_AT,
    reverse: true
  ) {
    nodes {
      id
      __typename
      name
      status
      invoiceUrl
      tags
      createdAt
      updatedAt
      customer {
        id
        __typename
        firstName
        lastName
        displayName
        email
        phone
        lifetimeDuration
        locale
        multipassIdentifier
        note
        numberOfOrders
        productSubscriberStatus
        state
        tags
        taxExempt
        taxExemptions
        canDelete
        dataSaleOptOut
        validEmailAddress
        verifiedEmail
        createdAt
        updatedAt
        defaultAddress {
          id
          __typename
          name
          firstName
          lastName
          address1
          address2
          city
          province
          provinceCode
          zip
          country
          countryCodeV2
          phone
          company
          coordinatesValidated
          latitude
          longitude
          timeZone
          formattedArea
        }
        addresses {
          id
          __typename
          name
          firstName
          lastName
          address1
          address2
          city
          province
          provinceCode
          zip
          country
          countryCodeV2
          phone
          company
          coordinatesValidated
          latitude
          longitude
          timeZone
          formattedArea
        }
        amountSpent {
          amount
          currencyCode
        }
        emailMarketingConsent {
          consentUpdatedAt
          marketingOptInLevel
          marketingState
        }
        smsMarketingConsent {
          consentCollectedFrom
          consentUpdatedAt
          marketingOptInLevel
          marketingState
        }
      }
      currencyCode
      presentmentCurrencyCode
      lineItems (first:250) {
        nodes {
          id
          __typename
          uuid
          sku
          name
          title
          variantTitle
          vendor
          quantity
          custom
          isGiftCard
          requiresShipping
          taxable
          product {
            id
            __typename
            handle
            title
          }
          variant {
            id
            __typename
            sku
            barcode
            title
            displayName
            price
            compareAtPrice
          }
          customAttributes {
            key
            value
          }
          bundleComponents {
            id
            __typename
          }
          fulfillmentService {
            id
            __typename
            handle
          }
          weight {
            value
            unit
          }
          taxLines {
            title
            source
            channelLiable
            rate
            ratePercentage
            priceSet {
              presentmentMoney {
                amount
                currencyCode
              }
              shopMoney {
                amount
                currencyCode
              }
            }
          }
          appliedDiscount {
            title
            description
            value
            valueType
            amountSet {
              presentmentMoney {
                amount
                currencyCode
              }
              shopMoney {
                amount
                currencyCode
              }
            }
          }
          approximateDiscountedUnitPriceSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
          discountedTotalSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
          totalDiscountSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
          originalUnitPriceWithCurrency {
            amount
            currencyCode
          }
          originalUnitPriceSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
          originalTotalSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
        }
        pageInfo {
          startCursor
          endCursor
          hasPreviousPage
          hasNextPage
        }
      }
    }
    pageInfo {
      startCursor
      endCursor
      hasPreviousPage
      hasNextPage
    }
  }
}',
            1 => [
                'perPage' => 10,
                'endCursor' => null,
            ],
            2 => 'draftOrders',
        ], $gqlQuery);

        $gql = $gqlQuery[0];

        $this->assertEquals('query queryDraftOrdersByTag(
  $perPage: Int!,
  $endCursor: String
) {
  draftOrders(
    first: $perPage,
    after: $endCursor,
    query: "customer_id:* (NOT status:COMPLETED AND NOT status:INVOICE_SENT AND NOT tag:StorisID:*)",
    sortKey: UPDATED_AT,
    reverse: true
  ) {
    nodes {
      id
      __typename
      name
      status
      invoiceUrl
      tags
      createdAt
      updatedAt
      customer {
        id
        __typename
        firstName
        lastName
        displayName
        email
        phone
        lifetimeDuration
        locale
        multipassIdentifier
        note
        numberOfOrders
        productSubscriberStatus
        state
        tags
        taxExempt
        taxExemptions
        canDelete
        dataSaleOptOut
        validEmailAddress
        verifiedEmail
        createdAt
        updatedAt
        defaultAddress {
          id
          __typename
          name
          firstName
          lastName
          address1
          address2
          city
          province
          provinceCode
          zip
          country
          countryCodeV2
          phone
          company
          coordinatesValidated
          latitude
          longitude
          timeZone
          formattedArea
        }
        addresses {
          id
          __typename
          name
          firstName
          lastName
          address1
          address2
          city
          province
          provinceCode
          zip
          country
          countryCodeV2
          phone
          company
          coordinatesValidated
          latitude
          longitude
          timeZone
          formattedArea
        }
        amountSpent {
          amount
          currencyCode
        }
        emailMarketingConsent {
          consentUpdatedAt
          marketingOptInLevel
          marketingState
        }
        smsMarketingConsent {
          consentCollectedFrom
          consentUpdatedAt
          marketingOptInLevel
          marketingState
        }
      }
      currencyCode
      presentmentCurrencyCode
      lineItems (first:250) {
        nodes {
          id
          __typename
          uuid
          sku
          name
          title
          variantTitle
          vendor
          quantity
          custom
          isGiftCard
          requiresShipping
          taxable
          product {
            id
            __typename
            handle
            title
          }
          variant {
            id
            __typename
            sku
            barcode
            title
            displayName
            price
            compareAtPrice
          }
          customAttributes {
            key
            value
          }
          bundleComponents {
            id
            __typename
          }
          fulfillmentService {
            id
            __typename
            handle
          }
          weight {
            value
            unit
          }
          taxLines {
            title
            source
            channelLiable
            rate
            ratePercentage
            priceSet {
              presentmentMoney {
                amount
                currencyCode
              }
              shopMoney {
                amount
                currencyCode
              }
            }
          }
          appliedDiscount {
            title
            description
            value
            valueType
            amountSet {
              presentmentMoney {
                amount
                currencyCode
              }
              shopMoney {
                amount
                currencyCode
              }
            }
          }
          approximateDiscountedUnitPriceSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
          discountedTotalSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
          totalDiscountSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
          originalUnitPriceWithCurrency {
            amount
            currencyCode
          }
          originalUnitPriceSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
          originalTotalSet {
            presentmentMoney {
              amount
              currencyCode
            }
            shopMoney {
              amount
              currencyCode
            }
          }
        }
        pageInfo {
          startCursor
          endCursor
          hasPreviousPage
          hasNextPage
        }
      }
    }
    pageInfo {
      startCursor
      endCursor
      hasPreviousPage
      hasNextPage
    }
  }
}', $gql);

        // TODO 2024-12-20 verify the "queryDraftOrdersByTag" structure
    }

    public function testGetMetafieldStorefrontVisibilityCreateMutation()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'mutation metafieldStorefrontVisibilityCreate($input: MetafieldStorefrontVisibilityInput!) {
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
        }',
            $graphqlQueryHelper->getMetafieldStorefrontVisibilityCreateMutation()
        );

        $gql = $this->generateGraphQL('mutation', 'metafieldStorefrontVisibilityCreate', [
            'input' => [
                'type' => 'MetafieldStorefrontVisibilityInput!',
            ],
        ], [
            'metafieldStorefrontVisibility' => [
                'id',
                'key',
                'namespace',
                'ownerType',
            ],
            'userErrors' => [
                'field',
                'message',
            ],
        ], []);

        $this->assertEquals('mutation metafieldStorefrontVisibilityCreate(
  $input: MetafieldStorefrontVisibilityInput!
) {
  metafieldStorefrontVisibilityCreate(
    input: $input
  ) {
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
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->getMetafieldStorefrontVisibilityCreateMutation())
//        );
    }

    public function testCollectionAddProductsQuery()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'mutation collectionAddProducts($id: ID!, $productIds: [ID!]!) {
          collectionAddProducts(id: $id, productIds: $productIds) {
            collection {
              id
            }
            userErrors {
              field
              message
            }
          }
        }',
            $graphqlQueryHelper->collectionAddProductsQuery()
        );

        $gql = $this->generateGraphQL('mutation', 'collectionAddProducts', [
            'id' => [
                'type' => 'ID!',
            ],
            'productIds' => [
                'type' => '[ID!]!',
            ],
        ], [
            'collection' => [
                'id',
            ],
            'userErrors' => [
                'field',
                'message',
            ],
        ], []);

        $this->assertEquals('mutation collectionAddProducts(
  $id: ID!,
  $productIds: [ID!]!
) {
  collectionAddProducts(
    id: $id,
    productIds: $productIds
  ) {
    collection {
      id
    }
    userErrors {
      field
      message
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->collectionAddProductsQuery())
//        );
    }

    public function testCollectionQueryById()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'query collection($id: ID!, $perPage: Int!) {
          collection(id: $id) {
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
        }',
            $graphqlQueryHelper->collectionQueryById()
        );

        $this->assertEquals(
            'query collection($id: ID!, $perPage: Int!, $endCursor: String) {
          collection(id: $id) {
            products(first: $perPage, after: $endCursor) {
              nodes {
                id
              }
              pageInfo {
                hasNextPage
                endCursor
              }
            }
          }
        }',
            $graphqlQueryHelper->collectionQueryById('ABC-123')
        );

        $gql = $this->generateGraphQLForNestedFunction('query', 'collection', 'products', [
//            'id' => [
//                'type' => 'ID!',
//            ],
            'perPage' => [
                'type' => 'Int!',
                'variable' => 'first',
            ],
            'endCursor' => [
                'type' => 'String',
                'variable' => 'after',
                'optional' => true,
                //'directValue' => null,
            ],
        ], [
            'nodes' => [
                'id',
            ],
            'pageInfo' => [
                'hasNextPage',
                'endCursor',
            ],
        ], []);

        $this->assertEquals('query collection(
  $perPage: Int!,
  $endCursor: String,
  $id: ID!
) {
  collection(
    id: $id
  ) {
    id
    __typename
    products(
      first: $perPage,
      after: $endCursor
    ) {
      nodes {
        id
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->collectionQueryById('ABC-123'))
//        );
    }

    public function testCollectionRemoveProductsQuery()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals(
            'mutation collectionRemoveProducts($id: ID!, $productIds: [ID!]!) {
          collectionRemoveProducts(id: $id, productIds: $productIds) {
            job {
              done
              id
            }
            userErrors {
              field
              message
            }
          }
        }',
            $graphqlQueryHelper->collectionRemoveProductsQuery()
        );

        $gql = $this->generateGraphQL('mutation', 'collectionRemoveProducts', [
            'id' => [
                'type' => 'ID!',
            ],
            'productIds' => [
                'type' => '[ID!]!',
            ],
        ], [
            'job' => [
                'done',
                'id',
            ],
            'userErrors' => [
                'field',
                'message',
            ],
        ], []);

        $this->assertEquals('mutation collectionRemoveProducts(
  $id: ID!,
  $productIds: [ID!]!
) {
  collectionRemoveProducts(
    id: $id,
    productIds: $productIds
  ) {
    job {
      done
      id
    }
    userErrors {
      field
      message
    }
  }
}', $gql);

//        $this->assertEquals(
//            $this->formatGraphQLQueryString($gql),
//            $this->formatGraphQLQueryString($graphqlQueryHelper->collectionRemoveProductsQuery())
//        );
    }

    public function testProductCreateMutation()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals('mutation productCreate($input: ProductInput!) {
          productCreate(input: $input) {
            product {
              id
              title
              handle
              vendor
              productType
              variants(first: 250) {
                edges {
                  node {
                    id
                    title
                    sku
                    price
                  }
                }
              }
            }
            userErrors {
              field
              message
            }
          }
        }',
            $graphqlQueryHelper->getProductCreateMutation()
        );

        $gql = $this->generateGraphQL('mutation', 'productCreate', [
            'input' => [
                'type' => 'ProductInput!',
            ],
        ], [
            'product' => [
                'id',
                'title',
                'handle',
                'vendor',
                'productType',
                'variants(first: 250)' => [
                    'edges' => [
                        'node' => [
                            'id',
                            'title',
                            'sku',
                            'price',
                        ],
                    ],
                ],
            ],
            'userErrors' => [
                'field',
                'message',
            ],
        ], []);

        $this->assertEquals('mutation productCreate(
  $input: ProductInput!
) {
  productCreate(
    input: $input
  ) {
    product {
      id
      title
      handle
      vendor
      productType
      variants(first: 250) {
        edges {
          node {
            id
            title
            sku
            price
          }
        }
      }
    }
    userErrors {
      field
      message
    }
  }
}', $gql);
    }

    public function testProductUpdateMutation()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals('mutation productUpdate($input: ProductInput!) {
          productUpdate(input: $input) {
            product {
              id
              title
              handle
              vendor
              productType
              media(first: 250) {
                nodes {
                  id
                }
              }
              variants(first: 250) {
                edges {
                  node {
                    id
                    title
                    sku
                    price
                  }
                }
              }
            }
            userErrors {
              field
              message
            }
          }
        }',
            $graphqlQueryHelper->getProductUpdateMutation()
        );

        $gql = $this->generateGraphQL('mutation', 'productUpdate', [
            'input' => [
                'type' => 'ProductInput!',
            ],
        ], [
            'product' => [
                'id',
                'title',
                'handle',
                'vendor',
                'productType',
                'media(first: 250)' => [
                    'nodes' => [
                        'id',
                    ],
                ],
                'variants(first: 250)' => [
                    'edges' => [
                        'node' => [
                            'id',
                            'title',
                            'sku',
                            'price',
                        ],
                    ],
                ],
            ],
            'userErrors' => [
                'field',
                'message',
            ],
        ], []);

        $this->assertEquals('mutation productUpdate(
  $input: ProductInput!
) {
  productUpdate(
    input: $input
  ) {
    product {
      id
      title
      handle
      vendor
      productType
      media(first: 250) {
        nodes {
          id
        }
      }
      variants(first: 250) {
        edges {
          node {
            id
            title
            sku
            price
          }
        }
      }
    }
    userErrors {
      field
      message
    }
  }
}', $gql);
    }

    public function testProductCreateMediaMutation()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals('mutation productCreateMedia($productId: ID!, $media: [CreateMediaInput!]!) {
          productCreateMedia(productId: $productId, media: $media) {
            media {
              alt
              mediaContentType
              status
            }
            mediaUserErrors {
              field
              message
            }
          }
        }',
            $graphqlQueryHelper->getProductCreateMediaMutation()
        );

        $gql = $this->generateGraphQL('mutation', 'productCreateMedia', [
            'productId' => [
                'type' => 'ID!',
            ],
            'media' => [
                'type' => '[CreateMediaInput!]!',
            ],
        ], [
            'media' => [
                'alt',
                'mediaContentType',
                'status',
            ],
            'mediaUserErrors' => [
                'field',
                'message',
            ],
        ], []);

        $this->assertEquals('mutation productCreateMedia(
  $productId: ID!,
  $media: [CreateMediaInput!]!
) {
  productCreateMedia(
    productId: $productId,
    media: $media
  ) {
    media {
      alt
      mediaContentType
      status
    }
    mediaUserErrors {
      field
      message
    }
  }
}', $gql);
    }

    public function testProductDeleteMediaMutation()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals('mutation productDeleteMedia($mediaIds: [ID!]!, $productId: ID!) {
          productDeleteMedia(mediaIds: $mediaIds, productId: $productId) {
            userErrors {
              field
              message
            }
          }
        }',
            $graphqlQueryHelper->getProductDeleteMediaMutation()
        );

        $gql = $this->generateGraphQL('mutation', 'productDeleteMedia', [
            'mediaIds' => [
                'type' => '[ID!]!',
            ],
            'productId' => [
                'type' => 'ID!',
            ],
        ], [
            'userErrors' => [
                'field',
                'message',
            ],
        ], []);

        $this->assertEquals('mutation productDeleteMedia(
  $mediaIds: [ID!]!,
  $productId: ID!
) {
  productDeleteMedia(
    mediaIds: $mediaIds,
    productId: $productId
  ) {
    userErrors {
      field
      message
    }
  }
}', $gql);
    }

    public function testProductIdByHandleQuery()
    {
        $graphqlQueryHelper = new GraphQLQueryHelper();

        $this->assertEquals('query productByHandle($handle: String!) {
          productByHandle(handle: $handle) {
            id
          }
        }',
            $graphqlQueryHelper->getProductIdByHandleQuery()
        );

        $gql = $this->generateGraphQL('query', 'productByHandle', [
            'handle' => [
                'type' => 'String!',
            ],
        ], [
            'id',
        ], []);

        $this->assertEquals('query productByHandle(
  $handle: String!
) {
  productByHandle(
    handle: $handle
  ) {
    id
  }
}', $gql);
    }
}
