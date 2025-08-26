<?php

namespace App\Command;

use App\Helper\GraphQLQueryHelper;
use PHPShopify\ShopifySDK;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AssociateFoundationsToMattressesCommand extends Command
{
    const PER_PAGE = 20;
    const KING_BLACK_FOUNDATION_HANDLE = 'kingsdown-black-foundation';
    const MATTRESSES = 'mattresses';
    const SIZE = 'size';
    const PROFILE = 'foundation profile';
    const FLOOR_MODEL = 'Floor Model';
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
            $this->setName('middleware:associate-foundations-to-mattresses');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        try {
            $youMightAlsoNeeds = [];
            $foundationData = [];
            $mattressVariants = [];

            $this->io->info('- Start reading data from RelatedFoundationRules.csv');
            $csvFilePath = 'var/import/RelatedFoundationRules.csv';
            $counter = 0;
            if (($handle = fopen($csvFilePath, 'r')) !== false) {
                while (($rows = fgetcsv($handle)) !== false) {
                    if (0 == $counter) {
                        ++$counter;
                        continue;
                    }

                    foreach ($rows as $key => $row) {
                        if (0 === $key) {
                            $foundationData[$counter - 1]['name'] = $row;
                            $foundationData[$counter - 1]['variants'] = [];
                        } elseif (1 === $key) {
                            $foundationData[$counter - 1]['vendor'] = $row;
                        } else {
                            if ('' !== $row) {
                                $this->io->writeln(sprintf('- Start querying product variants in "%s" product', $row));
                                $foundationData[$counter - 1]['variants'] = array_merge(
                                    $foundationData[$counter - 1]['variants'],
                                    $this->getProductVariantsByProductHandle($row, false)
                                );
                            }
                        }
                    }

                    ++$counter;
                }
            }

            if (0 === count($foundationData)) {
                $this->io->success('No found data in RelatedFoundationRules.csv');

                return Command::SUCCESS;
            }

            $this->io->info('- Start querying product variants in "Kingsdown Black Foundation" product');
            $defaultFoundationVariants = $this->getProductVariantsByProductHandle(
                self::KING_BLACK_FOUNDATION_HANDLE,
                false
            );

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
                $relatedVariantIds = [];
                $matchingFoundations = [];
                foreach ($foundationData as $data) {
                    if ($data['vendor'] === $mattressVariant['vendor']) {
                        $matchingFoundations = $data;
                        if ($data['name'] && false !== strpos($mattressVariant['title'], $data['name'])) {
                            break;
                        }
                    }
                }

                if (count($matchingFoundations)) {
                    $relatedVariantIds = $this->setYouMightAlsoNeeds(
                        $matchingFoundations['variants'],
                        $relatedVariantIds,
                        $mattressVariant['size']
                    );
                }

                if (0 === count($relatedVariantIds)) {
                    $relatedVariantIds = $this->setYouMightAlsoNeeds(
                        $defaultFoundationVariants,
                        $relatedVariantIds,
                        $mattressVariant['size']
                    );
                }

                $youMightAlsoNeeds[] = [
                    'namespace' => 'mw_marketing',
                    'key' => 'you_might_also_need',
                    'type' => 'list.variant_reference',
                    'ownerId' => $variantId,
                    'value' => json_encode($relatedVariantIds),
                ];
            }

            if (true === $this->updateAssociationOfFoundationsToMattresses($youMightAlsoNeeds)) {
                $this->io->success('Successfully updated '.count($youMightAlsoNeeds).' association of Foundations to Mattresses');

                return Command::SUCCESS;
            }
        } catch (\Exception $exception) {
            $this->io->error($exception->getMessage());

            return Command::FAILURE;
        }

        return Command::FAILURE;
    }

    private function updateAssociationOfFoundationsToMattresses($youMightAlsoNeeds): bool
    {
        $data['metafields'] = array_slice($youMightAlsoNeeds, 0, self::PER_PAGE);
        $remainingData = array_slice($youMightAlsoNeeds, self::PER_PAGE, count($youMightAlsoNeeds));
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
            $this->updateAssociationOfFoundationsToMattresses($remainingData);
        }

        return true;
    }

    private function setYouMightAlsoNeeds($foundationVariants, $youMightAlsoNeedIds, $mattressVariantSize): array
    {
        // process twice... the first loop gets the priority foundations related first
        // the second loop inverts the logic so that those that got skipped get tacked on last
        foreach ($foundationVariants as $foundationVariantId => $foundationVariant) {
            if (!$foundationVariant['profile']
                || (('Standard' == $foundationVariant['profile']) && ('Split Queen' != $foundationVariant['size']))
            ) {
                if (in_array($foundationVariant['size'], [$mattressVariantSize, 'Split '.$mattressVariantSize])) {
                    $youMightAlsoNeedIds[] = $foundationVariantId;
                }
            }
        }

        foreach ($foundationVariants as $foundationVariantId => $foundationVariant) {
            if ($foundationVariant['profile']
                && (('Standard' != $foundationVariant['profile']) || ('Split Queen' != $foundationVariant['size']))
            ) {
                if (in_array($foundationVariant['size'], [$mattressVariantSize, 'Split '.$mattressVariantSize])) {
                    $youMightAlsoNeedIds[] = $foundationVariantId;
                }
            }
        }

        return $youMightAlsoNeedIds;
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
            $selectedOptionProfile = null;
            array_filter(
                $productVariant['selectedOptions'],
                function ($option) use (&$selectedOptionSize) {
                    if (self::SIZE === strtolower($option['name'])) {
                        $selectedOptionSize = $option['value'];
                    }
                }
            );
            array_filter(
                $productVariant['selectedOptions'],
                function ($option) use (&$selectedOptionProfile) {
                    if (self::PROFILE === strtolower($option['name'])) {
                        $selectedOptionProfile = $option['value'];
                    }
                }
            );
            if ($isMattresses) {
                $allProductVariants[$productVariant['id']]['title'] = $productVariant['product']['title'];
                $allProductVariants[$productVariant['id']]['vendor'] = $productVariant['product']['vendor'];
                $allProductVariants[$productVariant['id']]['size'] = $selectedOptionSize;
            } else {
                $allProductVariants[$productVariant['id']]['size'] = $selectedOptionSize;
                $allProductVariants[$productVariant['id']]['profile'] = $selectedOptionProfile;
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
            $url = str_replace('/2022-04/', '/2022-07/', $url);
        }

        return $this->shopifySDK->GraphQL()->post($query, $url, false, $variables);
    }
}
