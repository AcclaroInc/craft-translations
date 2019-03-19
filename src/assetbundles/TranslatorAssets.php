<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class TranslatorAssets extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = '@acclaro/translationsforcraft/assetbundles/src';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/TranslatorDetail.js',
        ];

        $this->css = [
        ];

        parent::init();
    }
}