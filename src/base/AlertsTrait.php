<?php

/**
 * Translations for Craft plugin for Craft CMS 4.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\base;

use Craft;
use yii\web\Response as YiiResponse;
use acclaro\translations\Translations;

/**
 * Trait AlertsTrait will be used to show notifications to user in CP
 * 
 * @author    Acclaro
 * @package   Translations
 * @since     3.1.0
 */
trait AlertsTrait
{
    /**
     * Set Error in session to notify user
     * 
     * @param string $message
     * @return void
     */
    public function setError($message)
    {
        Craft::$app->getSession()->setError($this->getErrorMessage($message));
    }

    /**
     * Set Success in session to notify user
     * 
     * @param string $message
     * @return void
     */
    public function setSuccess($message)
    {
        Craft::$app->getSession()->setSuccess($this->getSuccessMessage($message));
    }

    /**
     * Set Notice in session to notify user
     * 
     * @param string $message
     * @return void
     */
    public function setNotice($message)
    {
        $message = Translations::$plugin->translator->translate('app', $message);
        Craft::$app->getSession()->setNotice("Alert: $message");
    }

    public function getErrorMessage($message)
    {
        $message = Translations::$plugin->translator->translate('app', $message);
        return "Error: $message";
    }

    public function getSuccessMessage($message)
    {
        $message = Translations::$plugin->translator->translate('app', $message);
        return "Success: $message";
    }
}