<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\translation;

use Craft;
use Exception;
use acclaro\translations\services\App;
use acclaro\translations\elements\Order;
use acclaro\translations\models\FileModel;
use acclaro\translations\Translations;
use acclaro\translations\services\job\Factory as JobFactory;

interface TranslationServiceInterface
{
    /**
     * Fetch order from service and update order model accordingly
     * @param  \acclaro\translations\services\job\Factory $jobFactory
     * @param  \acclaro\translations\elements\Order  $order
     * @return void
     */
    public function updateOrder(JobFactory $jobFactory, Order $order);

    /**
     * Fetch file from service and update file model accordingly
     * @param  \acclaro\translations\services\job\Factory $jobFactory
     * @param  \acclaro\translations\elements\Order  $order
     * @param  \acclaro\translations\models\FileModel   $file
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
     * @param  \acclaro\translations\elements\Order $order
     * @return void
     */
    public function sendOrder(Order $order);
}