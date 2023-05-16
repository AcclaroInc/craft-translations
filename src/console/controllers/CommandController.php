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
        /*$client = new Client('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', 'org-54kqPkGBJkyln7Mt6jog6w3c');

        $response = $client->translate(["Yes we have no bananas", "We have no bananas today", "If we did have bananas", "We would use the bananas well"], "French");
        echo json_encode($response) . "\n";
        

        $translator = Translations::$plugin->translatorRepository->getTranslatorById(2);

        $settings = $translator->getSettings();

        $service = new Service($settings);
        
        $response = $service->getTranslatedData(["Yes we have no bananas", "We have no bananas today", "If we did have bananas", "We would use the bananas well"], "English", "French");
        echo json_encode($response) . "\n";*/

        $order = Translations::$plugin->orderRepository->getOrderById(3097092);
        echo json_encode($order) . "\n";
        $translationService = $order->getTranslationService();
        $files = $order->getFiles();
        $translationService->syncOrder($order, [137]); 

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
