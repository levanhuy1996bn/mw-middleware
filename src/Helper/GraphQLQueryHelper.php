<?php

namespace App\Helper;

class GraphQLQueryHelper
{
    public function getProductsQueryByMattressesCollection($endCursor = null): string
    {
        $afterCursor = $endCursor ? ', after: $endCursor' : '';
        $declareEndCursor = $endCursor ? ', $endCursor: String' : '';

        return sprintf('query productsByCollection($collectionName: String!, $perPage: Int!%s) {
            collectionByHandle(handle: $collectionName) {
                products(first: $perPage%s) {
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
        }', $declareEndCursor, $afterCursor);
    }

    public function getProductQuery($endCursor = null): string
    {
        $afterCursor = $endCursor ? ', after: $endCursor' : '';
        $declareEndCursor = $endCursor ? ', $endCursor: String' : '';

        return sprintf('query getProducts($perPage: Int!%s) {
            products(first: $perPage%s) {
                nodes {
                     id
                     handle
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
            }
        }', $declareEndCursor, $afterCursor);
    }

    public function getProductsQueryInMattressesCollection($endCursor = null): string
    {
        $afterCursor = $endCursor ? ', after: $endCursor' : '';
        $declareEndCursor = $endCursor ? ', $endCursor: String' : '';

        return sprintf('query productsInMattressesCollection($handle: String!, $perPage: Int!%s) {
            collectionByHandle(handle: $handle) {
                products(first: $perPage%s) {
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
        }', $declareEndCursor, $afterCursor);
    }

    public function getProductVariantsByMattressProductId($endCursor = null): string
    {
        $afterCursor = $endCursor ? ', after: $endCursor' : '';
        $declareEndCursor = $endCursor ? ', $endCursor: String' : '';

        return sprintf('query productVariantsByProductId($productId: ID!, $perPage: Int!%s) {
            product(id: $productId) {
                %s
            }
        }', $declareEndCursor, $this->mattressProductVariantFields($afterCursor));
    }

    public function getProductsQueryByCollectionHandle($endCursor = null): string
    {
        $afterCursor = $endCursor ? ', after: $endCursor' : '';
        $declareEndCursor = $endCursor ? ', $endCursor: String' : '';

        return sprintf('query getProductsInPillowsCollection($collectionName: String!, $perPage: Int!%s) {
            collection: collectionByHandle(handle: $collectionName) {
                products(first: $perPage%s) {
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
        }', $declareEndCursor, $afterCursor);
    }

    public function getMattressAccessoriesVariantsQueryByProductId($endCursor = null): string
    {
        $afterCursor = $endCursor ? ', after: $endCursor' : '';
        $declareEndCursor = $endCursor ? ', $endCursor: String' : '';

        return sprintf('query mattressAccessoriesVariantsQueryByProductId($productId: ID!, $perPage: Int!%s) {
            product(id: $productId) {
                handle
                variants(first: $perPage%s) {
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
        }', $declareEndCursor, $afterCursor);
    }

    public function getBedMatchProductVariantsQueryByProductIds(): string
    {
        return 'query bedMatchProductVariantsQueryByProductIds($ids: [ID!]!) {
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
        }';
    }

    public function getProductsQueryByCollection($endCursor = null): string
    {
        $afterCursor = $endCursor ? ', after: $endCursor' : '';
        $declareEndCursor = $endCursor ? ', $endCursor: String' : '';

        return sprintf('query productsByCollection($collectionName: String!, $perPage: Int!%s) {
            collectionByHandle(handle: $collectionName) {
                products(first: $perPage%s) {
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
        }', $declareEndCursor, $afterCursor);
    }

    public function getProductVariantsQueryByProductHandle($endCursor = null): string
    {
        $afterCursor = $endCursor ? ', after: $endCursor' : '';
        $declareEndCursor = $endCursor ? ', $endCursor: String' : '';

        return sprintf('query productVariantsByProductHandle($productHandle: String!, $perPage: Int!%s) {
            product: productByHandle(handle: $productHandle) {
                %s
            }
        }', $declareEndCursor, $this->productVariantFields($afterCursor));
    }

    public function getCommentEventQuery($endCursor = null): string
    {
        $afterCursor = $endCursor ? ', after: $endCursor' : '';
        $declareEndCursor = $endCursor ? ', $endCursor: String' : '';

        return sprintf('query commentEventQuery($perPage: Int!, $discountId: ID!%s) {
            automaticDiscountNode (id: $discountId) {
                events (first: $perPage%s) {
                  nodes {
                    message
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
                }
            }
        }', $declareEndCursor, $afterCursor);
    }

    public function getAutomaticDiscountQuery($endCursor = null): string
    {
        $afterCursor = $endCursor ? ', after: $endCursor' : '';
        $declareEndCursor = $endCursor ? ', $endCursor: String' : '';
        $automaticDiscountFields = $this->getAutomaticDiscountFields();

        /*
        return sprintf(
            'query automaticDiscountQuery($perPage: Int!%s) {
            automaticDiscountNodes (first: $perPage, query: "status:active OR status:scheduled" %s) {
              nodes {
                id
                automaticDiscount {
                  __typename
                  ... on DiscountAutomaticBxgy {
                    %s
                    %s
                  }
                  ... on DiscountAutomaticBasic {
                    %s
                    %s
                  }
                  ... on DiscountAutomaticApp {
                    %s
                    %s
                  }
                }
              }
              pageInfo {
                 hasNextPage
                 endCursor
              }
            }
          }',
            $declareEndCursor,
            $afterCursor,
            $automaticDiscountFields,
            $this->discountAutomaticBxgyFields(),
            $automaticDiscountFields,
            $this->discountAutomaticBasicFields(),
            $automaticDiscountFields,
            $this->discountAutomaticAppFields()
        );
        */
        // MWS-642: Add additional queries for 3rd Party App Discounts when checking for discounts to badge products
        // 2024-10-28 It seems that the "automaticDiscountNodes()" GraphQL query only returns the DiscountAutomaticBxgy
        // and DiscountAutomaticBasic types, but not the DiscountAutomaticApp type.  So, the "discountNodes()" GraphQL
        // query is being used to retrieve the automatic discounts.
        return sprintf(
            'query automaticDiscountQuery($perPage: Int!%s) {
            discountNodes (first: $perPage, query: "method:automatic AND (status:active OR status:scheduled)" %s) {
              nodes {
                id
                discount {
                  __typename
                  ... on DiscountAutomaticBxgy {
                    %s
                    %s
                  }
                  ... on DiscountAutomaticBasic {
                    %s
                    %s
                  }
                  ... on DiscountAutomaticApp {
                    %s
                    %s
                  }
                }
              }
              pageInfo {
                 hasNextPage
                 endCursor
              }
            }
          }',
            $declareEndCursor,
            $afterCursor,
            $automaticDiscountFields,
            $this->discountAutomaticBxgyFields(),
            $automaticDiscountFields,
            $this->discountAutomaticBasicFields(),
            $automaticDiscountFields,
            $this->discountAutomaticAppFields()
        );
    }

    public function getAutomaticDiscountBasicQueryByDiscountId($endCursor, $isGetProduct = true): string
    {
        $fields = $isGetProduct ? $this->discountProductFields($endCursor) : $this->discountCollectionFields($endCursor);

        return sprintf('query automaticDiscountBasicQueryByDiscountId($perPage: Int!, $discountId: ID!, $endCursor: String) {
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
                          %s
                       }
                       %s
                    }
                    %s
                 }
               }
            }
        }', $fields, $this->customerGetsValueFields(), $this->minimumRequirementFields());
    }

    public function getDiscountBasicQueryByDiscountId($dataType, $endCursor): string
    {
        $fields = 'products' === $dataType ? $this->discountProductFields($endCursor) : $this->discountCollectionFields($endCursor);
        $declareEndCursor = $endCursor ? ', $endCursor: String' : '';

        return sprintf('query discountBasicQueryByDiscountId($perPage: Int!, $discountId: ID!%s) {
            automaticDiscountNode (id: $discountId) {
               automaticDiscount {
                 ... on DiscountAutomaticBasic {
                    customerGets {
                        items {
                            %s
                        }
                    }
                 }
               }
            }
        }', $declareEndCursor, $fields);
    }

    public function getDiscountBxgyQueryByDiscountId($dataType, $endCursor): string
    {
        $fields = 'products' === $dataType ? $this->discountProductFields($endCursor) : $this->discountCollectionFields($endCursor);
        $declareEndCursor = $endCursor ? ', $endCursor: String' : '';

        return sprintf('query discountBxgyQueryByDiscountId($perPage: Int!, $discountId: ID!%s) {
            automaticDiscountNode (id: $discountId) {
               automaticDiscount {
                 ... on DiscountAutomaticBxgy {
                    customerBuys {
                        items {
                            %s
                        }
                    }
                 }
               }
            }
        }', $declareEndCursor, $fields);
    }

    public function getAutomaticDiscountBxgyQueryByDiscountId($endCursor, $isGetProduct = true): string
    {
        $fields = $isGetProduct ? $this->discountProductFields($endCursor) : $this->discountCollectionFields($endCursor);

        return sprintf('query automaticDiscountBxgyQueryByDiscountId($perPage: Int!, $discountId: ID!, $endCursor: String) {
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
                       %s
                    }
                    customerBuys {
                       items {
                           %s
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
        }', $this->customerGetsValueFields(), $fields);
    }

    public function getProductVariantQueryBySKUs($records): string
    {
        $skus = [];
        foreach ($records as $record) {
            $skus[] = 'sku:'.trim($record['sku']);
        }

        return sprintf('query productVariantsById {
            productVariants(first: 100, query: "%s") {
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
        }', implode(' OR ', $skus));
    }

    public function getProductVariantQueryByIds(): string
    {
        return 'query productVariantsByIds($ids: [ID!]!) {
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
        }';
    }

    public function getProductQueryByIds($locationId, $options = null): string
    {
        if (!$locationId && isset($options['allowEmptyLocationId']) && $options['allowEmptyLocationId']) {
            return 'query productsByIds($ids: [ID!]!) {
              nodes(ids: $ids) {
                ... on Product {
                  id
                }
              }
            }';
        }

        return sprintf('query productsByIds($ids: [ID!]!) {
                nodes(ids: $ids) {
                  ... on Product {
                    id
                    variants(first: 250) {
                      nodes {
                        metafield_available_to_try: metafield(namespace: "available_to_try", key: "%s") {
                          value
                        }
                      }
                    }
                  }
                }
              }', $locationId);
    }

    public function getCollectionQueryByCollectionId($endCursor = null): string
    {
        $afterCursor = $endCursor ? ', after: $endCursor' : '';
        $declareEndCursor = $endCursor ? ' $endCursor: String' : '';

        return sprintf('query collectionQueryByCollectionId($collectionId: ID!, $perPage: Int!%s) {
            collection(id: $collectionId) {
               products(first: $perPage%s) {
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
        ', $declareEndCursor, $afterCursor);
    }

    public function getMetafieldsSetQuery(): string
    {
        return sprintf('
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
        ');
    }

    public function getProductVariantUpdateQuery(): string
    {
        return sprintf('
            mutation productVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
              productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                userErrors {
                  field
                  message
                }
              }
            }
        ');
    }

    public function getDraftOrdersQueryByTag($location = null, $currentDraftOrderId = null, $afterDate = null, $endCursor = null, $options = null): string
    {
        $afterCursor = $endCursor ? ', after: $endCursor' : '';
        $declareEndCursor = $endCursor ? ', $endCursor: String' : '';

        $excludeCompleted = isset($options['excludeCompleted']) && !$options['excludeCompleted'] ? false : true;
        $hasCustomer = isset($options['hasCustomer']) && !$options['hasCustomer'] ? false : true;
        $forDraftOrderId = isset($options['forDraftOrderId']) && $options['forDraftOrderId'] ? preg_replace('`[^\d]+`', '', ''.$options['forDraftOrderId']) : null;
        $allowStatusInvoiceSent = isset($options['allowStatusInvoiceSent']) && $options['allowStatusInvoiceSent'] ? true : false;
        $withoutTagPrefix = isset($options['withoutTagPrefix']) && $options['withoutTagPrefix'] ? ''.$options['withoutTagPrefix'] : 'StorisID:';
        $withExtendedDraftOrderData = isset($options['withExtendedDraftOrderData']) && $options['withExtendedDraftOrderData'] ? true : false;

        $query = $location ? 'tag:'.$location : '';
        if ($hasCustomer) {
            $query = $query ? $query.' customer_id:*' : 'customer_id:*';
        }
        if ($currentDraftOrderId) {
            $query = $query ? $query.' NOT id:'.$currentDraftOrderId : 'NOT id:'.$currentDraftOrderId;
        }
        if ($afterDate) {
            if ($afterDate instanceof \DateTimeInterface) {
                $afterDate = $afterDate->format(\DateTimeInterface::RFC3339);
            }
            $query = $query ? $query.' created_at:>='.$afterDate : 'created_at:>='.$afterDate;
        }
        if ($excludeCompleted) {
            if ($allowStatusInvoiceSent) {
                $query = $query ? $query.' (NOT status:COMPLETED AND NOT tag:'.$withoutTagPrefix.'*)' : 'NOT status:COMPLETED AND NOT tag:'.$withoutTagPrefix.'*';
            } else {
                $query = $query ? $query.' (NOT status:COMPLETED AND NOT status:INVOICE_SENT AND NOT tag:'.$withoutTagPrefix.'*)' : 'NOT status:COMPLETED AND NOT status:INVOICE_SENT AND NOT tag:'.$withoutTagPrefix.'*';
            }
        }
        if ($forDraftOrderId) {
            $query = ''; // if this forDraftOrderId option is defined, it should be the only query parameter
            $query = 'id:'.$forDraftOrderId;
        }
        $query = $query ? 'query: "'.$query.'"' : '';

        if ($withExtendedDraftOrderData) {
            return sprintf('query queryDraftOrdersByTag($perPage: Int!%s) {
                draftOrders(first: $perPage, sortKey:UPDATED_AT, reverse:true%s%s) {
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
            }', $declareEndCursor, $afterCursor, $query ? ', '.$query : '');
        }

        return sprintf('query queryDraftOrdersByTag($perPage: Int!%s) {
            draftOrders(first: $perPage, sortKey:UPDATED_AT, reverse:true%s%s) {
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
        }', $declareEndCursor, $afterCursor, $query ? ', '.$query : '');
    }

    public function getMetafieldStorefrontVisibilityCreateMutation(): string
    {
        return sprintf('mutation metafieldStorefrontVisibilityCreate($input: MetafieldStorefrontVisibilityInput!) {
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
        }');
    }

    public function collectionAddProductsQuery(): string
    {
        return sprintf('mutation collectionAddProducts($id: ID!, $productIds: [ID!]!) {
          collectionAddProducts(id: $id, productIds: $productIds) {
            collection {
              id
            }
            userErrors {
              field
              message
            }
          }
        }');
    }

    public function collectionQueryById($endCursor = null): string
    {
        $afterCursor = $endCursor ? ', after: $endCursor' : '';
        $declareEndCursor = $endCursor ? ', $endCursor: String' : '';

        return sprintf('query collection($id: ID!, $perPage: Int!%s) {
          collection(id: $id) {
            products(first: $perPage%s) {
              nodes {
                id
              }
              pageInfo {
                hasNextPage
                endCursor
              }
            }
          }
        }', $declareEndCursor, $afterCursor);
    }

    public function collectionRemoveProductsQuery(): string
    {
        return sprintf('mutation collectionRemoveProducts($id: ID!, $productIds: [ID!]!) {
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
        }');
    }

    private function mattressProductVariantFields($afterCursor): string
    {
        return sprintf('
            variants(first: $perPage%s) {
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
        ', $afterCursor);
    }

    private function discountAutomaticBxgyFields(): string
    {
        // The related query using this function seems to trigger an error with the default $perPage value:
        // message - Query cost is 1082, which exceeds the single query max cost limit (1000).
        // ... so it is hard-coded to a smaller value (10) for now
        $customerGetsItemsCollectionFields = str_replace('$perPage', '10', $this->discountCollectionFields());
        $customerGetsItemsProductFields = str_replace('$perPage', '10', $this->discountProductFields());

        return sprintf('
            combinesWith {
               productDiscounts
            }
            customerGets {
               items {
                   %s
                   %s
               }
               %s
            }
            customerBuys {
               items {
                   %s
                   %s
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
        ', $customerGetsItemsCollectionFields, $customerGetsItemsProductFields, $this->customerGetsValueFields(), $this->discountCollectionFields(), $this->discountProductFields());
    }

    private function minimumRequirementFields(): string
    {
        return sprintf('
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
        ');
    }

    private function getAutomaticDiscountFields(): string
    {
        return sprintf(
            'startsAt
                endsAt
                title
                discountClass
                '
        );
    }

    private function discountAutomaticBasicFields(): string
    {
        return sprintf(
            '
            customerGets {
                items {
                   %s
                   %s
                }
                %s
            }
            %s
            ',
            $this->discountCollectionFields(),
            $this->discountProductFields(),
            $this->customerGetsValueFields(),
            $this->minimumRequirementFields()
        );
    }

    private function discountAutomaticAppFields(): string
    {
        return sprintf(
            '
            status
            combinesWith {
               productDiscounts
            }
            '
        );
    }

    private function customerGetsValueFields(): string
    {
        return sprintf('
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
        ');
    }

    private function discountCollectionFields($endCursor = null): string
    {
        $after = $endCursor ? ', after: $endCursor' : '';

        return sprintf('
            ... on DiscountCollections {
               collections (first: $perPage %s) {
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
        ', $after);
    }

    private function discountProductFields($endCursor = null): string
    {
        $after = $endCursor ? ', after: $endCursor' : '';

        return sprintf('
            ... on DiscountProducts {
               products (first: $perPage %s) {
                  nodes {
                     id
                  }
                  pageInfo {
                     hasNextPage
                     endCursor
                  }
               }
            }
        ', $after);
    }

    private function productVariantFields($afterCursor): string
    {
        return sprintf('
            productType
            variants(first: $perPage%s) {
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
        ', $afterCursor);
    }

    public function getProductCreateMutation(): string {
        return sprintf('
        mutation ProductCreate($input: ProductInput!) {
            productCreate(input: $input) {
                product {
                    id
                    title
                    handle
                    vendor
                    productType
                    variants(first: 10) {
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
        }
    ');
    }

    public function getProductUpdateMutation(): string {
        return sprintf('
        mutation ProductUpdate($input: ProductInput!) {
            productUpdate(input: $input) {
                product {
                    id
                    title
                    handle
                    vendor
                    productType
                    variants(first: 10) {
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
        }
    ');
    }
}
