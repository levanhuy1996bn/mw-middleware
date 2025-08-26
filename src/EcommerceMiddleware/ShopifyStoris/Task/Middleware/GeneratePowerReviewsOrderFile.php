<?php

namespace App\EcommerceMiddleware\ShopifyStoris\Task\Middleware;

use Endertech\EcommerceMiddleware\Contracts\Connector\WithGetProductsInterface;
use Endertech\EcommerceMiddleware\Contracts\Customer\WithCustomerEmailInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\Field\OrderFieldDataDestinationOrderInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\Field\OrderFieldDataDestinationRequestInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\Field\OrderFieldDataSourceFulfillmentOrderInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\Field\OrderFieldDataSourceOrderInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\Field\OrderFieldDataSourceRequestInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\Field\OrderFieldDataSourceTransactionInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\Field\VariantFieldDataDestinationVariantInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\OrderInterface;
use Endertech\EcommerceMiddleware\Contracts\Model\VariantInterface;
use Endertech\EcommerceMiddleware\Core\Task\Data\Order\AbstractOrderTask;
use Endertech\EcommerceMiddleware\Core\Traits\Connector\GraphQLFormatTrait;
use phpseclib3\Net\SFTP;

class GeneratePowerReviewsOrderFile extends AbstractOrderTask
{
    use GraphQLFormatTrait;

    const LOG_RECORDS_GENERATED = 'task.middleware.generate_power_reviews_order_file.records_generated';
    const LOG_RECORDS_GENERATE_ERROR = 'task.middleware.generate_power_reviews_order_file.records_generate_error';
    const LOG_RECORDS_SENT = 'task.middleware.generate_power_reviews_order_file.records_sent';
    const LOG_RECORDS_SEND_ERROR = 'task.middleware.generate_power_reviews_order_file.records_send_error';

