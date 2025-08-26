<?php

namespace App\Command;

use App\Connector\MultiscountClient;
use App\Helper\GraphQLQueryHelper;
use PHPShopify\ShopifySDK;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AddDiscountsToProductsCommand extends Command
{
    const PER_PAGE = 20;
    const DISCOUNT_EVENT_PER_PAGE = 50;
    const DISCOUNT_CLASS_PRODUCT = 'PRODUCT';
    const DISCOUNT_AUTOMATIC_APP = 'DiscountAutomaticApp';
    const DISCOUNT_AUTOMATIC_BASIC = 'DiscountAutomaticBasic';
    const DISCOUNT_AUTOMATIC_BXGY = 'DiscountAutomaticBxgy';
    const ELIGIBLE_FOR_DISCOUNT = 'eligible-for-discount';
    const COLLECTION_BODY = 'COLLECTION_BODY:';
    const COLLECTION_TITLE = 'COLLECTION_TITLE:';
    const QUALIFIED = 'QUALIFIED:';
    const UNQUALIFIED = 'UNQUALIFIED:';
    const PROMO_TITLE = 'PROMO_TITLE:';
    const PROMO_BODY = 'PROMO_BODY:';
    const COLLECTION_TYPE = 'collections';
    const PRODUCT_TYPE = 'products';
    const CUSTOMER_GETS = 'customerGets';
    const CUSTOMER_BUYS = 'customerBuys';
    const DEFAULT_ARRAY = [
        'nodes' => [],
        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
    ];

    /**
     * @var GraphQLQueryHelper
     */
    private $graphQLQueryHelper;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var ShopifySDK
     */
    private $shopifySDK;

    /**
     * @var MultiscountClient
     */
    private $multiscountClient;
    private $multiscountResults = [];
    private $multiscountProductVariantIds = [];

    public function __construct(
        GraphQLQueryHelper $graphQLQueryHelper,
        ShopifySDK $shopifySDK,
        MultiscountClient $multiscountClient,
        string $name = null
    ) {
        parent::__construct($name);

        $this->shopifySDK = $shopifySDK;
        $this->graphQLQueryHelper = $graphQLQueryHelper;
        $this->multiscountClient = $multiscountClient;
    }

    public function setDecoratedTaskLogger($decoratedTaskLogger = null)
    {
        // do nothing
        // @see \Endertech\EcommerceMiddlewareEventsBundle\DependencyInjection\Compiler\CommandPass::process()
    }

    protected function configure(): void
    {
        parent::configure();

        if (!$this->getName()) {
            $this->setName('middleware:discount:add-to-product');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $applicableDiscounts = [];

        $this->io->writeln(' - Start applying discounts to products');

        try {
            $applicableDiscounts = $this->getDiscounts($applicableDiscounts);
            $existingOwnerIds = array_column($applicableDiscounts, 'ownerId');
            $this->io->writeln(' - Start querying products in "Eligible for Discount" collection...');
            $eligibleForDiscountProducts = $this->getProductsByEligibleForDiscountCollection();
            foreach ($eligibleForDiscountProducts as $eligibleForDiscountProduct) {
                if (!in_array($eligibleForDiscountProduct['id'], $existingOwnerIds)) {
                    $applicableDiscounts['eligibleForDiscount'.$eligibleForDiscountProduct['id']] = [
                        'key' => 'eligible_for_discount',
                        'namespace' => 'mw_marketing',
                        'ownerId' => $eligibleForDiscountProduct['id'],
                        'type' => 'boolean',
                        'value' => 'false',
                    ];
                    $applicableDiscounts[$eligibleForDiscountProduct['id']] = [
                        'key' => 'applicable_discounts',
                        'namespace' => 'mw_marketing',
                        'ownerId' => $eligibleForDiscountProduct['id'],
                        'type' => 'json',
                        'value' => json_encode([]),
                    ];
                }
            }

            $applicableDiscounts = array_values($applicableDiscounts);

            if (!count($applicableDiscounts)) {
                $this->io->success('No products have been updated');

                return Command::SUCCESS;
            }

            if (true === $this->updateProductMetafieldWithDiscounts($applicableDiscounts)) {
                $this->io->success('Successfully apply discounts to '.(count($applicableDiscounts) / 2).' products');

                return Command::SUCCESS;
            }

            $this->io->error('Update Failed');

            return Command::FAILURE;
        } catch (\Exception $exception) {
            $this->io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }

    private function getProductsByEligibleForDiscountCollection($endCursor = null, $previousProducts = []): array
    {
        $productsQueryByByEligibleForDiscountCollection = $this->graphQLQueryHelper->getProductsQueryByCollection($endCursor);

        $response = $this->requestQuery($productsQueryByByEligibleForDiscountCollection, [
            'perPage' => self::PER_PAGE,
            'endCursor' => $endCursor,
            'collectionName' => self::ELIGIBLE_FOR_DISCOUNT,
        ]);

        $productsByCollection = $response['data']['collectionByHandle']['products'] ?? self::DEFAULT_ARRAY;

        $allProducts = array_merge($productsByCollection['nodes'], $previousProducts);

        if ($productsByCollection['pageInfo']['hasNextPage']) {
            return $this->getProductsByEligibleForDiscountCollection($productsByCollection['pageInfo']['endCursor'], $allProducts);
        }

        return $allProducts;
    }

    private function getAllAutomaticDiscount($automaticDiscounts, $discountId, $prevData = [], $endCursor = null): array
    {
        $typename = $automaticDiscounts['__typename'] ?? null;
        $customerDataType = self::DISCOUNT_AUTOMATIC_BASIC === $typename ? self::CUSTOMER_GETS : self::CUSTOMER_BUYS;
        $customerBuyItems = $automaticDiscounts[$customerDataType]['items'] ?? [];
        $dataType = isset($customerBuyItems[self::COLLECTION_TYPE]) ? self::COLLECTION_TYPE : self::PRODUCT_TYPE;

        if (!in_array($typename, [self::DISCOUNT_AUTOMATIC_APP, self::DISCOUNT_AUTOMATIC_BASIC, self::DISCOUNT_AUTOMATIC_BXGY])) {
            return $automaticDiscounts;
        }

        if (self::DISCOUNT_AUTOMATIC_APP === $typename) {
            if ('ACTIVE' !== $automaticDiscounts['status'] ?? null) {
                // since this app discount is not active, it must be setup so that it will send empty metafield data
                // once way to do that is to use an endsAt date in the past
                // @see \App\Command\AddDiscountsToProductsCommand::setDiscountsToProducts()
                $timeAgo = new \DateTime('-1 day', new \DateTimeZone('UTC'));
                if (isset($automaticDiscounts['endsAt'])
                    && is_string($automaticDiscounts['endsAt'])
                    && false !== strpos($automaticDiscounts['endsAt'], 'T')
                ) {
                    $tempTimeAgo = new \DateTime($automaticDiscounts['endsAt'], new \DateTimeZone('UTC'));
                    if ($tempTimeAgo < $timeAgo) {
                        $timeAgo = $tempTimeAgo;
                    }
                }
                $automaticDiscounts['endsAt'] = $timeAgo->format(\DateTimeInterface::RFC3339);
            }

            return $automaticDiscounts;
        }

        if (self::DISCOUNT_AUTOMATIC_BASIC === $typename) {
            $customerItemsQuery = $this->graphQLQueryHelper->getDiscountBasicQueryByDiscountId($dataType, $endCursor);
        } else {
            $customerItemsQuery = $this->graphQLQueryHelper->getDiscountBxgyQueryByDiscountId($dataType, $endCursor);
        }

        $response = $this->requestQuery($customerItemsQuery, [
            'perPage' => 250,
            'endCursor' => $endCursor,
            'discountId' => $discountId,
        ]);

        $currentDiscount = $response['data']['automaticDiscountNode']['automaticDiscount'][$customerDataType]['items'] ?? [];

        $currentData = $currentDiscount[$dataType] ?? self::DEFAULT_ARRAY;
        $allAutomaticData = array_merge($currentData['nodes'] ?? [], $prevData);

        if ($currentData['pageInfo']['hasNextPage']) {
            $automaticDiscounts = $this->getAllAutomaticDiscount(
                $automaticDiscounts,
                $discountId,
                $allAutomaticData,
                $currentData['pageInfo']['endCursor']
            );
        }

        $automaticDiscounts[$customerDataType]['items'][$dataType]['nodes'] = $allAutomaticData;

        return $automaticDiscounts;
    }

    private function getDiscounts($applicableDiscounts, $endCursor = null): array
    {
        $discountQuery = $this->graphQLQueryHelper->getAutomaticDiscountQuery($endCursor);
        $response = $this->requestQuery($discountQuery, [
            'perPage' => self::PER_PAGE,
            'endCursor' => $endCursor,
        ]);

        $discounts = $response['data']['automaticDiscountNodes']['nodes'] ?? $response['data']['discountNodes']['nodes'] ?? [];
        $discountPageInfo = $response['data']['automaticDiscountNodes']['pageInfo'] ?? $response['data']['discountNodes']['pageInfo'] ?? [];
        if (!count($discounts)) {
            return [];
        }

        foreach ($discounts as $discount) {
            $automaticDiscounts = $this->getAllAutomaticDiscount($discount['automaticDiscount'] ?? $discount['discount'] ?? [], $discount['id']);
            $discountEvents = $this->getDiscountEvents($discount['id']);
            $automaticDiscounts['events'] = $discountEvents;
            if (self::DISCOUNT_CLASS_PRODUCT !== $automaticDiscounts['discountClass'] ?? null) {
                continue;
            }

            $applicableDiscounts = $this->handleProductData(
                $discount['id'],
                $applicableDiscounts,
                $automaticDiscounts,
                $automaticDiscounts['__typename']
            );

            $collections = $this->setBuyOrGetData(
                $automaticDiscounts['__typename'],
                $automaticDiscounts,
                'collections'
            );

            if (count($collections['nodes'])) {
                foreach ($collections['nodes'] as $collection) {
                    $applicableDiscounts = $this->getProductsByCollectionId(
                        $discount['id'],
                        $collection,
                        $applicableDiscounts,
                        $automaticDiscounts
                    );
                }
            }
        }

        if (isset($discountPageInfo['hasNextPage']) && $discountPageInfo['hasNextPage']) {
            $applicableDiscounts = $this->getDiscounts(
                $applicableDiscounts,
                $discountPageInfo['endCursor']
            );
        }

        return $applicableDiscounts;
    }

    private function getDiscountEvents($discountId, $endCursor = null, $previousDiscountEvents = []): array
    {
        $commentEventQuery = $this->graphQLQueryHelper->getCommentEventQuery($endCursor);
        $response = $this->requestQuery($commentEventQuery, [
            'perPage' => self::DISCOUNT_EVENT_PER_PAGE,
            'discountId' => $discountId,
            'endCursor' => $endCursor,
        ]);

        $newDiscountEvents = $response['data']['automaticDiscountNode']['events'] ?? self::DEFAULT_ARRAY;

        $allDiscountEvents = array_merge($newDiscountEvents['nodes'], $previousDiscountEvents);

        if ($newDiscountEvents['pageInfo']['hasNextPage']) {
            return $this->getDiscountEvents(
                $discountId,
                $newDiscountEvents['pageInfo']['endCursor'],
                $allDiscountEvents
            );
        }

        return $allDiscountEvents;
    }

    private function updateProductMetafieldWithDiscounts($applicableDiscounts): bool
    {
        $data = [
            'metafields' => array_slice($applicableDiscounts, 0, self::PER_PAGE),
        ];
        $remainingData = array_slice($applicableDiscounts, self::PER_PAGE, count($applicableDiscounts));
        $metafieldsSetQuery = $this->graphQLQueryHelper->getMetafieldsSetQuery();
        $response = $this->requestQuery($metafieldsSetQuery, $data);
        $userErrors = $response['data']['metafieldsSet']['userErrors'] ?? [];

        if (count($userErrors)) {
            foreach ($userErrors as $error) {
                $this->io->error($error['message']);
            }

            return false;
        }

        $this->io->success(sprintf('Successfully updated %d products...', count($data['metafields']) / 2));

        if (count($remainingData)) {
            $this->updateProductMetafieldWithDiscounts($remainingData);
        }

        return true;
    }

    private function handleProductData($discountId, $applicableDiscounts, $automaticDiscounts, $typename): array
    {
        $products = $this->setBuyOrGetData($typename, $automaticDiscounts, 'products');

        if (count($products['nodes'])) {
            $applicableDiscounts = $this->setDiscountsToProducts(
                $products['nodes'],
                $applicableDiscounts,
                $automaticDiscounts,
                $discountId
            );
        }

        return $applicableDiscounts;
    }

    private function getProductsByCollectionId(
        $discountId,
        $collection,
        $applicableDiscounts,
        $automaticDiscounts,
        $endCursor = null
    ) {
        $query = $this->graphQLQueryHelper->getCollectionQueryByCollectionId($endCursor);
        $response = $this->requestQuery($query, [
            'perPage' => self::PER_PAGE,
            'collectionId' => $collection['id'],
            'endCursor' => $endCursor,
        ]);

        $products = $response['data']['collection']['products'] ?? self::DEFAULT_ARRAY;

        if (count($products['nodes'])) {
            $applicableDiscounts = $this->setDiscountsToProducts(
                $products['nodes'],
                $applicableDiscounts,
                $automaticDiscounts,
                $discountId
            );
        }

        if ($products['pageInfo']['hasNextPage']) {
            $applicableDiscounts = $this->getProductsByCollectionId(
                $discountId,
                $collection,
                $applicableDiscounts,
                $automaticDiscounts,
                $products['pageInfo']['endCursor']
            );
        }

        return $applicableDiscounts;
    }

    private function setDiscountsToProducts($nodes, $applicableDiscounts, $automaticDiscounts, $discountId): array
    {
        $startsAt = new \DateTime($automaticDiscounts['startsAt'], new \DateTimeZone('UTC'));
        $endsAt = new \DateTime($automaticDiscounts['endsAt'], new \DateTimeZone('UTC'));
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $startsAt = $startsAt->format('Y-m-d H:i');
        foreach ($nodes as $node) {
            $applicableDiscountKey = 0;
            if (!in_array($node['id'], array_keys($applicableDiscounts))) {
                $applicableDiscounts[$node['id']] = [
                    'key' => 'applicable_discounts',
                    'namespace' => 'mw_marketing',
                    'ownerId' => $node['id'],
                    'type' => 'json',
                ];
            } else {
                $applicableDiscountsValue = $applicableDiscounts[$node['id']]['value'];
                $applicableDiscounts[$node['id']]['value'] = json_decode($applicableDiscountsValue, true);
                $applicableDiscountKey = count($applicableDiscounts[$node['id']]['value']);
            }

            if ($automaticDiscounts['endsAt'] && $endsAt < $now) {
                if (0 === $applicableDiscountKey) {
                    $applicableDiscounts[$node['id']]['value'] = [];
                    $applicableDiscounts['eligibleForDiscount'.$node['id']] = [
                        'key' => 'eligible_for_discount',
                        'namespace' => 'mw_marketing',
                        'ownerId' => $node['id'],
                        'type' => 'boolean',
                        'value' => 'false',
                    ];
                }
            } else {
                $promoContents = [
                    'collectionTitle' => '',
                    'collectionBody' => '',
                    'qualified' => '',
                    'unQualified' => '',
                    'promoTitle' => '',
                    'promoBody' => '',
                ];

                foreach ($automaticDiscounts['events'] as $event) {
                    $promoContents = $this->setPromoContents(
                        $event,
                        $promoContents,
                        'collectionBody',
                        self::COLLECTION_BODY
                    );
                    $promoContents = $this->setPromoContents(
                        $event,
                        $promoContents,
                        'collectionTitle',
                        self::COLLECTION_TITLE
                    );
                    $promoContents = $this->setPromoContents($event, $promoContents, 'qualified', self::QUALIFIED);
                    $promoContents = $this->setPromoContents($event, $promoContents, 'unQualified', self::UNQUALIFIED);
                    $promoContents = $this->setPromoContents($event, $promoContents, 'promoTitle', self::PROMO_TITLE);
                    $promoContents = $this->setPromoContents($event, $promoContents, 'promoBody', self::PROMO_BODY);
                }

                $applicableDiscounts[$node['id']]['value'][$applicableDiscountKey] = [
                    'discountTitle' => $automaticDiscounts['title'],
                    'discountId' => $discountId,
                    'collectionTitle' => $promoContents['collectionTitle'],
                    'collectionBody' => $promoContents['collectionBody'],
                    'qualified' => $promoContents['qualified'],
                    'unQualified' => $promoContents['unQualified'],
                    'promoTitle' => $promoContents['promoTitle'],
                    'promoBody' => $promoContents['promoBody'],
                    'combinesWithProductDiscounts' => $automaticDiscounts['combinesWith']['productDiscounts'] ?? false,
                    'startsAt' => $automaticDiscounts['startsAt'] ? $startsAt : null,
                    'endsAt' => $automaticDiscounts['endsAt'] ? $endsAt->format('Y-m-d H:i') : null,
                    'timezone' => 'UTC',
                    'discountType' => $automaticDiscounts['__typename'],
                    'minimumRequirement' => $automaticDiscounts['minimumRequirement'] ?? null,
                    'discountValue' => $automaticDiscounts['customerGets']['value'] ?? null,
                    'customerBuysValue' => $automaticDiscounts['customerBuys']['value'] ?? null,
                    'customerGetsItems' => array_merge(
                        $automaticDiscounts['customerGets']['items']['collections']['nodes'] ?? [],
                        $automaticDiscounts['customerGets']['items']['products']['nodes'] ?? []
                    ) ?: null,
                ];

                $applicableDiscounts['eligibleForDiscount'.$node['id']] = [
                    'key' => 'eligible_for_discount',
                    'namespace' => 'mw_marketing',
                    'ownerId' => $node['id'],
                    'type' => 'boolean',
                    'value' => 'true',
                ];
            }

            $applicableDiscounts[$node['id']]['value'] = json_encode($applicableDiscounts[$node['id']]['value']);
        }

        return $applicableDiscounts;
    }

    private function setPromoContents($event, $promoContents, $contentKey, $startWith): array
    {
        if (0 === strpos($event['message'], $startWith)) {
            $promoContents[$contentKey] = trim(str_replace($startWith, '', $event['message']));
        }

        return $promoContents;
    }

    private function requestQuery($query, $variables)
    {
        $url = $this->shopifySDK->GraphQL()->generateUrl();
        if (is_string($url)) {
            // the DiscountAutomaticBxgy discountClass field exists in at least 2022-07
            $url = str_replace('/2022-04/', '/2022-07/', $url);
        }

        return $this->shopifySDK->GraphQL()->post($query, $url, false, $variables);
    }

    private function setBuyOrGetData($typename, $automaticDiscounts, $dataType): array
    {
        if (self::DISCOUNT_AUTOMATIC_APP === $typename) {
            $title = $automaticDiscounts['title'] ?? '';
            if (!isset($this->multiscountResults[$title])) {
                $this->multiscountResults[$title] = $this->multiscountClient->getProductsAndCollectionsFromDiscounts($title);
            }
            $data = self::DEFAULT_ARRAY;
            foreach ($this->multiscountResults[$title][$dataType] ?? [] as $itemId) {
                $node = ['id' => $itemId];
                if ($itemId && false !== stripos(''.$itemId, 'Variant')) {
                    if (!isset($this->multiscountProductVariantIds[$itemId])) {
                        try {
                            $productVariantQuery = $this->graphQLQueryHelper->getProductVariantQueryByIds();
                            $productVariantData = $this->requestQuery($productVariantQuery, [
                                'ids' => $this->multiscountResults[$title][$dataType] ?? [$itemId],
                            ]);
                            foreach ($productVariantData['data']['nodes'] ?? [] as $productVariantNode) {
                                if (isset($productVariantNode['id'])
                                    && isset($productVariantNode['product'])
                                    && isset($productVariantNode['product']['id'])
                                ) {
                                    $this->multiscountProductVariantIds[$productVariantNode['id']] = $productVariantNode['product']['id'];
                                }
                                if (isset($productVariantNode['id'])
                                    && isset($productVariantNode['variants'])
                                    && isset($productVariantNode['variants']['nodes'])
                                ) {
                                    foreach ($productVariantNode['variants']['nodes'] ?? [] as $variantNode) {
                                        if (isset($variantNode['id'])) {
                                            $this->multiscountProductVariantIds[$variantNode['id']] = $productVariantNode['id'];
                                        }
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            // do nothing
                        }
                    }
                    if (isset($this->multiscountProductVariantIds[$itemId])) {
                        // instead of the ProductVariant ID, use the Product ID
                        $node['id'] = $this->multiscountProductVariantIds[$itemId] ?? $itemId;
                    }
                }
                if (self::COLLECTION_TYPE === $dataType) {
                    $node['title'] = '';
                }
                if (in_array($node, $data['nodes'])) {
                    // prevent duplicate Product IDs from being included
                    continue;
                }
                $data['nodes'][] = $node;
            }

            return $data;
        }

        if (self::DISCOUNT_AUTOMATIC_BASIC === $typename) {
            return $automaticDiscounts['customerGets']['items'][$dataType] ?? self::DEFAULT_ARRAY;
        }

        return $automaticDiscounts['customerBuys']['items'][$dataType] ?? self::DEFAULT_ARRAY;
    }
}
