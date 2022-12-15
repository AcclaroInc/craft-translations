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

use acclaro\translations\Constants;

class Factory
{
    private $supportedServices = [
        Constants::TRANSLATOR_ACCLARO => AcclaroTranslationService::class,
        Constants::TRANSLATOR_DEFAULT => Export_ImportTranslationService::class,
        Constants::TRANSLATOR_GOOGLE  => GoogleTranslationService::class
    ];

    /**
     * @return AcclaroTranslationService|Export_ImportTranslationService|GoogleTranslationService
     */
    public function makeTranslationService($serviceHandle, $settings)
    {
        if (!array_key_exists($serviceHandle, $this->supportedServices)) {
            throw new \Exception('Invalid translation service.');
        }
        
        $class = $this->supportedServices[$serviceHandle];
        
        return (new $class($settings));
    }
}