    private $records = [];
    private $endpoint;
    private $username;
    private $password;
    private $port = 22;
    private $productHandles = [];

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
    }

    protected function doRetrieveResults()
    {
        if (!$this->getObjectManager()) {
            return [];
        }

        return $this->getObjectManager()->getRepository(OrderInterface::class)->createQueryBuilder('o')
            ->select('o.id')
            ->where('o.dataSourceOrderCreatedAt >= :timeAgo')
            ->setParameter('timeAgo', new \DateTime('midnight -5 days'))
            ->getQuery()
            ->getArrayResult();
    }

    protected function doProcessResult($result, $resultIndex = null)
    {
        $orderId = $result['id'];
        $order = $this->getObjectManager()->getRepository(OrderInterface::class)->find($orderId);

        $this->generateOrderRow($order, $resultIndex);

        $this->getObjectManager()->clear(OrderFieldDataDestinationOrderInterface::class);
        $this->getObjectManager()->clear(OrderFieldDataDestinationRequestInterface::class);
        $this->getObjectManager()->clear(OrderFieldDataSourceFulfillmentOrderInterface::class);
        $this->getObjectManager()->clear(OrderFieldDataSourceOrderInterface::class);
        $this->getObjectManager()->clear(OrderFieldDataSourceRequestInterface::class);
        $this->getObjectManager()->clear(OrderFieldDataSourceTransactionInterface::class);
        $this->getObjectManager()->clear(OrderInterface::class);
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
        $this->records[] = ['page_id', 'order_id', 'first_name', 'last_name', 'email', 'order_date', 'locale', 'variant', 'user_id'];
    }

    private function addFileRecord($pageId, $orderId, $firstName, $lastName, $email, $orderDate, $locale = null, $variant = null, $userId = null)
    {
        $orderId = $this->normalizeMiddlewareId($orderId);
        $userId = $this->normalizeMiddlewareId($userId);

        if (strlen((string) $pageId) > 50) {
            $pageId = substr((string) $pageId, 0, 50);
        }
        if (strlen((string) $orderId) > 50) {
            $orderId = substr((string) $orderId, 0, 50);
        }
        if (strlen((string) $firstName) > 255) {
            $firstName = substr((string) $firstName, 0, 255);
        }
        if (strlen((string) $lastName) > 255) {
            $lastName = substr((string) $lastName, 0, 255);
        }
        if (strlen((string) $email) > 255) {
            $email = substr((string) $email, 0, 255);
        }
        if ($orderDate instanceof \DateTimeInterface) {
            $orderDate = $orderDate->format('Y-m-d');
        }
        if (!$locale) {
            $locale = 'en_US';
        }
        if (strlen((string) $variant) > 50) {
            $variant = substr((string) $variant, 0, 50);
        }
        if (strlen((string) $userId) > 50) {
            $userId = substr((string) $userId, 0, 50);
        }

        $this->records[] = [$pageId, $orderId, $firstName, $lastName, $email, $orderDate, $locale, $variant, $userId];
    }

    private function generateOrderRow(OrderInterface $order, $orderIndex = null)
    {
        $logContext = $this->createLogContext($order, $orderIndex);

        $email = null;
        if ($order instanceof WithCustomerEmailInterface) {
            $email = $order->getDataSourceCustomerEmail();
        }
        if (!$email || !$order->getDataSourceOrderId() || !$order->getDataSourceCustomerId() || !$order->getDataSourceOrderJson()) {
            return;
        }

        $json = $order->getDataSourceOrderJson();
        $lineItems = $json['line_items'] ?? $json['lineItems']['nodes'] ?? [];

        $firstName = $json['customer']['first_name'] ?? $json['customer']['firstName'] ?? null;
        $lastName = $json['customer']['last_name'] ?? $json['customer']['lastName'] ?? null;
        if (!$firstName || !$lastName) {
            $firstName = $json['customer']['default_address']['first_name'] ?? $json['customer']['defaultAddress']['firstName'] ?? null;
            $lastName = $json['customer']['default_address']['last_name'] ?? $json['customer']['defaultAddress']['lastName'] ?? null;
        }
        if (!$firstName || !$lastName) {
            $firstName = $json['billing_address']['first_name'] ?? $json['billingAddress']['firstName'] ?? null;
            $lastName = $json['billing_address']['last_name'] ?? $json['billingAddress']['lastName'] ?? null;
        }
        if (!$firstName || !$lastName) {
            $firstName = $json['shipping_address']['first_name'] ?? $json['shippingAddress']['firstName'] ?? null;
            $lastName = $json['shipping_address']['last_name'] ?? $json['shippingAddress']['lastName'] ?? null;
        }
        if (!$firstName || !$lastName) {
            return;
        }

        foreach ($lineItems as $lineItem) {
            $productId = $lineItem['product_id'] ?? $lineItem['product']['id'] ?? null;
            $variantId = $lineItem['variant_id'] ?? $lineItem['variant']['id'] ?? null;
            $sku = $lineItem['sku'] ?? null;

            $this->addFileRecord(
                $this->getPageId($productId, $variantId, $sku),
                $order->getDataSourceOrderId(),
                $firstName,
                $lastName,
                $email,
                $order->getDataSourceOrderCreatedAt(),
                null,
                $sku,
                $order->getDataSourceCustomerId()
            );
        }
    }

    private function getPageId($productId = null, $variantId = null, $sku = null)
    {
        if (!$productId) {
            $search = [];
            if ($sku) {
                $search['sku'] = $sku;
            }
            if ($this->normalizeMiddlewareId($variantId)) {
                $search['dataSourceVariantId'] = $this->normalizeMiddlewareId($variantId);
            }
            if ($search) {
                $variant = $this->getObjectManager()->getRepository(VariantInterface::class)->findOneBy($search);
                if ($variant instanceof VariantInterface) {
                    $productId = $variant->getDataSourceProductId();
                }
            }
        }

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

        throw new \Exception('Could not determine the page_id from the provided arguments: '.json_encode([$productId, $variantId, $sku]));
    }

    private function generateProductData()
    {
        $products = $this->getDataSourceProducts(['fields' => 'id,handle', 'limit' => 250]);

        foreach ($products as $product) {
            if (!isset($product['id'])) {
                continue;
            }
            $this->productHandles[$this->normalizeCacheKey($product['id'])] = $product['handle'] ?? null;
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

    private function createLogContext(OrderInterface $order, $orderIndex = null)
    {
        $logContext = [];

        $this->getDataMiddleware()->addResourceToLogContext($logContext, $order);

        $this->getDataMiddleware()->addApiMetadataToLogContext($logContext);

        $logContext['middlewareOrderIndex'] = $orderIndex;

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
                'Error generating order records for Power Reviews',
                [
                    'records' => $this->records,
                ]
            );

            return;
        }

        $this->writeFileRecords($this->records, $this->getFilePath());

        $this->logInfo(
            static::LOG_RECORDS_GENERATED,
            'Generated {recordCount} order records for Power Reviews',
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
                'Error sending {recordCount} order records to Power Reviews: no credentials',
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
                'Error sending {recordCount} order records to Power Reviews: no file exists',
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
                'Error sending {recordCount} order records to Power Reviews: request error ({errorCode})',
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
            'Sent {recordCount} order records to Power Reviews',
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

        return sprintf('%s-sleep_outfitters_power_reviews_order_feed.csv', $environment);
    }
}
