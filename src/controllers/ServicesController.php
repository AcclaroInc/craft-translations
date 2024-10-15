<?php
/**
 * Translations for Craft plugin for Craft CMS 5.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\controllers;

use acclaro\translations\Constants;
use craft\web\Controller;

class ServicesController extends Controller
{
    public function actionIndex()
    {
        $this->requireLogin();
        $variables = array();
        $servicesData = Constants::SERVICES_CONTENT;

        $variables['servicesData']  = $servicesData;     
        $variables['selectedSubnavItem'] = 'services';       

        return $this->renderTemplate('translations/services/_index', $variables);
    }
}