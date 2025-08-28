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
}
