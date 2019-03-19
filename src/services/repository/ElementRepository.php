<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\services\repository;

use Craft;
use Exception;
use acclaro\translationsforcraft\TranslationsForCraft;

class ElementRepository
{
    public function getElementById($element, $siteId)
    {
        return Craft::$app->elements->getElementById($element, $siteId);
    }
}