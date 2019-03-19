<?php
namespace acclaro\translationsforcraft\base;

use acclaro\translationsforcraft;
use acclaro\translationsforcraft\services\TranslationsForCraftService;

use Craft;
use craft\log\FileTarget;

use yii\log\Logger;

trait PluginTrait
{
    // Static Properties
    // =========================================================================

    public static $plugin;


    // Public Methods
    // =========================================================================

    public function getService()
    {
        return $this->get('service');
    }

    private function _setPluginComponents()
    {
        $this->setComponents([
            'service' => TranslationsForCraftService::class,
        ]);
    }

    private function _setLogging()
    {
        Craft::getLogger()->dispatcher->targets[] = new FileTarget([
            'logFile' => Craft::getAlias('@storage/logs/translations-for-craft.log'),
            'categories' => ['translations-for-craft'],
        ]);
    }

    public static function log($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'translations-for-craft');
    }

    public static function error($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'translations-for-craft');
    }

}