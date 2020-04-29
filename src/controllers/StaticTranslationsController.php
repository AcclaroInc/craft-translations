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
use acclaro\translations\Translations;
use acclaro\translations\assetbundles\StaticTranslationsAssets;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class StaticTranslationsController extends Controller
{
    /**
     * @return mixed
     */
    public function actionIndex() {

        $variables = [];
        $variables['selectedSubnavItem'] = 'static-translations';

        $this->requireLogin();
        $this->renderTemplate('translations/static-translations/index', $variables);
    }

    /**
     * @return \yii\web\Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSave() {

        $this->requirePostRequest();

        $siteId = Craft::$app->request->getRequiredBodyParam('siteId');
        $site = Craft::$app->getSites()->getSiteById($siteId);
        $lang = $site->language;
        $translations = Craft::$app->request->getRequiredBodyParam('translation');

        Translations::$plugin->staticTranslationsRepository->set($lang, $translations);

        return $this->asJson([
            'success' => true,
            'errors' => []
        ]);
    }
}