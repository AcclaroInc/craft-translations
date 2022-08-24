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
use craft\base\Component;
use craft\elements\Category;
use acclaro\translations\Translations;
use acclaro\translations\Constants;

class CategoryRepository extends Component
{
    public function find($attributes = null)
    {
        return Category::find()
            ->siteId($attributes['siteId'])
            ->groupId($attributes['groupId'])
            ->one();
    }

    public function getCategoryById($id, $site = null)
    {
        return Craft::$app->getCategories()->getCategoryById($id, $site);
    }

    public function getDraftById($draftId, $siteId)
    {
        return Category::find()
            ->draftId($draftId)
            ->siteId($siteId)
            ->status(null)
            ->one();
    }

    public function saveCategory(Category $category)
    {
        $success = Craft::$app->elements->saveElement($category);
        if (!$success) {
            Translations::$plugin->logHelper->log( '['. __METHOD__ .'] Couldn’t save the category "'.$category->title.'"', Constants::LOG_LEVEL_ERROR );
        }
    }
}
