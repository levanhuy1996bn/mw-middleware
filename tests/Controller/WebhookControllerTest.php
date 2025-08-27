<?php

namespace App\Tests\Controller;

use App\Tests\fixtures\Controller\HtmlRequestParser;
use Endertech\EcommerceMiddleware\ShopifyStoris\Tests\Task\ShopifyStorisTaskTesterTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WebhookControllerTest extends WebTestCase
{
    use ShopifyStorisTaskTesterTrait;

    public function testWebhookShopify()
    {
        $client = static::createClient();

        $json = [
            'id' => '1234567890',
        ];

        $_ENV['SHOPIFY_WEBHOOK_SECRET'] = $webhookSecret = 'test123';
        $webhookBody = json_encode($json);
        $webhookSignature = base64_encode(hash_hmac('sha256', $webhookBody, $webhookSecret, true));

        $client->jsonRequest('POST', '/webhook/shopify', $json, ['HTTP_X-SHOPIFY-HMAC-SHA256' => $webhookSignature]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
    }

    public function testWebhookShopifyInvalidSignature()
    {
        $client = static::createClient();

        $json = [
            'id' => '1234567890',
        ];

        $_ENV['SHOPIFY_WEBHOOK_SECRET'] = 'test123';
        $wrongSignature = base64_encode(hash_hmac('sha256', json_encode($json), 'wrong-secret', true));

        $client->jsonRequest('POST', '/webhook/shopify', $json, [
            'HTTP_X-SHOPIFY-HMAC-SHA256' => $wrongSignature,
        ]);

        $this->assertResponseStatusCodeSame(406);
    }

    public function testWebhookShopifyWithProductsCreateTopic()
    {
        $client = static::createClient();

        $json = [
            'id' => '1234567890',
        ];

        $_ENV['SHOPIFY_WEBHOOK_SECRET'] = $webhookSecret = 'test123';
        $webhookBody = json_encode($json);
        $webhookSignature = base64_encode(hash_hmac('sha256', $webhookBody, $webhookSecret, true));

        $client->jsonRequest('POST', '/webhook/shopify', $json, [
            'HTTP_X-SHOPIFY-EVENT-ID' => '9876543210',
            'HTTP_X-SHOPIFY-TOPIC' => 'products/create',
            'HTTP_X-SHOPIFY-SHOP-DOMAIN' => 'shop.example.com',
            'HTTP_X-SHOPIFY-HMAC-SHA256' => $webhookSignature,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        // Topic trace file
        $this->assertFileExists(__DIR__.'/../../var/webhook_shopify_products_create_event_triggered.txt');
        unlink(__DIR__.'/../../var/webhook_shopify_products_create_event_triggered.txt');

        // EventId trace file: sanitize("products/create.shop.example.com.9876543210") -> products_create_shop_example_com_9876543210
        $this->assertFileExists(__DIR__.'/../../var/webhook_shopify_products_create_shop_example_com_9876543210_event_triggered.txt');
        unlink(__DIR__.'/../../var/webhook_shopify_products_create_shop_example_com_9876543210_event_triggered.txt');
    }

    public function testWebhookShopifyWithProductsUpdateTopicCreatesTraceFile()
    {
        $client = static::createClient();

        $json = [
            'id' => 'abc-xyz-0001',
        ];

        $_ENV['SHOPIFY_WEBHOOK_SECRET'] = $webhookSecret = 'test123';
        $webhookBody = json_encode($json);
        $webhookSignature = base64_encode(hash_hmac('sha256', $webhookBody, $webhookSecret, true));

        $client->jsonRequest('POST', '/webhook/shopify', $json, [
            'HTTP_X-SHOPIFY-EVENT-ID' => 'evt-001',
            'HTTP_X-SHOPIFY-TOPIC' => 'products/update',
            'HTTP_X-SHOPIFY-SHOP-DOMAIN' => 'shop.example.com',
            'HTTP_X-SHOPIFY-HMAC-SHA256' => $webhookSignature,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        // Topic trace file
        $traceFile = __DIR__.'/../../var/webhook_shopify_products_update_event_triggered.txt';
        $this->assertFileExists($traceFile);
        unlink($traceFile);

        // EventId trace file: sanitize("products/update.shop.example.com.evt-001") -> products_update_shop_example_com_evt_001
        $eventTrace = __DIR__.'/../../var/webhook_shopify_products_update_shop_example_com_evt_001_event_triggered.txt';
        $this->assertFileExists($eventTrace);
        unlink($eventTrace);
    }
}
