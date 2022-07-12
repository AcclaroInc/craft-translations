<?php

namespace acclaro\translations\base;

use acclaro\translations\services\TranslationsService;

use Craft;
use craft\log\MonologTarget;

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
            'service' => TranslationsService::class,
        ]);
    }

    private function _setLogging()
    {
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => 'translations',
            'allowLineBreaks' => true
        ]);
    }

    public static function log($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'translations');
    }

    public static function error($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'translations');
    }

}