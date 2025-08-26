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

    public function testWebhookShopifyWithTopic()
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
            'HTTP_X-SHOPIFY-TOPIC' => 'orders/create',
            'HTTP_X-SHOPIFY-SHOP-DOMAIN' => 'shop.example.com',
            'HTTP_X-SHOPIFY-HMAC-SHA256' => $webhookSignature,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $this->assertFileExists(__DIR__.'/../../var/webhook_shopify_orders_create_event_triggered.txt');

        unlink(__DIR__.'/../../var/webhook_shopify_orders_create_event_triggered.txt');
    }
}
