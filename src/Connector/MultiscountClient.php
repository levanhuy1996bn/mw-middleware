<?php

namespace App\Connector;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class MultiscountClient
{
    const API_DISCOUNTS_URL = 'https://sleep-outfitters.myshopify.com/apps/multiscount/v2/discounts';
    const API_SHOP = 'sleep-outfitters.myshopify.com';
    const API_TYPE_GIFT = 'gift';
    const API_TYPE_ORDER = 'order';
    const API_TYPE_VOLUME = 'volume';

    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getDiscounts($title, $type, $params = [])
    {
        $url = static::API_DISCOUNTS_URL;

        $params['title'] = $title;
        $params['type'] = $type;

        return $this->httpClient->request('POST', $url, ['json' => $params, 'query' => ['shop' => static::API_SHOP]]);
    }

    public function getDiscountsData($title)
    {
        $data = [];

        if (!$title) {
            return $data;
        }

        try {
            $data = $this->getDiscounts($title, static::API_TYPE_VOLUME)->toArray();
            if (isset($data[0])) {
                $data = $data[0];
            }
        } catch (\Exception $e) {
            // do nothing
        }

        if (!(isset($data['products']) && is_array($data['products']) && count($data['products']) > 0)) {
            try {
                $data = $this->getDiscounts($title, static::API_TYPE_ORDER)->toArray();
                if (isset($data[0])) {
                    $data = $data[0];
                }
            } catch (\Exception $e) {
                // do nothing
            }
        }

        if (!(isset($data['products']) && is_array($data['products']) && count($data['products']) > 0)) {
            try {
                $data = $this->getDiscounts($title, static::API_TYPE_GIFT)->toArray();
                if (isset($data[0])) {
                    $data = $data[0];
                }
            } catch (\Exception $e) {
                // do nothing
            }
        }

        return $data;
    }

    public function getProductsAndCollectionsFromDiscounts($title)
    {
        $ret = [];
        $ret['products'] = [];
        $ret['collections'] = [];

        $data = $this->getDiscountsData($title);

        $products = $data['products'] ?? [];

        foreach ($products as $product) {
            if (false !== stripos(''.$product, 'Collection')) {
                $ret['collections'][] = $product;
            } elseif (false !== stripos(''.$product, 'Product')) {
                $ret['products'][] = $product;
            }
        }

        return $ret;
    }
}
