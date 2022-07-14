<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\repository;

use Craft;
use craft\elements\Entry;

class ElementRepository
{
    public function getElementById($elementId, $siteId = null)
    {
        return Craft::$app->getElements()->getElementById($elementId, null, $siteId);
    }

    public function getElementByDraftId($draftId, $siteId = null)
    {
        if (! $siteId) {
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        }

        return Entry::find()
            ->draftId($draftId)
            ->provisionalDrafts(false)
            ->siteId($siteId)
            ->status(null)
            ->one();
    }
}
