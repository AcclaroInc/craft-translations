<?php

namespace acclaro\translations\services\api;

use acclaro\translations\Constants;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Response;

class GoogleApiClient
{
    /**
     * Access key
     *
     * @var string
     */
    private $accessKey;

    /**
     * Http client
     *
     * @var \GuzzleHttp\ClientInterface
     */
    private $client;

    public function __construct($token)
    {
        $this->accessKey = $token;
        
        $this->client = new HttpClient([
            'base_uri' => Constants::GOOGLE_TRANSLATE_API_URL,
            'headers' => array(
                'Content-Type'  => 'application/json'
            ),
        ]);
    }

    public function getSupportedLanguages($targetLanguage = null)
    {
        $options = [
            'key' => $this->accessKey
        ];

        if ($targetLanguage) {
            $options['target'] = $targetLanguage;
        }

        $response = $this->request(
            Constants::REQUEST_METHOD_GET,
            'languages',
            $options
        );

        return $response;
    }

    public function translate(string|array $text, string $targetLanguage, string $sourceLanguage = null)
    {
        // validate if required fields has being filled.
        if (empty($text)) {
            throw new \Exception("Empty target text");
        }

        // used to return the same type of variable used in the text
        $onceResult = !is_array($text);

        // prepare the string
        $text = is_array($text) ? $text : [$text];

        // query params
        $options = [
            'q' => $text,
            'target' => $targetLanguage
        ];

        if ($sourceLanguage) {
            $options['source'] = $sourceLanguage;
        }

        // add access key
        $options['key'] = $this->accessKey;
        
        $result = $this->request(
            Constants::REQUEST_METHOD_POST,
            '',
            $options
        );
        
        if (!$result['success']) {
            return $result;
        }

        // prepare responses
        $translations = [];
        $sources = [];
        foreach ($result['data']['translations'] as $translation) {
            $translations[] = html_entity_decode($translation['translatedText'], ENT_QUOTES, 'UTF-8');

            if (array_key_exists('detectedSourceLanguage', $translation)) {
                $sources[] = $translation['detectedSourceLanguage'];
            }
        }

        // add source language by reference if it was not passed.
        if (!$sourceLanguage) {
            $sourceLanguage = $onceResult ? current($sources) : $sources;
        }
        
        $result['data'] = $onceResult ? current($translations) : $translations;

        return $result;
    }

    private function request($method, $endpoint, $options)
    {
        try {
            $response = $this->client->request(
                $method,
                $this->getUrl($endpoint),
                ['query' => $this->buildQuery($options)]
            );
            
            $res = $this->parseResponse($response);

            return ['success' => true, 'data' => $res];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

    }
    
    private function getUrl(string $endpoint): string
    {
        return sprintf('%s/%s', Constants::GOOGLE_TRANSLATE_API_URL, $endpoint);
    }

    /**
     * Create a query string
     *
     * @param array $params
     * @return string
     */
    private function buildQuery($params)
    {
        $query = [];
        foreach ($params as $key => $param) {
            if (!is_array($param)) {
                continue;
            }
            // when a param has many values, it generate the query string separated to join late
            foreach ($param as $subParam) {
                $query[] = http_build_query([$key => $subParam]);
            }
            unset($params[$key]);
        }

        // join queries strings
        $query[] = http_build_query($params);
        $query = implode('&', $query);

        return $query;
    }
    
    private function parseResponse(Response $res)
    {
        $result = json_decode($res->getBody(), true);
        if (
            !is_array($result) ||
            !array_key_exists('data', $result)
        ) {
            throw new \Exception('Invalid response');
        }
        
        return $result['data'];
    }
}