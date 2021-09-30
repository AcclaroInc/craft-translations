<?php

namespace acclaro\translations\services;

use acclaro\translations\services\api\AcclaroApiClient;
use Craft;
use acclaro\translations\Translations;

/**
 * Acclaro Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Acclaro
 * @package   Translations
 */
class AcclaroService
{
    /**
     * Authenticate Acclaro API Token
     *
     * @param [string] $service
     * @param [string] $settings
     * @return void
     */
    public function authenticateService($service, $settings)
    {
        $translator = Translations::$plugin->translatorRepository->makeNewTranslator();
        $translator->service = $service;
        $translator->settings = json_encode($settings);
        
        $translationService = Translations::$plugin->translatorFactory->makeTranslationService($service, $settings);

        return $translationService->authenticate($settings);
    }
}
