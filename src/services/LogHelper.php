<?php

/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services;

use acclaro\translations\Constants;
use acclaro\translations\Translations;
use Craft;


class LogHelper
{
    private static $loggingEnabled;

    public function __construct(){
        self::$loggingEnabled = Translations::getInstance()->settings->apiLogging;
    }
    public function log($info, $level) {
        if (self::$loggingEnabled) {
        Craft::$level($info, Constants::PLUGIN_HANDLE);
        }
    }
}