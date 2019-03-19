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

class OrderAssets extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = '@acclaro/translations/assetbundles/src';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/OrderDetail.js',
            'js/OrderEntries.js',
            'js/OrderIndex.js',
            'js/ExportFiles.js',
            'js/ImportFiles.js',
            'js/SyncOrders.js',
            'js/SyncOrder.js'
        ];

        $this->css = [
        ];

        parent::init();
    }
}