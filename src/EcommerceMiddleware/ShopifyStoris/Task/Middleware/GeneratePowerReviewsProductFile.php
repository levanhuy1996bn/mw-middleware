<?php

namespace App\EcommerceMiddleware\ShopifyStoris\Task\Middleware;

use Endertech\EcommerceMiddleware\Contracts\Connector\WithGetProductsInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\Field\VariantFieldDataDestinationVariantInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\VariantInterface;
use Endertech\EcommerceMiddleware\Core\Task\Data\Variant\AbstractVariantTask;
use Endertech\EcommerceMiddleware\Core\Traits\Connector\GraphQLFormatTrait;
use phpseclib3\Net\SFTP;

class GeneratePowerReviewsProductFile extends AbstractVariantTask
{
    use GraphQLFormatTrait;

    const LOG_RECORDS_GENERATED = 'task.middleware.generate_power_reviews_product_file.records_generated';
    const LOG_RECORDS_GENERATE_ERROR = 'task.middleware.generate_power_reviews_product_file.records_generate_error';
    const LOG_RECORDS_SENT = 'task.middleware.generate_power_reviews_product_file.records_sent';
    const LOG_RECORDS_SEND_ERROR = 'task.middleware.generate_power_reviews_product_file.records_send_error';

    private $records = [];
    private $endpoint;
    private $username;
    private $password;
    private $port = 22;
    private $productHandles = [];
    private $productTypes = [];
    private $productVendors = [];
    private $productImageUrls = [];
    private $variantPrices = [];
    private $variantUpcData = [];

    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    public function setPort($port)
    {
        $this->port = (int) $port;

        return $this;
    }

    protected function doStartTask()
    {
        parent::doStartTask();

        $this->generateHeader();
        $this->generateProductData();
        $this->generateVariantData();
    }

    protected function doRetrieveResults()
    {
        if (!$this->getObjectManager()) {
            return [];
        }

        return $this->getObjectManager()->getRepository(VariantInterface::class)->createQueryBuilder('v')
            ->select('v.id')
            ->getQuery()
            ->getArrayResult();
    }

    protected function doProcessResult($result, $resultIndex = null)
    {
        $orderId = $result['id'];
        $order = $this->getObjectManager()->getRepository(VariantInterface::class)->find($orderId);

        $this->generateVariantRow($order, $resultIndex);

        $this->getObjectManager()->clear(VariantFieldDataDestinationVariantInterface::class);
        $this->getObjectManager()->clear(VariantInterface::class);
    }

    protected function doEndTask()
    {
        $this->generateFile();

        if ($this->getOption('send-file')) {
            $this->sendFile();
        }

        parent::doEndTask();
    }

    private function generateHeader()
    {
        $this->records[] = ['page_id', 'page_id_variant', 'url', 'name', 'description', 'category', 'brand', 'upc', 'in_stock', 'price', 'image_url'];
    }

    private function addFileRecord($pageId, $pageIdVariant, $url, $name, $description, $category, $brand, $upc, $inStock, $price, $imageUrl)
    {
        if (strlen((string) $pageId) > 50) {
            $pageId = substr((string) $pageId, 0, 50);
        }

        $this->records[$pageId.'|||'.$pageIdVariant] = [$pageId, $pageIdVariant, $url, $name, $description, $category, $brand, $upc, $inStock, $price, $imageUrl];
    }

    private function generateVariantRow(VariantInterface $variant, $variantIndex = null)
    {
        $logContext = $this->createLogContext($variant, $variantIndex);

        if (!$variant->getSku() || !$variant->getDataSourceVariantId() || !$variant->getDataSourceProductId()) {
            return;
        }

        $inStock = 1;

        try {
            $this->addFileRecord(
                $this->getPageId($variant),
                '',
                $this->getUrl($variant),
                $variant->getDataSourceProductTitle(),
                $variant->getDataSourceProductTitle(),
                $this->getCategory($variant),
                $this->getBrand($variant),
                '',
                $inStock,
                '',
                $this->getImageUrl($variant)
            );

            $this->addFileRecord(
                $this->getPageId($variant),
                $variant->getSku(),
                $this->getUrl($variant),
                $variant->getDataSourceProductTitle(),
                $variant->getDataSourceProductTitle().' - '.str_replace(' / ', ' - ', $variant->getDataSourceVariantTitle()),
                $this->getCategory($variant),
                $this->getBrand($variant),
                $this->getUpc($variant),
                $inStock,
                $this->getPrice($variant),
                $this->getImageUrl($variant)
            );
        } catch (\Exception $e) {
            $this->logError(
                static::LOG_RECORDS_GENERATE_ERROR,
                'There was an error while adding a file record: {exceptionMessage}',
                $this->addExceptionLogContext($e, $logContext)
            );
        }
    }

