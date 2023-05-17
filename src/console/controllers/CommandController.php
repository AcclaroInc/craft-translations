<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\console\controllers;

use acclaro\translations\Translations;
use acclaro\translations\services\api\ChatGPTApiClient as Client;
use acclaro\translations\services\translator\ChatGPTTranslationService as Service;

use yii\console\Controller;

/**
 * Command Command
 *
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class CommandController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Handle translations/command console commands
     *
     * @return mixed
     */
    public function actionIndex()
    {

        $order = Translations::$plugin->orderRepository->getOrderById(3097095);
        echo json_encode($order) . "\n";
        $translationService = $order->getTranslationService();
        $files = $order->getFiles();
        $translationService->syncOrder($order, [145]); 

        return true;
    }

    /**
     * Handle translations/command/do-something console commands
     *
     * @return mixed
     */
    public function actionDoSomething()
    {
        $result = 'something';

        echo "Welcome to the console CommandController actionDoSomething() method!!!!!\n";

        return $result;
    }
}
