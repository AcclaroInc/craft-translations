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

use Craft;
use acclaro\translations\Constants;
use acclaro\translations\Translations;


class LogHelper
{
    public static $loggingEnabled;

    public function __construct(){
        self::$loggingEnabled = Translations::getInstance()->settings->apiLogging;
    }

    /**
     * Create a log stack in translation.log file if logging is enabled
     *
     * @param string $errorMessage
     * @param string $level the type of log (error, info, debug, warning)
     *
     * @return void
     */
    public function log($message, $level) {
        if (self::$loggingEnabled) {
            Craft::$level($message, Constants::PLUGIN_HANDLE);
        }
    }
}