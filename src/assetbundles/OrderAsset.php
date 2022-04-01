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

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use acclaro\translations\Constants;

class OrderAsset extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = Constants::URL_BASE_ASSETS;

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/OrderDetails.js',
            'js/OrderEntries.js',
            'js/ExportFiles.js',
            'js/ImportFiles.js',
            'js/diff2html.min.js',
        ];

        $this->css = [
            'css/diff2html.min.css',
        ];

        parent::init();
    }
}
