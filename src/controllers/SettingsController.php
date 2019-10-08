<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\controllers;

use Craft;
use craft\web\Controller;
use acclaro\translations\services\App;
use acclaro\translations\Translations;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class SettingsController extends Controller
{
    /**
     * @return mixed
     */
    public function actionIndex()
    {
        $variables = array();

        $this->renderTemplate('translations/settings/index', $variables);
    }
}