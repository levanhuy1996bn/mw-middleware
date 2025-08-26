<?php

namespace App\Command;

use App\Helper\GraphQLQueryHelper;
use PHPShopify\ShopifySDK;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AssociateAverageReviewScoreToAllProductsCommand extends Command
{
    const PER_PAGE = 24;
    const LOCALE = 'en_US';

    const DEFAULT_ARRAY = [
        'nodes' => [],
        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
    ];

    /**
     * @var GraphQLQueryHelper
     */
    private $graphQLQueryHelper;

    /**
     * @var ParameterBagInterface
     */
    private $params;

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
        ParameterBagInterface $params,
        string $name = null
    ) {
        parent::__construct($name);

        $this->shopifySDK = $shopifySDK;
        $this->graphQLQueryHelper = $graphQLQueryHelper;
        $this->params = $params;
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
            $this->setName('middleware:associate-average-review-score-to-all-products');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->info(' - Start querying all products');
        $reviewData = [];

        try {
            $allProducts = $this->getAllProducts();
            if (!count($allProducts)) {
                $this->io->success('No found products');

                return Command::SUCCESS;
            }

            foreach ($allProducts as $product) {
                $this->io->writeln(sprintf(' - Start getting review data by productID #%s...', $product['id']));
                $reviewData = $this->getReviewDataByProduct($product, $reviewData);
            }

            if (true === $this->updateReviewMetafieldsToAllProducts($reviewData)) {
                $this->io->success(sprintf('Successfully updated review data to %d products', count($reviewData) / 6));

                return Command::SUCCESS;
            }

            return Command::FAILURE;
        } catch (\Exception $exception) {
            $this->io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }

    private function getAllProducts($endCursor = null, $previousProducts = []): array
    {
        $productQuery = $this->graphQLQueryHelper->getProductQuery($endCursor);

        $response = $this->requestQuery($productQuery, [
            'endCursor' => $endCursor,
            'perPage' => 250,
        ]);

        $newProducts = $response['data']['products'] ?? self::DEFAULT_ARRAY;
        $allProducts = array_merge($newProducts['nodes'], $previousProducts);

        if ($newProducts['pageInfo']['hasNextPage']) {
            return $this->getAllProducts(
                $newProducts['pageInfo']['endCursor'],
                $allProducts
            );
        }

        return $allProducts;
    }

    private function getReviewDataByProduct($product, $reviewData): array
    {
        $powerReviewsBaseUrl = $this->params->get('power_reviews_base_url');
        $powerReviewsMerchantId = $this->params->get('power_reviews_merchant_id');
        $powerReviewsReadApiKey = $this->params->get('power_reviews_read_api_key');
        $powerReviewUrl = sprintf(
            '%s/m/%s/l/%s/product/%s/reviews?apikey=%s',
            $powerReviewsBaseUrl,
            $powerReviewsMerchantId,
            self::LOCALE,
            substr($product['handle'], 0, 50),
            $powerReviewsReadApiKey
        );

        $response = file_get_contents($powerReviewUrl, false, $this->createContext());
        $data = json_decode($response, true);

        $faceoffPositive = null;
        $faceoffNegative = null;
        $listCons = [];
        $listPros = [];
        $averageRating = $data['results'][0]['rollup']['average_rating'] ?? 0;
        $reviewCount = $data['results'][0]['rollup']['review_count'] ?? 0;
        $recommendedRatio = $data['results'][0]['rollup']['recommended_ratio'] ?? 0;
        if (isset($data['results'][0]['rollup'])) {
            $rollup = $data['results'][0]['rollup'];
            if (isset($rollup['faceoff_positive']) && is_array($rollup['faceoff_positive'])) {
                $faceoffPositive = $this->setMostHelpfulReview(
                    $rollup['faceoff_positive'],
                    $powerReviewsBaseUrl,
                    $powerReviewsMerchantId,
                    $powerReviewsReadApiKey
                );
            }

            if (isset($rollup['faceoff_negative']) && is_array($rollup['faceoff_negative'])) {
                $faceoffNegative = $this->setMostHelpfulReview(
                    $rollup['faceoff_negative'],
                    $powerReviewsBaseUrl,
                    $powerReviewsMerchantId,
                    $powerReviewsReadApiKey
                );
            }

            if (isset($rollup['properties']) && is_array($rollup['properties'])) {
                foreach ($rollup['properties'] as $property) {
                    if ('cons' === $property['key']) {
                        $listCons = $property['display_values'];
                    } elseif ('pros' === $property['key']) {
                        $listPros = $property['display_values'];
                    }
                }
            }
        }

        $this->setReviewData(
            'average_review_score',
            'number_decimal',
            $product['id'],
            (string) $averageRating,
            $reviewData
        );
        $this->setReviewData(
            'review_count',
            'number_integer',
            $product['id'],
            (string) $reviewCount,
            $reviewData
        );
        $this->setReviewData(
            'recommended_ratio',
            'number_decimal',
            $product['id'],
            (string) $recommendedRatio,
            $reviewData
        );
        $this->setReviewData(
            'most_helpful_positive_review',
            'json',
            $product['id'],
            json_encode($faceoffPositive),
            $reviewData
        );
        $this->setReviewData(
            'most_helpful_negative_review',
            'json',
            $product['id'],
            json_encode($faceoffNegative),
            $reviewData
        );

        //$this->setReviewData(
        //    'review_cons_list',
        //    'list.single_line_text_field',
        //    $product['id'],
        //    json_encode($listCons),
        //    $reviewData
        //);
        //
        //$this->setReviewData(
        //    'review_pros_list',
        //    'list.single_line_text_field',
        //    $product['id'],
        //    json_encode($listPros),
        //    $reviewData
        //);

        return $reviewData;
    }

    private function setMostHelpfulReview(
        $review,
        $powerReviewsBaseUrl,
        $powerReviewsMerchantId,
        $powerReviewsReadApiKey
    ): \stdClass {
        $data = new \stdClass();
        $data->ratingValue = $review['rating'];
        $data->reviewTitle = $review['headline'];
        $data->reviewBody = $review['comments'];

        $reviewDetailsUrl = sprintf(
            '%s/m/%s/l/%s/review/%s?apikey=%s',
            $powerReviewsBaseUrl,
            $powerReviewsMerchantId,
            self::LOCALE,
            $review['ugc_id'],
            $powerReviewsReadApiKey
        );
        $response = file_get_contents($reviewDetailsUrl, false, $this->createContext());
        $reviewById = json_decode($response, true);
        $data->reviewAuthor = $reviewById['results'][0]['details']['nickname'] ?? '';

        return $data;
    }

    private function setReviewData($key, $type, $ownerId, $value, &$reviewData)
    {
        $reviewData[] = [
            'namespace' => 'mw_marketing',
            'key' => $key,
            'type' => $type,
            'ownerId' => $ownerId,
            'value' => $value,
        ];
    }

    private function updateReviewMetafieldsToAllProducts($reviewData): bool
    {
        $data['metafields'] = array_slice($reviewData, 0, self::PER_PAGE);
        $remainingData = array_slice($reviewData, self::PER_PAGE, count($reviewData));
        $response = $this->requestQuery($this->graphQLQueryHelper->getMetafieldsSetQuery(), $data);
        $userErrors = $response['data']['metafieldsSet']['userErrors'] ?? [];

        if (count($userErrors)) {
            foreach ($userErrors as $error) {
                $this->io->error($error['message']);
            }

            return false;
        }

        $this->io->success(sprintf('Successfully updated review data to %d products', count($data['metafields']) / 6));

        if (count($remainingData)) {
            $this->updateReviewMetafieldsToAllProducts($remainingData);
        }

        return true;
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

    private function buildHeaders(array $headers): string
    {
        return implode("\r\n", $headers) . "\r\n";
    }

    private function createContext(array $headers = [], string $method = 'GET')
    {
        $headerString = self::buildHeaders(array_merge([
            'Accept: application/json'
        ], $headers));

        $options = [
            'http' => [
                'method'  => $method,
                'header'  => $headerString
            ]
        ];

        return stream_context_create($options);
    }
}
