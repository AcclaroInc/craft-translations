<?php

namespace acclaro\translations\base;

use Craft;
use craft\log\MonologTarget;
use acclaro\translations\Translations;

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

    private function _setLogging()
    {
        if (Translations::getInstance()->settings->apiLogging) {
            Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
                'name' => 'translations',
                'allowLineBreaks' => true
            ]);
        }
    }

}