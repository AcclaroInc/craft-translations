<?php

namespace acclaro\translations\services\api;

use Craft;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use acclaro\translations\Constants;
use acclaro\translations\Translations;

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

    /**
     * Get Mapping data and update local mapping if is updated on server
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getAliases(): array
    {
        if (! $this->aliases && $latestMapping = $this->fetchAliases()) {
            if (isset($latestMapping['isoMappingTable']) && isset($latestMapping['dateUpdated'])) {
                $this->aliases = array_column($latestMapping['isoMappingTable'], 'acclaroIsoCode', 'craftIsoCode');

                if ($this->isMappingOutdated($latestMapping['dateUpdated'])) {
                    $this->updateLocalIsoMapping([
                        'updated'           => $latestMapping['dateUpdated'],
                        'isoMappingTable'   => $this->aliases
                    ]);
                }
            }
        }

        return $this->aliases ?? $this->getLocalMap() ?: Constants::SITE_DEFAULT_ALIASES;
    }

    // Private functions

    /**
     * Makes graphql request to a3 server to fetch mapping and meta data.
     *
     * @return |null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
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

            return $responseJson['data']['entry'] ?? null;
        } catch(\Exception $e) {
            Translations::$plugin->logHelper->log('Exception fetching IsoMapping', Constants::LOG_LEVEL_ERROR);
            Translations::$plugin->logHelper->log($e->getMessage(), Constants::LOG_LEVEL_ERROR);
            return null;
        }
    }

    /**
     * Writes given array to local mapping file as json
     *
     * @param array $latestMapping
     * @return void
     */
    private function updateLocalIsoMapping(array $latestMapping): void
    {
        $alias = Craft::getAlias(Constants::PLUGIN_STORAGE_LOCATION);
        $fileName = Constants::ISO_MAPPING_FILE_NAME;

        $file = fopen($alias . '/' . $fileName, 'w');
        fwrite($file, json_encode($latestMapping));
        fclose($file);
    }

    /**
     * Return the check of if last updated time of mapping exists or is greater than given date
     *
     * @param [string] $latestUpdated
     * @return boolean
     */
    private function isMappingOutdated(string $latestUpdated): bool
    {
        $lastUpdated = $this->getLocalMapUpdated();
        return $lastUpdated === null || strtotime($latestUpdated) > strtotime($lastUpdated);
    }

    /**
     * Return either last updated date of mapping or null
     *
     * @return string|null
     */
    private function getLocalMapUpdated(): ?string
    {
        $result = null;
        if ($fileContents = $this->getMappingFileContents()) {
            $result = $fileContents['updated'];
        }

        return $result;
    }

    /**
     * Get local map of sites
     *
     * @return array
     */
    private function getLocalMap(): array
    {
        $result = [];
        if ($fileContents = $this->getMappingFileContents()) {
            $result = $fileContents['isoMappingTable'];
        }

        return $result;
    }

    /**
     * Returns mapping data from locally stored iso_mapping file else null
     *
     * @return array|null
     */
    private function getMappingFileContents(): ?array
    {
        $alias = Craft::getAlias(Constants::PLUGIN_STORAGE_LOCATION);
        $file = $alias . '/' . Constants::ISO_MAPPING_FILE_NAME;
        $result = null;

        if (! is_dir($alias)) {
            try {
                mkdir($alias, 0777, true);
            } catch (\Exception $e) {
                Translations::$plugin->logHelper->log($e->getMessage(), Constants::LOG_LEVEL_ERROR);
            }
        }

        if (file_exists($file)) {
            $result = file_get_contents($file);
        }

        return $result ? json_decode($result, true) : $result;
    }
}
