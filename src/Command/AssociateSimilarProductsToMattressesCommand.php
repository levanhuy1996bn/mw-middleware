<?php

namespace App\Command;

use App\Helper\GraphQLQueryHelper;
use PHPShopify\ShopifySDK;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AssociateSimilarProductsToMattressesCommand extends Command
{
    const PER_PAGE = 20;
    const MATTRESSES = 'mattresses';
    const FLOOR_MODEL = 'Floor Model';
    const LIMIT_VARIANT = 5;
    const SIZE = 'size';
    const DEFAULT_ARRAY = [
        'nodes' => [],
        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
    ];
    const RECOMMENDED_BRANDS = [
        'tempur-pedic',
        'kingsdown',
        'sealy',
        'serta',
        'beautyrest',
        'stearns & foster',
        'cheswick manor',
        'purple',
        'nectar',
        'casper',
        'aireloom',
        'spink & co',
        'smartlife',
        'comfort essentials',
        'bedgear',
        'simmons',
        'mattress 2.0',
        'somosbeds',
        'king koil',
        'dreamcloud',
        'ghostbed',
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

    public function __construct(
        GraphQLQueryHelper $graphQLQueryHelper,
        ShopifySDK $shopifySDK,
        string $name = null
    ) {
        parent::__construct($name);

        $this->shopifySDK = $shopifySDK;
        $this->graphQLQueryHelper = $graphQLQueryHelper;
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
            $this->setName('middleware:associate-similar-product-to-mattresses');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        try {
            $mattressVariants = [];
            $similarProducts = [];
            $this->io->writeln(' - Start querying products in "Mattresses" collection...');
            $mattressProducts = $this->getProductsInMattressesCollection();
            if (!count($mattressProducts)) {
                $this->io->success('No found products in "Mattresses" collections');

                return Command::SUCCESS;
            }

            foreach ($mattressProducts as $mattressProduct) {
                if ('ACTIVE' != $mattressProduct['status']) {
                    continue;
                }
                $this->io->writeln(sprintf(
                    ' - Start querying product variants by productID #%s...',
                    $mattressProduct['id']
                ));

                $mattressVariants = array_merge(
                    $mattressVariants,
                    $this->getProductVariantsByProductId($mattressProduct)
                );
            }

            if (!count($mattressVariants)) {
                $this->io->success('No found product variants in "Mattresses" collections');

                return Command::SUCCESS;
            }

            $recommendedBrands = self::RECOMMENDED_BRANDS;

            usort($mattressVariants, function ($prev, $nextData) use ($recommendedBrands) {
                return $this->customSortByRecommendedBrands($prev, $nextData, $recommendedBrands);
            });

            foreach ($mattressVariants as $mattressVariant) {
                if (!isset($mattressVariant['product']['featuredImage']['id'])) {
                    continue;
                }
                $variantSize = $this->getVariantSize($mattressVariant['selectedOptions']);
                $comfortLevel = $mattressVariant['product']['comfortLevel']['value'] ?? null;
                $vendor = $mattressVariant['product']['vendor'];
                $mattressType = $mattressVariant['product']['mattressType']['value'] ?? null;
                $matchingVariants = [];
                $minVendorPrices = [];

                array_filter(
                    $mattressVariants,
                    function ($anotherVariant) use (
                        &$matchingVariants,
                        &$minVendorPrices,
                        $variantSize,
                        $comfortLevel,
                        $mattressType,
                        $vendor,
                        $mattressVariant
                    ) {
                        if (self::LIMIT_VARIANT == count($matchingVariants) || self::FLOOR_MODEL === $anotherVariant['product']['productType']) {
                            return false;
                        }

                        $anotherVariantSize = $this->getVariantSize($anotherVariant['selectedOptions']);
                        $anotherComfortLevel = $anotherVariant['product']['comfortLevel']['value'] ?? null;
                        $anotherMattressType = $anotherVariant['product']['mattressType']['value'] ?? null;
                        $anotherVendor = $anotherVariant['product']['vendor'];

                        if ($variantSize === $anotherVariantSize
                            && $comfortLevel === $anotherComfortLevel
                            && $mattressType === $anotherMattressType
                            && $vendor !== $anotherVendor
                            && isset($mattressVariant['product']['featuredImage']['id'])
                        ) {
                            $this->setMatchingVariantsByPriceRangeAndClosestMSRP($matchingVariants, $anotherVendor, $anotherVariant, $minVendorPrices, $mattressVariant);
                        }

                        return true;
                    }
                );

                $similarProducts[] = [
                    'namespace' => 'mw_marketing',
                    'key' => 'similar_products',
                    'type' => 'list.variant_reference',
                    'ownerId' => $mattressVariant['id'],
                    'value' => json_encode(array_values($matchingVariants)),
                ];
            }

            if (true === $this->updateSimilarProductsMetafieldsToMattresses($similarProducts)) {
                $this->io->success('Successfully updated '.count($similarProducts).' product variants');

                return Command::SUCCESS;
            }
        } catch (\Exception $exception) {
            $this->io->error($exception->getMessage());

            return Command::FAILURE;
        }

        return Command::FAILURE;
    }

    private function updateSimilarProductsMetafieldsToMattresses($similarProducts): bool
    {
        $data['metafields'] = array_slice($similarProducts, 0, self::PER_PAGE);
        $remainingData = array_slice($similarProducts, self::PER_PAGE, count($similarProducts));
        $response = $this->requestQuery($this->graphQLQueryHelper->getMetafieldsSetQuery(), $data);
        $userErrors = $response['data']['metafieldsSet']['userErrors'] ?? [];

        if (count($userErrors)) {
            foreach ($userErrors as $error) {
                $this->io->error($error['message']);
            }

            return false;
        }

        $this->io->success(sprintf('Successfully updated %d product variants...', count($data['metafields'])));

        if (count($remainingData)) {
            $this->updateSimilarProductsMetafieldsToMattresses($remainingData);
        }

        return true;
    }

    private function getProductsInMattressesCollection($endCursor = null, $previousProducts = []): array
    {
        $productsQueryByCollection = $this->graphQLQueryHelper->getProductsQueryInMattressesCollection($endCursor);

        $response = $this->requestQuery($productsQueryByCollection, [
            'perPage' => self::PER_PAGE,
            'endCursor' => $endCursor,
            'handle' => self::MATTRESSES,
        ]);

        $productsByCollection = $response['data']['collectionByHandle']['products'] ?? self::DEFAULT_ARRAY;

        $allProducts = array_merge($productsByCollection['nodes'], $previousProducts);

        if ($productsByCollection['pageInfo']['hasNextPage']) {
            return $this->getProductsInMattressesCollection(
                $productsByCollection['pageInfo']['endCursor'],
                $allProducts
            );
        }

        return $allProducts;
    }

    private function getProductVariantsByProductId(
        $mattressProduct,
        $endCursor = null,
        $previousProductVariants = []
    ): array {
        $allProductVariants = $previousProductVariants;
        $productVariantsQueryByProductId = $this->graphQLQueryHelper->getProductVariantsByMattressProductId($endCursor);

        $response = $this->requestQuery($productVariantsQueryByProductId, [
            'perPage' => self::PER_PAGE,
            'endCursor' => $endCursor,
            'productId' => $mattressProduct['id'],
        ]);

        $variants = $response['data']['product']['variants'] ?? self::DEFAULT_ARRAY;

        return $this->getAllProductVariants($variants, $allProductVariants, $mattressProduct);
    }

    private function getAllProductVariants(
        $variants,
        $allProductVariants,
        $mattressProduct
    ): array {
        $allProductVariants = array_merge($variants['nodes'], $allProductVariants);

        if ($variants['pageInfo']['hasNextPage']) {
            return $this->getProductVariantsByProductId(
                $mattressProduct['id'],
                $variants['pageInfo']['endCursor'],
                $allProductVariants
            );
        }

        return $allProductVariants;
    }

    private function getVariantSize($selectedOptions): string
    {
        $variantSize = '';
        array_filter(
            $selectedOptions,
            function ($option) use (&$variantSize) {
                if (self::SIZE === strtolower($option['name'])) {
                    $variantSize = $option['value'];
                }
            }
        );

        return $variantSize;
    }

    private function requestQuery($query, $variables)
    {
        $url = $this->shopifySDK->GraphQL()->generateUrl();
        if (is_string($url)) {
            // be consistent with the \App\Command\AddDiscountsToProductsCommand
            $url = str_replace('/2022-04/', '/2022-07/', $url);
        }

        return $this->shopifySDK->GraphQL()->post($query, $url, false, $variables);
    }

    private function customSortByRecommendedBrands($prevData, $nextData, $recommendedBrands)
    {
        $positionPrev = array_search(strtolower($prevData['product']['vendor']), $recommendedBrands);
        $positionNext = array_search(strtolower($nextData['product']['vendor']), $recommendedBrands);
        if (false === $positionPrev) {
            $positionPrev = count($recommendedBrands);
        }
        if (false === $positionNext) {
            $positionNext = count($recommendedBrands);
        }

        return $positionPrev - $positionNext;
    }

    private function setMatchingVariantsByPriceRangeAndClosestMSRP(
        &$matchingVariants,
        $anotherVendor,
        $anotherVariant,
        &$minVendorPrices,
        $mattressVariant
    ) {
        $mattressVariantMSRP = max((float) $mattressVariant['compareAtPrice'], (float) $mattressVariant['price']);
        $anotherVariantMSRP = max((float) $anotherVariant['compareAtPrice'], (float) $anotherVariant['price']);
        $isCheapestPriceRange = $mattressVariantMSRP <= 1000 && $anotherVariantMSRP <= 2000;
        $isCheapPriceRange = $mattressVariantMSRP > 1000 && $mattressVariantMSRP <= 2000 && $anotherVariantMSRP <= 3500;
        $isHighPriceRange = $mattressVariantMSRP > 2000 && $mattressVariantMSRP <= 4000 && $anotherVariantMSRP <= 7000;
        $isHighestPriceRange = $mattressVariantMSRP > 4000 && $anotherVariantMSRP > 2000;
        if ($isCheapestPriceRange || $isCheapPriceRange || $isHighestPriceRange || $isHighPriceRange) {
            $isVendorExists = key_exists($anotherVendor, $matchingVariants);
            $newVendorPrices = $isVendorExists ? $mattressVariantMSRP - $anotherVariantMSRP : 0;
            $currentVendorPrices = $isVendorExists ? $mattressVariantMSRP - (float) $minVendorPrices[$anotherVendor] : 0;
            if (!$isVendorExists || $currentVendorPrices >= $newVendorPrices) {
                $matchingVariants[$anotherVendor] = $anotherVariant['id'];
                $minVendorPrices[$anotherVendor] = $anotherVariantMSRP;
            }
        }
    }
}
