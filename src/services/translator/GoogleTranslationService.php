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
use acclaro\translations\services\api\GoogleApiClient;

/**
 * @since 3.2.0
 */
class GoogleTranslationService implements TranslationServiceInterface
{
    private $apiClient;

    public function __construct(array $settings)
    {
        $this->apiClient = new GoogleApiClient($settings['apiToken']);
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate()
    {
        return $this->apiClient->authenticate();
    }

    /**
     * {@inheritdoc}
     */
    public function updateOrder(Order $order)
    {
        return (new Export_ImportTranslationService())->updateOrder($order);
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

            $response = $this->apiClient->translate($data, $targetLanguage, $sourceLanguage);

            if (!$response['success']) {
                $order->logActivity(sprintf(
                    'Error translating file "%s" Error: %s',
                    $source['content']['title'],
                    $response['message']
                ));
                continue;
            }

            $index = 0;
            foreach ($source['content'] as $key => $val) {
                $source['content'][$key] = $response['data'][$index];
                $index++;
            }

            $file->target = Translations::$plugin->elementToFileConverter->jsonToXml($source);
            $this->updateFile($order, $file);
        }
        
        $this->updateOrder($order);
        Translations::$plugin->orderRepository->saveOrder($order);
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
        return (new Export_ImportTranslationService())->updateIOFile($order, $file);
    }
}