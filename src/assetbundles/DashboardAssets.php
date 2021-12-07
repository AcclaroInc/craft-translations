<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\assetbundles;

use craft\web\View;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use acclaro\translations\Constants;
/**
 * Asset bundle for the Dashboard
 */
class DashboardAssets extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = Constants::URL_BASE_ASSETS;

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/Dashboard.js',
        ];

        $this->css = [
            'css/Dashboard.css',
        ];

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function registerAssetFiles($view)
    {
        parent::registerAssetFiles($view);
        if ($view instanceof View) {
            $view->registerTranslations('app', [
                '1 column',
                '{num} columns',
                '{type} Settings',
                'Widget saved.',
                'Couldn’t save widget.',
                'You don’t have any widgets yet.',
            ]);
        }
    }
}