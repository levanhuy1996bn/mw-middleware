<?php

namespace App\Location;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class YextLocation
{
    const API_LOCATIONS_URL_FORMAT = 'https://cdn.yextapis.com/v2/accounts/%s/content/locations?id=%s&v=20230807';

    private $httpClient;
    private $yextApiAccountId;
    private $yextApiKey;

    public function __construct(HttpClientInterface $httpClient, $yextApiAccountId, $yextApiKey)
    {
        $this->httpClient = $httpClient;
        $this->yextApiAccountId = $yextApiAccountId;
        $this->yextApiKey = $yextApiKey;
    }

    public function getLocationById($locationId)
    {
        $url = sprintf(static::API_LOCATIONS_URL_FORMAT, $this->yextApiAccountId, $locationId);

        return $this->httpClient->request('GET', $url, ['headers' => ['api-key' => $this->yextApiKey]]);
    }
}