    private function getPageId(VariantInterface $variant)
    {
        $productId = $variant->getDataSourceProductId();

        if ($productId && isset($this->productHandles[$this->normalizeCacheKey($productId)])) {
            return $this->productHandles[$this->normalizeCacheKey($productId)];
        }

        if ($productId) {
            $product = $this->getDataSource()->getDataSourceApiConnector()->getProduct($productId, ['fields' => 'id,handle']);
            $this->productHandles[$this->normalizeCacheKey($productId)] = $product['handle'] ?? null;

            if (isset($this->productHandles[$this->normalizeCacheKey($productId)])) {
                return $this->productHandles[$this->normalizeCacheKey($productId)];
            }
        }

        throw new \Exception('Could not determine the page_id from the provided arguments: '.json_encode([$variant->getSku(), $productId]));
    }

    private function getUrl(VariantInterface $variant)
    {
        $shopifySDK = $this->getDataSource()->getDataSourceApiConnector()->getShopify();
        $adminUrl = $shopifySDK::getAdminUrl();
        $urlParts = parse_url($adminUrl);

        return sprintf('https://%s/products/%s', $urlParts['host'], $this->getPageId($variant));
    }

    private function getCategory(VariantInterface $variant)
    {
        $productId = $variant->getDataSourceProductId();

        if ($productId && isset($this->productTypes[$this->normalizeCacheKey($productId)])) {
            return $this->productTypes[$this->normalizeCacheKey($productId)];
        }

        return '';
    }

    private function getBrand(VariantInterface $variant)
    {
        $productId = $variant->getDataSourceProductId();

        if ($productId && isset($this->productVendors[$this->normalizeCacheKey($productId)])) {
            return $this->productVendors[$this->normalizeCacheKey($productId)];
        }

        return '';
    }

    private function getUpc(VariantInterface $variant)
    {
        $variantId = $variant->getDataSourceVariantId();

        if ($variantId && isset($this->variantUpcData[$this->normalizeCacheKey($variantId)])) {
            return $this->variantUpcData[$this->normalizeCacheKey($variantId)];
        }

        return '';
    }

    private function getPrice(VariantInterface $variant)
    {
        $variantId = $variant->getDataSourceVariantId();

        if ($variantId && isset($this->variantPrices[$this->normalizeCacheKey($variantId)])) {
            return $this->variantPrices[$this->normalizeCacheKey($variantId)];
        }

        return '';
    }

    private function getImageUrl(VariantInterface $variant)
    {
        $productId = $variant->getDataSourceProductId();

        if ($productId && isset($this->productImageUrls[$this->normalizeCacheKey($productId)])) {
            return $this->productImageUrls[$this->normalizeCacheKey($productId)];
        }

        return '';
    }

    private function generateVariantData()
    {
        $productVariants = $this->getDataSource()->getDataSourceApiConnector()->getProductVariants(['fields' => 'id,barcode,price', 'limit' => 250]);

        foreach ($productVariants as $productVariant) {
            if (!isset($productVariant['id'])) {
                continue;
            }
            $this->variantUpcData[$this->normalizeCacheKey($productVariant['id'])] = $productVariant['barcode'] ?? null;
            $this->variantPrices[$this->normalizeCacheKey($productVariant['id'])] = $productVariant['price'] ?? null;
        }
    }

