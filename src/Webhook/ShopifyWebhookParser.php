<?php

namespace App\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

class ShopifyWebhookParser extends AbstractRequestParser
{
    const WEBHOOK_NAME = 'shopify';

    const EVENT_TOPICS = [
        // GraphQL => REST
        'PRODUCTS_CREATE' => 'products/create',
        'PRODUCTS_UPDATE' => 'products/update',
    ];

    public function createSuccessfulResponse(): Response
    {
        return parent::createSuccessfulResponse()->setStatusCode(Response::HTTP_OK);
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?RemoteEvent
    {
        $this->validateSignature($request->headers, $request->getContent(), $secret);

        $payload = $request->toArray();

        $id = $request->headers->get('X-Shopify-Event-Id') ?? microtime(true);
        $topic = $request->headers->get('X-Shopify-Topic');
        $domain = $request->headers->get('X-Shopify-Shop-Domain');

        $eventId = sprintf('%s.%s.%s', $topic, $domain, $id);

        return new RemoteEvent(static::WEBHOOK_NAME, $eventId, $payload);
    }

    private function validateSignature(HeaderBag $headers, string $body, #[\SensitiveParameter] string $secret): void
    {
        $signature = $headers->get('X-Shopify-Hmac-SHA256');

        if (!hash_equals(''.$signature, base64_encode(hash_hmac('sha256', $body, $secret, true)))) {
            throw new RejectWebhookException(406, 'Signature is wrong.');
        }
    }
}
