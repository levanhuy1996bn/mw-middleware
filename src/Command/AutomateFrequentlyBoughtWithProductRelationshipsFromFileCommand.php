<?php

namespace App\Command;

use App\Helper\GraphQLQueryHelper;
use PHPShopify\ShopifySDK;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AutomateFrequentlyBoughtWithProductRelationshipsFromFileCommand extends Command
{
    const PER_PAGE = 20;
    const MATTRESSES = 'mattresses';
    const SIZE = 'size';
    const FLOOR_MODEL = 'Floor Model';
    const SPLIT = 'Split';
    const TOP = 'Top';
    const CAL = 'Cal';
    const CALIFORNIA = 'California';
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
            $this->setName('middleware:automate-frequently-bought-with-product-relationships-from-file');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        try {
            $frequentlyBoughtWithData = [];
            $accessoryData = [];
            $mattressVariants = [];

            $this->io->info('- Start reading data from FBWRelatedCrossSellRules.csv');
            $csvFilePath = 'var/import/FBWRelatedCrossSellRules.csv';
            $counter = 0;
            if (($handle = fopen($csvFilePath, 'r')) !== false) {
                while (($rows = fgetcsv($handle)) !== false) {
                    if (0 == $counter) {
                        ++$counter;
                        continue;
                    }

                    foreach ($rows as $key => $row) {
                        if (0 === $key) {
                            $accessoryData[$counter - 1]['name'] = $row;
                            $accessoryData[$counter - 1]['variants'] = [];
                        } elseif (1 === $key) {
                            $accessoryData[$counter - 1]['vendor'] = $row;
                        } else {
                            if ('' !== $row) {
                                $this->io->writeln(sprintf('- Start querying product variants in "%s" product', $row));
                                $accessoryData[$counter - 1]['variants'] = array_merge(
                                    $accessoryData[$counter - 1]['variants'],
                                    $this->getProductVariantsByProductHandle($row, false)
                                );
                            }
                        }
                    }

                    ++$counter;
                }
            }

            if (0 === count($accessoryData)) {
                $this->io->success('No found data in FBWRelatedCrossSellRules.csv');

                return Command::SUCCESS;
            }

            $this->io->info(' - Start querying products in "Mattresses" collection...');
            $mattressProducts = $this->getProductsByMattressesCollection();

            if (!count($mattressProducts)) {
                $this->io->success('No found products in "Mattresses" collections');

                return Command::SUCCESS;
            }

            foreach ($mattressProducts as $mattressProduct) {
                if (null !== $mattressProduct['featuredImage']) {
                    $this->io->writeln(sprintf(
                        ' - Start querying product variants in "%s" product',
                        $mattressProduct['handle']
                    ));

                    $mattressVariants = array_merge(
                        $mattressVariants,
                        $this->getProductVariantsByProductHandle($mattressProduct['handle'])
                    );
                }
            }

            foreach ($mattressVariants as $variantId => $mattressVariant) {
                $relatedVariants = [];
                $matchingAccessories = [];
                foreach ($accessoryData as $data) {
                    if ($data['vendor'] === $mattressVariant['vendor']) {
                        if ($data['name']) {
                            if (str_contains($mattressVariant['title'], $data['name'])) {
                                $matchingAccessories = $data;
                                break;
                            }
                        } else {
                            $matchingAccessories = $data;
                        }
                    }
                }

                if (count($matchingAccessories)) {
                    $relatedVariants = $this->setFrequentlyBoughtWith(
                        $matchingAccessories['variants'],
                        $relatedVariants,
                        $mattressVariant['size']
                    );
                }

                $relatedVariantIds = [];
                if (count($relatedVariants)) {
                    foreach ($relatedVariants as $variant) {
                        $perfectSizeVariantId = $variant['perfect-size'] ?? $variant['near-perfect-size'] ?? $variant['other-size'] ?? null;
                        if ($perfectSizeVariantId) {
                            $relatedVariantIds[] = $perfectSizeVariantId;
                        }
                    }
                }

                $frequentlyBoughtWithData[] = [
                    'namespace' => 'mw_marketing',
                    'key' => 'frequently_bought_with',
                    'type' => 'list.variant_reference',
                    'ownerId' => $variantId,
                    'value' => json_encode($relatedVariantIds),
                ];
            }

            if (true === $this->updateFrequentlyBoughtWithToMattresses($frequentlyBoughtWithData)) {
                $this->io->success('Successfully updated '.count($frequentlyBoughtWithData).' association of Accessories to Mattresses');

                return Command::SUCCESS;
            }
        } catch (\Exception $exception) {
            $this->io->error($exception->getMessage());

            return Command::FAILURE;
        }

        return Command::FAILURE;
    }

    private function updateFrequentlyBoughtWithToMattresses($frequentlyBoughtWithData): bool
    {
        $data['metafields'] = array_slice($frequentlyBoughtWithData, 0, self::PER_PAGE);
        $remainingData = array_slice($frequentlyBoughtWithData, self::PER_PAGE, count($frequentlyBoughtWithData));
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
            $this->updateFrequentlyBoughtWithToMattresses($remainingData);
        }

        return true;
    }

    private function setFrequentlyBoughtWith($accessoryVariants, $frequentlyBoughtWithIds, $mattressVariantSize): array
    {
        foreach ($accessoryVariants as $accessoryVariantId => $accessoryVariant) {
            $normalizedAccessorySize = $accessoryVariant['size'] ?: '';
            $normalizedMattressSize = $mattressVariantSize ?: '';

            // Remove the word "Split" from both accessory and mattress size for comparison
            $normalizedAccessorySize = trim(str_ireplace('(1pc)', '', $normalizedAccessorySize));
            $normalizedAccessorySize = trim(str_ireplace(self::CALIFORNIA, self::CAL, $normalizedAccessorySize));
            $normalizedMattressSize = trim(str_ireplace('(1pc)', '', $normalizedMattressSize));
            $normalizedMattressSize = trim(str_ireplace(self::CALIFORNIA, self::CAL, $normalizedMattressSize));

            // Compare the normalized sizes
            if ($normalizedAccessorySize === $normalizedMattressSize) {
                $frequentlyBoughtWithIds[$accessoryVariant['handle']]['perfect-size'] = $accessoryVariantId;
                continue;
            }

            $mattressSizes = explode('/', $normalizedMattressSize);
            $mapToSizes = [];
            foreach ($mattressSizes as $mattressSize) {
                $mattressSize = $mattressSize ?: '';
                $mattressSizeWithoutSplit = trim(str_ireplace(self::SPLIT, '', $mattressSize));
                $mapToSizes = array_merge(
                    $mapToSizes,
                    [$mattressSize, self::SPLIT." {$mattressSize}", $mattressSizeWithoutSplit]
                );
                if (count($mattressSizes) > 1) {
                    $mapToSizes[] = $normalizedMattressSize;
                } elseif (!empty($normalizedAccessorySize) && '' !== trim($normalizedAccessorySize) && str_contains($normalizedAccessorySize, '/')) {
                    if (str_starts_with($normalizedAccessorySize, "{$mattressSize}/")
                        || str_starts_with($normalizedAccessorySize, self::SPLIT." {$mattressSize}/")
                        || str_starts_with($normalizedAccessorySize, "{$mattressSizeWithoutSplit}/")
                        || str_ends_with($normalizedAccessorySize, "/{$mattressSize}")
                        || str_ends_with($normalizedAccessorySize, "/{$mattressSizeWithoutSplit}")
                        || str_ends_with($normalizedAccessorySize, '/'.self::SPLIT." {$mattressSize}")
                    ) {
                        $mapToSizes[] = $normalizedAccessorySize;
                    }
                }
            }

            if (in_array($normalizedAccessorySize, $mapToSizes)) {
                $frequentlyBoughtWithIds[$accessoryVariant['handle']]['near-perfect-size'] = $accessoryVariantId;
                continue;
            }

            if (empty($frequentlyBoughtWithIds[$accessoryVariant['handle']]['other-size'])) {
                $frequentlyBoughtWithIds[$accessoryVariant['handle']]['other-size'] = $accessoryVariantId;
            }
        }

        return $frequentlyBoughtWithIds;
    }

    private function getProductsByMattressesCollection($endCursor = null, $previousProducts = []): array
    {
        $productsQueryByCollection = $this->graphQLQueryHelper->getProductsQueryByMattressesCollection($endCursor);

        $response = $this->requestQuery($productsQueryByCollection, [
            'perPage' => self::PER_PAGE,
            'endCursor' => $endCursor,
            'collectionName' => self::MATTRESSES,
        ]);

        $productsByCollection = $response['data']['collectionByHandle']['products'] ?? self::DEFAULT_ARRAY;

        $allProducts = array_merge($productsByCollection['nodes'], $previousProducts);

        if ($productsByCollection['pageInfo']['hasNextPage']) {
            return $this->getProductsByMattressesCollection(
                $productsByCollection['pageInfo']['endCursor'],
                $allProducts
            );
        }

        return $allProducts;
    }

    private function getProductVariantsByProductHandle(
        $productHandle,
        $isMattresses = true,
        $endCursor = null,
        $previousProductVariants = []
    ): array {
        $allProductVariants = $previousProductVariants;
        $productVariantsByProductHandle = $this->graphQLQueryHelper->getProductVariantsQueryByProductHandle($endCursor);

        $response = $this->requestQuery($productVariantsByProductHandle, [
            'perPage' => self::PER_PAGE,
            'endCursor' => $endCursor,
            'productHandle' => $productHandle,
        ]);

        if (false === $isMattresses) {
            $productType = $response['data']['product']['productType'] ?? '';

            if (self::FLOOR_MODEL === $productType) {
                return [];
            }
        }

        return $this->getAllProductVariants($response, $allProductVariants, $productHandle, $isMattresses);
    }

    private function getAllProductVariants(
        $response,
        $allProductVariants,
        $productHandle,
        $isMattresses
    ): array {
        $variants = $response['data']['product']['variants'] ?? self::DEFAULT_ARRAY;
        foreach ($variants['nodes'] as $productVariant) {
            $selectedOptionSize = null;
            array_filter(
                $productVariant['selectedOptions'],
                function ($option) use (&$selectedOptionSize) {
                    if (self::SIZE === strtolower($option['name'])) {
                        $selectedOptionSize = $option['value'];
                    }
                }
            );
            $allProductVariants[$productVariant['id']]['handle'] = $productHandle;
            if ($isMattresses) {
                $allProductVariants[$productVariant['id']]['title'] = $productVariant['product']['title'];
                $allProductVariants[$productVariant['id']]['vendor'] = $productVariant['product']['vendor'];
                $allProductVariants[$productVariant['id']]['size'] = $selectedOptionSize;
            } else {
                $allProductVariants[$productVariant['id']]['size'] = $selectedOptionSize;
            }
        }

        if ($variants['pageInfo']['hasNextPage']) {
            return $this->getProductVariantsByProductHandle(
                $productHandle,
                $isMattresses,
                $variants['pageInfo']['endCursor'],
                $allProductVariants
            );
        }

        return $allProductVariants;
    }

    private function requestQuery($query, $variables)
    {
        $url = $this->shopifySDK->GraphQL()->generateUrl();
        if (is_string($url)) {
            // be consistent with the \App\Command\AddDiscountsToProductsCommand
            $url = str_replace('/2022-04/', '/2022-10/', $url);
        }

        return $this->shopifySDK->GraphQL()->post($query, $url, false, $variables);
    }
}
