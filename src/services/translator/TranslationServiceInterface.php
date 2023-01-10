<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\translator;

use acclaro\translations\elements\Order;
use acclaro\translations\models\FileModel;

interface TranslationServiceInterface
{
    /**
     * Fetch order from service and update order model accordingly
     * @param  \acclaro\translations\elements\Order  $order
     * @return void
     */
    public function updateOrder(Order $order);

    /**
     * Fetch file from service and update file model accordingly
     * @param  \acclaro\translations\elements\Order  $order
     * @param  \acclaro\translations\models\FileModel   $file
     * @return void
     */
    public function updateFile(Order $order, FileModel $file);

    /**
     * Validate authentication credentials
     * @return boolean
     */
    public function authenticate();

    /**
     * Sync order to service and update order model accordingly
     * @param  \acclaro\translations\elements\Order $order
     * @param  array $fileIds
     * @return void
     */
    public function syncOrder(Order $order, array $fileIds);

    /**
     * Send order to service and update order model accordingly
     * @param  \acclaro\translations\elements\Order $order
     * @return void
     */
    public function sendOrder(Order $order);

    /**
     * Get supported languages by translation service
     * 
     * @return array
     */
    public function getLanguages();

    /**
     * Get supported language pairs for a source language
     * @return array
     */
    public function getLanguagePairs(string $sourceLanguage);
}