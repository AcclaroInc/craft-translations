<?php

/**
 * Translations for Craft plugin for Craft CMS 4.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\translator;


use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\elements\Order;
use acclaro\translations\models\FileModel;
use acclaro\translations\services\api\ChatGPTApiClient;

/**
 * @since 3.2.0
 */
class ChatGPTTranslationService implements TranslationServiceInterface
{
    private $apiClient;

    public function __construct(array $settings)
    {
        $this->apiClient = new ChatGPTApiClient($settings['apiToken'], $settings['orgId'], $settings['prompt']);
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate()
    {
        $response = $this->apiClient->testAuthentication();
        return $response['success'];
    }

    /**
     * {@inheritdoc}
     */
    public function updateOrder(Order $order)
    {
        return (new Export_ImportTranslationService([]))->updateOrder($order);
    }

    /**
     * {@inheritdoc}
     */
    public function updateFile(Order $order, FileModel $file)
    {
        $file->status = Constants::FILE_STATUS_REVIEW_READY;
        $file->dateDelivered = new \DateTime();
        Translations::$plugin->fileRepository->saveFile($file);
        return ;
    }

    /**
     * {@inheritdoc}
     */
    public function syncOrder(Order $order, $selectedFiles, $queue = null)
    {
        foreach ($order->getFiles() as $file) {
            // Only process files that are selected and is new or modified
            if (!in_array($file->id, $selectedFiles) || !($file->isNew() || $file->isInProgress() || $file->isModified())) {
                continue;
                
            }

            $source = json_decode(Translations::$plugin->elementToFileConverter->xmlToJson($file->source), true);
            $data = array_values($source['content']);
            $sourceLanguage = $file->getSourceLangCode();
            $targetLanguage = $file->getTargetLangCode();

            $response = $this->getTranslatedData($data, $sourceLanguage, $targetLanguage);

            if (!$response['success'] || !array_key_exists('data', $response) || !is_array($response['data'])) {
                $order->logActivity(sprintf(
                    'Error translating file "%s" Error: %s',
                    $source['content']['title'],
                    $response['message']
                ));
                continue;
            }

            $index = 0;

            foreach ($source['content'] as $key => $val) {
                if ($index >= count($response['data'])) {
                    break;
                }
                $source['content'][$key] = $response['data'][$index];
                $index++;
            }

            //echo "source = " . json_encode($source) . "\n\n";
            $file->target = Translations::$plugin->elementToFileConverter->jsonToXml($source);
            //echo "target = " . $file->target . "\n\n";
            $this->updateFile($order, $file);
        }
        
        $this->updateOrder($order);
        Translations::$plugin->orderRepository->saveOrder($order);
    }

    /**
     * Processes chunks of data and returns a response
     * 
     * @param array $chunkArray - array of data to be translated
     * @param string $sourceLanguage - source language code
     * @param string $targetLanguage - target language code
     * @param bool $oneString - whether the array contains one string that needs to be concatenated back together
     * 
     */


    private function chunkTranslations(array $chunkArray, $sourceLanguage, $targetLanguage, $oneString = false)
    {
        $response = ['success' => true, 'message' => ''];
        $tempResponse = null;

        foreach ($chunkArray as $chunk) {
            if (array_is_list($chunk)) {
                $processChunk = $chunk;
            } else {
                $processChunk = [];
                if (is_array($chunk)) {
                    foreach ($chunk as $key => $value) {
                        $processChunk[] = $value;
                    }
                } else if (!is_string($chunk)) {
                    $chunkArray = json_decode(json_encode($chunk), true);
                    foreach ($chunkArray as $key => $value) {
                        $processChunk[] = $value;
                    }
                } else {
                    $processChunk = $chunk;
                }
            } 
            if (! $tempResponse) {
                $tempResponse = $this->apiClient->translate($processChunk, $targetLanguage, $sourceLanguage);
                if (! $tempResponse['success']) {
                    throw new \Exception($tempResponse['message']);
                }
            } else {
                $temp = $this->apiClient->translate($processChunk, $targetLanguage, $sourceLanguage);
                if (!$temp['success']) {

                    throw new \Exception($temp['message']);
                    
                } else {
                    if ($oneString) { // if the array contains one string that needs to be concatenated back together
                        $tempResponse['data']= is_array($tempResponse['data']) ? implode(" ", $tempResponse['data']) : $tempResponse['data'];
                        $temp['data']= is_array($temp['data']) ? implode($temp['data']) : $temp['data'];
                        $tempResponse['data'] .= $temp['data'];
                    } else {
                        $tempResponse['data']= $this->fixArray($tempResponse['data']);
                        $temp['data']= $this->fixArray($temp['data']);
                        $tempResponse['data'] = array_merge($tempResponse['data'], $temp['data']);
                    }
                }
            }
        }

        $response['data'] = $tempResponse['data'];
        return $response;
    }

    /**
     * Fixes array if it is not an array
     * 
     * @return array
     */
    private function fixArray($data) {
        return is_array($data) ? $data : [$data];
    }

    /**
     * Translates $data array in case the length of array is more than 100 then chunks it down to make request
     * 
     * @return array
     */ 
    public function getTranslatedData($data, $sourceLanguage, $targetLanguage) 
    {
        $response = ['success' => true, 'message' => ''];
        try {
            if (str_word_count(implode($data)) > 1000) { // too many words for ChatGPT
                $tempResponse = null;
                foreach ($data as $dataString) {
                    if (str_word_count($dataString) > 1000) { // too many words in a string
                        if (strpos($dataString, "</p>")) { // if string contains paragraph tags
                            $explodeString = explode("</p>", $dataString);
                            $chunkResponse = $this->chunkTranslations($explodeString, $sourceLanguage, $targetLanguage, true);
                        } else { // very unlikely, but if there is a very long plain text string, break it up into 1000 word chunks
                            $explodeString = explode(" ", $dataString);
                            $dataChunk = array_chunk($explodeString, 1000, true);
                            $chunkResponse = $this->chunkTranslations($dataChunk, $sourceLanguage, $targetLanguage, true);
                        }
                        $chunkResponse['data'] = $this->fixArray($chunkResponse['data']);
                        $tempResponse['data'] = array_merge($tempResponse['data'], $chunkResponse['data']);
                    } else { // too many words in multiple strings
                        $dataStringArray = is_array($dataString) ?  $dataString : [$dataString];
                        if (! $tempResponse) {
                            $tempResponse = $this->apiClient->translate($dataStringArray, $targetLanguage, $sourceLanguage);
                            if (! $tempResponse['success']) {
                                throw new \Exception($tempResponse['message']);
                            }
                        } else {
                            $temp = $this->apiClient->translate($dataStringArray, $targetLanguage, $sourceLanguage);

                            if (!$temp['success']) {
                                throw new \Exception($temp['message']);
                            } else {
                                $tempResponse['data']= $this->fixArray($tempResponse['data']);
                                $temp['data']= $this->fixArray($temp['data']);
                                $tempResponse['data'] = array_merge($tempResponse['data'], $temp['data']);
                            }
                        }
                     
                    }
                    
                }
                $response['data'] = $tempResponse['data'];
            } elseif (count($data) > 100) { // too many data
                $dataChunk = array_chunk($data, 99, true);
                $response = $this->chunkTranslations($dataChunk, $sourceLanguage, $targetLanguage);
            } else {
                $response = $this->apiClient->translate($data, $targetLanguage, $sourceLanguage);
            }
            
        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        }
        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function sendOrder(Order $order)
    {
        return;
    }

    /**
     * {@inheritdoc}
     */
    public function getLanguages()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getLanguagePairs(string $sourceLanguage)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderUrl(Order $order): string
    {
        return  sprintf('#%s', $order->id);
    }

    public function updateIOFile(Order $order, FileModel $file)
    {
        return (new Export_ImportTranslationService([]))->updateIOFile($order, $file);
    }
}