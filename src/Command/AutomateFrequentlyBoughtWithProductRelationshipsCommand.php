<?php

namespace App\Command;

use App\Helper\GraphQLQueryHelper;
use PHPShopify\ShopifySDK;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AutomateFrequentlyBoughtWithProductRelationshipsCommand extends Command
{
    const PER_PAGE = 20;
    const FLOOR_MODEL = 'Floor Model';
    const SIZE = 'size';
    const KING = 'King';
    const QUEEN = 'Queen';
    const STANDARD = 'Standard';
    const CAL_KING = 'California King';
    const PILLOWS = 'pillows';
    const MATTRESSES = 'mattresses';
    const STANDARD_PILLOW = 'Bed Pillow';
    const SHEETS_BLANKETS_PILLOW_CASES = 'sheets-blankets-pillow-cases';
    const MATTRESS_PROTECTORS_SLUG = 'mattress-protectors';
    const MATTRESS_PROTECTORS = 'Mattress Protectors';
    const TEMPUR_PEDIC_COMBED_COTTON_SHEET_SET = 'tempur-pedic-combed-cotton-sheet-set';
    const BEDGEAR_BASIC_SHEET_SET = 'bedgear-basic-sheet-set';
    const BED_SHEETS = 'Bed Sheets';
    const CRIB = 'crib';
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
            $this->setName('middleware:automate-frequently-bought-with-product-relationships');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $mattressVariants = [];
        $frequentlyBoughtWithMetafields = [];
        $pillowsVariants = [
            'all' => [],
            'cheap' => [],
            'mid' => [],
            'high' => [],
        ];

        $sheetsVariants = [
            'all' => [],
            'cheap' => [],
            'mid' => [],
            'high' => [],
        ];

        $mattressProtectorsVariants = [
            'all' => [],
            'cheap' => [],
            'mid' => [],
            'high' => [],
        ];

        $this->io->writeln('- Start querying product variants in "Pillows" collection...');
        $pillowsProducts = $this->getFrequentlyBoughtWithProductQueryByCollection(self::PILLOWS);
        $pillowsVariants = $this->getProductVariantsInPillowsCollection($pillowsProducts, $pillowsVariants);

        $this->io->writeln('- Start querying product variants in "Sheets" collection...');
        $sheetsProducts = $this->getFrequentlyBoughtWithProductQueryByCollection(self::SHEETS_BLANKETS_PILLOW_CASES);
        $sheetsVariants = $this->getProductVariantsInSheetsCollection($sheetsProducts, $sheetsVariants);

        $this->io->writeln('- Start querying product variants in "Mattress Protectors" collection...');
        $mattressProtectorsProducts = $this->getFrequentlyBoughtWithProductQueryByCollection(self::MATTRESS_PROTECTORS_SLUG);
        $mattressProtectorsVariants = $this->getProductVariantsInMattressProtectorsCollection(
            $mattressProtectorsProducts,
            $mattressProtectorsVariants
        );

        $this->io->writeln('- Start querying product variants in "Mattresses" collection...');
        $mattressProducts = $this->getFrequentlyBoughtWithProductQueryByCollection(self::MATTRESSES);
        foreach ($mattressProducts as $mattressProduct) {
            $this->io->writeln(sprintf(
                ' - Start querying product variants in productID %s...',
                $mattressProduct['id']
            ));
            $mattressVariants = array_merge(
                $mattressVariants,
                $this->getProductVariantsByProductId($mattressProduct['id'])
            );
        }

        if (0 === count($mattressVariants)) {
            $this->io->success('No found product variants in "Mattresses" collections');

            return Command::SUCCESS;
        }

        $this->io->writeln('- Start looping the mattress variants in "Mattresses" collection...');
        foreach ($mattressVariants as $mattressVariant) {
            $this->io->writeln('- Start setting mattress variant metafields with variantID '.$mattressVariant['id']);
            if (null === $mattressVariant['size']) {
                continue;
            }
            $mattressMetafieldsValue = [];
            $sheetKey = self::PILLOWS.$mattressVariant['id'];
            $protectorKey = self::MATTRESS_PROTECTORS_SLUG.$mattressVariant['id'];
            $pillowKey = self::SHEETS_BLANKETS_PILLOW_CASES.$mattressVariant['id'];
            if ((float) $mattressVariant['price'] < 1000) {
                // set value for mattress metafields by sheet variants and mattress protectors variants
                $mattressMetafieldsValue = $this->setMattressMetafieldsValueBySheetsAndMattressProtectors(
                    $sheetsVariants['cheap'],
                    $mattressVariant['size'],
                    $mattressMetafieldsValue,
                    $sheetKey
                );
                $mattressMetafieldsValue = $this->setMattressMetafieldsValueBySheetsAndMattressProtectors(
                    $mattressProtectorsVariants['cheap'],
                    $mattressVariant['size'],
                    $mattressMetafieldsValue,
                    $protectorKey
                );

                // set value for mattress metafields by pillows variants
                $mattressMetafieldsValue = $this->setMattressMetafieldsValueByPillows(
                    $pillowsVariants['cheap'],
                    $mattressVariant['size'],
                    $mattressMetafieldsValue,
                    $pillowKey
                );
            } elseif ((float) $mattressVariant['price'] < 2500) {
                // set value for mattress metafields by sheet variants and mattress protectors variants
                $mattressMetafieldsValue = $this->setMattressMetafieldsValueBySheetsAndMattressProtectors(
                    $sheetsVariants['mid'],
                    $mattressVariant['size'],
                    $mattressMetafieldsValue,
                    $sheetKey
                );
                $mattressMetafieldsValue = $this->setMattressMetafieldsValueBySheetsAndMattressProtectors(
                    $mattressProtectorsVariants['mid'],
                    $mattressVariant['size'],
                    $mattressMetafieldsValue,
                    $protectorKey
                );

                // set value for mattress metafields by pillows variants
                $mattressMetafieldsValue = $this->setMattressMetafieldsValueByPillows(
                    $pillowsVariants['mid'],
                    $mattressVariant['size'],
                    $mattressMetafieldsValue,
                    $pillowKey
                );
            } else {
                // set value for mattress metafields by sheet variants and mattress protectors variants
                $mattressMetafieldsValue = $this->setMattressMetafieldsValueBySheetsAndMattressProtectors(
                    $sheetsVariants['high'],
                    $mattressVariant['size'],
                    $mattressMetafieldsValue,
                    $sheetKey
                );
                $mattressMetafieldsValue = $this->setMattressMetafieldsValueBySheetsAndMattressProtectors(
                    $mattressProtectorsVariants['high'],
                    $mattressVariant['size'],
                    $mattressMetafieldsValue,
                    $protectorKey
                );

                // set value for mattress metafields by pillows variants
                $mattressMetafieldsValue = $this->setMattressMetafieldsValueByPillows(
                    $pillowsVariants['high'],
                    $mattressVariant['size'],
                    $mattressMetafieldsValue,
                    $pillowKey
                );
            }

            $frequentlyBoughtWithMetafields[] = [
                'namespace' => 'mw_marketing',
                'key' => 'frequently_bought_with',
                'type' => 'list.variant_reference',
                'ownerId' => $mattressVariant['id'],
                'value' => json_encode(array_values($mattressMetafieldsValue)),
            ];
        }

        if (!count($frequentlyBoughtWithMetafields)) {
            $this->io->success('No product variants available');

            return Command::SUCCESS;
        }

        if (true === $this->updateFrequentlyBoughtWithMetafieldsToMattresses($frequentlyBoughtWithMetafields)) {
            $this->io->success('Successfully updated '.count($frequentlyBoughtWithMetafields).' product variants');

            return Command::SUCCESS;
        }

        return Command::SUCCESS;
    }

    private function updateFrequentlyBoughtWithMetafieldsToMattresses($frequentlyBoughtWithMetafields): bool
    {
        $data['metafields'] = array_slice($frequentlyBoughtWithMetafields, 0, self::PER_PAGE);
        $remainingData = array_slice(
            $frequentlyBoughtWithMetafields,
            self::PER_PAGE,
            count($frequentlyBoughtWithMetafields)
        );
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
            $this->updateFrequentlyBoughtWithMetafieldsToMattresses($remainingData);
        }

        return true;
    }

    private function setMattressMetafieldsValueByPillows(
        $variants,
        $mattressVariantSize,
        $frequentlyBoughtWithValue,
        $mattressVariantId
    ): array {
        $existKingKeys = [];
        $existQueenKeys = [];
        foreach ($variants as $variant) {
            $isExistKey = in_array($mattressVariantId, array_keys($frequentlyBoughtWithValue));
            if (in_array($mattressVariantSize, [self::KING, self::CAL_KING])) {
                if (!in_array($mattressVariantId, $existKingKeys)) {
                    if (self::KING === $variant['size']) {
                        $existKingKeys[] = $mattressVariantId;
                        $frequentlyBoughtWithValue[$mattressVariantId] = $variant['id'];
                    } elseif (self::QUEEN === $variant['size']) {
                        $frequentlyBoughtWithValue[$mattressVariantId] = $variant['id'];
                    } elseif (self::STANDARD === $variant['size'] && !$isExistKey) {
                        $frequentlyBoughtWithValue[$mattressVariantId] = $variant['id'];
                    }
                }
            } else {
                if (self::QUEEN === $mattressVariantSize) {
                    if (!in_array($mattressVariantId, $existQueenKeys)) {
                        if ($variant['size'] === $mattressVariantSize) {
                            $existQueenKeys[] = $mattressVariantId;
                            $frequentlyBoughtWithValue[$mattressVariantId] = $variant['id'];
                        } elseif (self::STANDARD === $variant['size']) {
                            $frequentlyBoughtWithValue[$mattressVariantId] = $variant['id'];
                        }
                    }
                } else {
                    if (self::STANDARD === $variant['size'] && !$isExistKey) {
                        $frequentlyBoughtWithValue[$mattressVariantId] = $variant['id'];
                    }
                }
            }
        }

        return $frequentlyBoughtWithValue;
    }

    private function setMattressMetafieldsValueBySheetsAndMattressProtectors(
        $variants,
        $mattressVariantSize,
        $frequentlyBoughtWithValue,
        $mattressVariantId
    ): array {
        foreach ($variants as $variant) {
            if ($variant['size'] === $mattressVariantSize) {
                if (!in_array($mattressVariantId, array_keys($frequentlyBoughtWithValue))) {
                    $frequentlyBoughtWithValue[$mattressVariantId] = $variant['id'];
                }
            }
        }

        return $frequentlyBoughtWithValue;
    }

    private function getFrequentlyBoughtWithProductQueryByCollection(
        $collectionName,
        $endCursor = null,
        $previousProducts = []
    ): array {
        $productsQuery = $this->graphQLQueryHelper->getProductsQueryByCollectionHandle($endCursor);

        $response = $this->requestQuery($productsQuery, [
            'perPage' => self::PER_PAGE,
            'endCursor' => $endCursor,
            'collectionName' => $collectionName,
        ]);

        $productsByCollection = $response['data']['collection']['products'] ?? self::DEFAULT_ARRAY;

        $allProducts = array_merge($productsByCollection['nodes'], $previousProducts);

        if ($productsByCollection['pageInfo']['hasNextPage']) {
            return $this->getFrequentlyBoughtWithProductQueryByCollection(
                $collectionName,
                $productsByCollection['pageInfo']['endCursor'],
                $allProducts
            );
        }

        return $allProducts;
    }

    private function getProductVariantsByProductId($productId, $endCursor = null, $previousProductVariants = []): array
    {
        $allProductVariants = $previousProductVariants;
        $productVariantsQuery = $this->graphQLQueryHelper->getMattressAccessoriesVariantsQueryByProductId($endCursor);

        $response = $this->requestQuery($productVariantsQuery, [
            'perPage' => self::PER_PAGE,
            'endCursor' => $endCursor,
            'productId' => $productId,
        ]);

        $variants = $response['data']['product']['variants'] ?? self::DEFAULT_ARRAY;
        $allProductVariants = array_merge($variants['nodes'], $allProductVariants);
        foreach ($variants['nodes'] as $key => $productVariant) {
            $selectedOptionSize = [];
            array_filter(
                $productVariant['selectedOptions'],
                function ($option) use (&$selectedOptionSize, $key) {
                    if (self::SIZE === strtolower($option['name'])) {
                        $selectedOptionSize = [$key => $option['value']];
                    }
                }
            );
            if (count($selectedOptionSize) > 0) {
                $allProductVariants[$key]['size'] = $selectedOptionSize[$key];

                $variantSize = ['name' => 'Size', 'value' => $selectedOptionSize[$key]];
                $variantSizeKey = array_search($variantSize, $allProductVariants[$key]['selectedOptions']);

                if (false !== $variantSizeKey) {
                    unset($allProductVariants[$key]['selectedOptions'][$variantSizeKey]);
                }
            } else {
                $allProductVariants[$key]['size'] = null;
            }
        }

        if ($variants['pageInfo']['hasNextPage']) {
            return $this->getProductVariantsByProductId(
                $productId,
                $variants['pageInfo']['endCursor'],
                $allProductVariants
            );
        }

        return $allProductVariants;
    }

    // pillow_type = "Standard Pillow", Cheap = < $100, Mid = $100 <=> $150, High = > $150
    private function getProductVariantsInPillowsCollection($pillowsProducts, $pillowsVariants): array
    {
        foreach ($pillowsProducts as $pillowsProduct) {
            if ('ACTIVE' != $pillowsProduct['status'] || null == $pillowsProduct['featuredImage'] || self::FLOOR_MODEL === $pillowsProduct['productType']) {
                continue;
            }
            if ($pillowsProduct['pillowType'] && self::STANDARD_PILLOW === $pillowsProduct['pillowType']['value']) {
                $pillowsVariants['all'] = array_merge(
                    $pillowsVariants['all'],
                    $this->getProductVariantsByProductId($pillowsProduct['id'])
                );
            }
        }

        if (0 === count($pillowsVariants['all'])) {
            $this->io->success('No found product variants in "Pillows" collections');
        } else {
            $this->io->writeln('- Start processing product variants in "Pillows" collections...');
        }

        foreach ($pillowsVariants['all'] as $variant) {
            if ((float) $variant['price'] < 100) {
                $pillowsVariants['cheap'][] = $variant;
            } elseif ((float) $variant['price'] <= 150) {
                $pillowsVariants['mid'][] = $variant;
            } else {
                $pillowsVariants['high'][] = $variant;
            }
        }

        return $pillowsVariants;
    }

    /*
     * product_type = "Bed Sheets", Cheap = handle = "tempur-pedic-combed-cotton-sheet-set"
     * Mid = handle = “bedgear-basic-sheet-set”, High = > $100
     * */
    private function getProductVariantsInSheetsCollection($sheetsProducts, $sheetsVariants): array
    {
        foreach ($sheetsProducts as $sheetsProduct) {
            if ('ACTIVE' != $sheetsProduct['status'] || null == $sheetsProduct['featuredImage'] || self::FLOOR_MODEL === $sheetsProduct['productType']) {
                continue;
            }
            if (self::BED_SHEETS === $sheetsProduct['productCategory']['productTaxonomyNode']['name']) {
                $sheetsVariants['all'] = array_merge(
                    $sheetsVariants['all'],
                    $this->getProductVariantsByProductId($sheetsProduct['id'])
                );
            }
        }

        if (0 === count($sheetsVariants['all'])) {
            $this->io->success('No found product variants in "Sheets" collections');
        } else {
            $this->io->writeln('- Start processing product variants in "Sheets" collections...');
        }

        foreach ($sheetsVariants['all'] as $variant) {
            if (self::BEDGEAR_BASIC_SHEET_SET === $variant['product']['handle']) {
                $sheetsVariants['cheap'][] = $variant;
                $sheetsVariants['mid'][] = $variant;
            } elseif ((float) $variant['price'] >= 100) {
                $sheetsVariants['high'][] = $variant;
            }
        }

        return $sheetsVariants;
    }

    /*
     * product_type = "Mattress Protectors", Cheap = < $80 AND name not contains "crib"
     * Mid = $80 <=> $110, High = > $110
     * */
    private function getProductVariantsInMattressProtectorsCollection(
        $mattressProtectorsProducts,
        $mattressProtectorsVariants
    ): array {
        foreach ($mattressProtectorsProducts as $protectorsProduct) {
            if ('ACTIVE' != $protectorsProduct['status'] || null == $protectorsProduct['featuredImage'] || self::FLOOR_MODEL === $protectorsProduct['productType']) {
                continue;
            }
            if (self::MATTRESS_PROTECTORS === $protectorsProduct['productCategory']['productTaxonomyNode']['name']) {
                $mattressProtectorsVariants['all'] = array_merge(
                    $mattressProtectorsVariants['all'],
                    $this->getProductVariantsByProductId($protectorsProduct['id'])
                );
            }
        }

        if (0 === count($mattressProtectorsVariants['all'])) {
            $this->io->success('No found product variants in "Mattress Protectors" collections');
        } else {
            $this->io->writeln('- Start processing product variants in "Mattress Protectors" collections...');
        }

        foreach ($mattressProtectorsVariants['all'] as $variant) {
            if ((float) $variant['price'] < 80) {
                $productTitles = explode(' ', strtolower($variant['product']['title']));
                if (!in_array(self::CRIB, $productTitles)) {
                    $mattressProtectorsVariants['cheap'][] = $variant;
                }
            } elseif ((float) $variant['price'] <= 110) {
                $mattressProtectorsVariants['mid'][] = $variant;
            } else {
                $mattressProtectorsVariants['high'][] = $variant;
            }
        }

        return $mattressProtectorsVariants;
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
