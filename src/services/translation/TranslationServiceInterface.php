<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\services\translation;

use Craft;
use Exception;
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\elements\Order;
use acclaro\translationsforcraft\models\FileModel;
use acclaro\translationsforcraft\TranslationsForCraft;
use acclaro\translationsforcraft\services\job\Factory as JobFactory;

interface TranslationServiceInterface
{
    /**
     * Fetch order from service and update order model accordingly
     * @param  \acclaro\translationsforcraft\services\job\Factory $jobFactory
     * @param  \acclaro\translationsforcraft\elements\Order  $order
     * @return void
     */
    public function updateOrder(JobFactory $jobFactory, Order $order);

    /**
     * Fetch file from service and update file model accordingly
     * @param  \acclaro\translationsforcraft\services\job\Factory $jobFactory
     * @param  \acclaro\translationsforcraft\elements\Order  $order
     * @param  \acclaro\translationsforcraft\models\FileModel   $file
     * @return void
     */
    public function updateFile(JobFactory $jobFactory, Order $order, FileModel $file);

    /**
     * Validate authentication credentials
     * @return boolean
     */
    public function authenticate();

    /**
     * Send order to service and update order model accordingly
     * @param  \acclaro\translationsforcraft\elements\Order $order
     * @return void
     */
    public function sendOrder(Order $order);
}