    private function generateProductData()
    {
        $products = $this->getDataSourceProducts(['fields' => 'id,handle,product_type,vendor,images', 'limit' => 250]);

        foreach ($products as $product) {
            if (!isset($product['id'])) {
                continue;
            }
            $this->productHandles[$this->normalizeCacheKey($product['id'])] = $product['handle'] ?? null;
            $this->productTypes[$this->normalizeCacheKey($product['id'])] = $product['product_type'] ?? $product['productType'] ?? null;
            $this->productVendors[$this->normalizeCacheKey($product['id'])] = $product['vendor'] ?? null;

            if (isset($product['images']) && isset($product['images'][0]) && isset($product['images'][0]['src'])) {
                $this->productImageUrls[$this->normalizeCacheKey($product['id'])] = $product['images'][0]['src'];
            } elseif (isset($product['media']) && isset($product['media']['nodes']) && isset($product['media']['nodes'][0]) && isset($product['media']['nodes'][0]['image']) && isset($product['media']['nodes'][0]['image']['url'])) {
                $this->productImageUrls[$this->normalizeCacheKey($product['id'])] = $product['media']['nodes'][0]['image']['url'];
            }
        }
    }

    private function getDataSourceProducts($params = null)
    {
        if ($this->getDataSource()->getDataSourceApiConnector() instanceof WithGetProductsInterface) {
            return $this->getDataSource()->getDataSourceApiConnector()->getProducts($params);
        }

        return [];
    }

    private function normalizeCacheKey($key)
    {
        return ' '.$this->normalizeMiddlewareId($key);
    }

    private function createLogContext(VariantInterface $variant, $variantIndex = null)
    {
        $logContext = [];

        $this->getDataMiddleware()->addResourceToLogContext($logContext, $variant);

        $this->getDataMiddleware()->addApiMetadataToLogContext($logContext);

        $logContext['middlewareVariantIndex'] = $variantIndex;

        return $logContext;
    }

    private function writeFileRecords($records, $filepath, $isAppend = false)
    {
        $fp = fopen($filepath, $isAppend ? 'a' : 'w');

        foreach ($records as $fields) {
            fputcsv($fp, $fields);
        }

        fclose($fp);
    }

    private function generateFile()
    {
        if (count($this->records) <= 1) {
            $this->logError(
                static::LOG_RECORDS_GENERATE_ERROR,
                'Error generating product records for Power Reviews',
                [
                    'records' => $this->records,
                ]
            );

            return;
        }

        $this->writeFileRecords($this->records, $this->getFilePath());

        $this->logInfo(
            static::LOG_RECORDS_GENERATED,
            'Generated {recordCount} product records for Power Reviews',
            [
                'records' => $this->records,
                'recordCount' => count($this->records) - 1,
            ]
        );
    }

    private function sendFile()
    {
        $recordCount = count($this->records);

        if ($recordCount <= 1) {
            return;
        }

        if (!$this->endpoint || !$this->username || !$this->password || !$this->port) {
            $this->logError(
                static::LOG_RECORDS_SEND_ERROR,
                'Error sending {recordCount} product records to Power Reviews: no credentials',
                [
                    'records' => $this->records,
                    'recordCount' => $recordCount - 1,
                ]
            );

            return;
        }

        if (!file_exists($this->getFilePath())) {
            $this->logError(
                static::LOG_RECORDS_SEND_ERROR,
                'Error sending {recordCount} product records to Power Reviews: no file exists',
                [
                    'records' => $this->records,
                    'recordCount' => $recordCount - 1,
                    'filePath' => $this->getFilePath(),
                ]
            );

            return;
        }

        $sftp = new SFTP($this->endpoint, $this->port);
        $sftp->login($this->username, $this->password);
        $ret = $sftp->put($this->getFileName(), $this->getFilePath(), SFTP::SOURCE_LOCAL_FILE);

        if (!$ret) {
            $this->logError(
                static::LOG_RECORDS_SEND_ERROR,
                'Error sending {recordCount} product records to Power Reviews: request error ({errorCode})',
                [
                    'records' => $this->records,
                    'recordCount' => $recordCount - 1,
                    'errorCode' => $sftp->getLastSFTPError(),
                ]
            );

            return;
        }

        $this->logInfo(
            static::LOG_RECORDS_SENT,
            'Sent {recordCount} product records to Power Reviews',
            [
                'records' => $this->records,
                'recordCount' => $recordCount - 1,
            ]
        );
    }

    private function getFilePath()
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.$this->getFileName();
    }

    private function getFileName()
    {
        $environment = 'TEST';

        if (in_array(strtolower((string) $this->getOption('pr-environment')), ['prod', 'live'])) {
            $environment = 'LIVE';
        }

        return sprintf('%s-sleep_outfitters_power_reviews_product_feed.csv', $environment);
    }
}
