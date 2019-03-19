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
use acclaro\translationsforcraft\TranslationsForCraft;
use acclaro\translationsforcraft\services\api\AcclaroApiClient;
use acclaro\translationsforcraft\services\translation\AcclaroTranslationService;
use acclaro\translationsforcraft\services\translation\Export_ImportTranslationService;

class Factory
{
    private $translationServices = array(
        'acclaro' => 'Acclaro',
        'export_import' => 'Export_Import'
    );

    public function getTranslationServiceNames()
    {
        return $this->translationServices;
    }

    public function makeTranslationService($serviceHandle, $settings)
    {
        if (!array_key_exists($serviceHandle, $this->translationServices)) {
            throw new Exception('Invalid translation service.');
        }

        $service = $serviceHandle != 'export_import' ? ucfirst($serviceHandle) : 'Export_Import';

        $class = sprintf(
            '%s\\%sTranslationService',
            __NAMESPACE__,
            $service
        );

        switch ($class) {
            case AcclaroTranslationService::class:
                return new AcclaroTranslationService(
                    $settings,
                    new AcclaroApiClient(
                        $settings['apiToken'],
                        !empty($settings['sandboxMode'])
                    )
                );
                break;
            case Export_ImportTranslationService::class:
                return new Export_ImportTranslationService(
                    $settings
                );
        }

        $class = '\\'.$class;

        return new $class($settings, Craft::$app);
    }
}