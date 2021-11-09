<?php

namespace acclaro\translations\services\api;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use acclaro\translations\Constants;

class CraftApiClient
{
    protected $aliases;
    protected $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri'  => Constants::GRAPHQL_API_BASE_URI,
            'headers'   => [
                'Authorization' => sprintf('Bearer %s', Constants::GRAPHQL_ISO_MAPPING_TOKEN),
                'Accept' => 'application/json',
            ]
        ]);
    }

    // Public functions
    public function getAliases()
    {
        return $this->aliases ?: $this->fetchAliases();
    }

    // Private functions
    private function fetchAliases()
    {
        $query = ["query" => Constants::GRAPHQL_ISO_MAPPING_QUERY];

        $request = new Request('POST', '?'.http_build_query($query, '', '&'));

        try {
            // Wait only 5 seconds for server to respond else return null
            $response = $this->client->send($request, ['timeout' => 5.0]);

            if ($response->getStatusCode() != 200) {
                return null;
            }

            $body = $response->getBody();

            $responseJson = json_decode($body->getContents(), true);

            if (isset($responseJson['data']['entry']['isoMappingTable'])) {
                $this->aliases = array_column($responseJson['data']['entry']['isoMappingTable'], 'acclaroIsoCode', 'craftIsoCode');

                return $this->aliases;
            }
        } catch(\Exception $e) {
            return null;
        }

        return null;
    }
}