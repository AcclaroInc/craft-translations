<?php

namespace acclaro\translations\services\api;

use acclaro\translations\Constants;
use acclaro\translations\Translations;
use Google\Cloud\Translate\V2\TranslateClient;

class GoogleApiClient
{
    /**
     * Google client
     *
     * @var \Google\Cloud\Translate\V2\TranslateClient
     */
    private $client;

    public function __construct($token)
    {
        $this->client = new TranslateClient([
            'key' => $token
        ]);
    }

    public function authenticate()
    {
        try {
            return !!$this->getSupportedLanguages();
        } catch (\Exception $e) {
            Translations::$plugin->logHelper->log($e, Constants::LOG_LEVEL_ERROR);
            return false;
        }
    }

    public function getSupportedLanguages($targetLanguage = null)
    {
        $options = [];

        if ($targetLanguage) {
            $options['target'] = $targetLanguage;
        }

        try {
            return $this->client->localizedLanguages($options);
        } catch (\Throwable $th) {
            throw $th;
        }
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
            'target' => $targetLanguage
        ];

        if ($sourceLanguage) {
            $options['source'] = $sourceLanguage;
        }

        try {
            $result = $this->client->translateBatch($text, $options);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }

        // prepare responses
        $translations = [];
        foreach ($result as $translation) {
            $translations[] = html_entity_decode($translation['text'], ENT_QUOTES, 'UTF-8');
        }

        $result['data'] = $onceResult ? current($translations) : $translations;
        $result['success'] = true;

        return $result;
    }
}