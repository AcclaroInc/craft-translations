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
use Exception;
use craft\elements\Category;
use craft\elements\db\ElementQuery;
use craft\elements\db\CategoryQuery;
use acclaro\translations\Translations;

class CategoryRepository
{
    public function find($attributes = null)
    {
        return Category::find()
                ->siteId($attributes['siteId'])
                ->groupId($attributes['groupId'])
                // ->slug($attributes['slug'])
                ->one();
    }

    public function getCategoryById($id, $site=null)
    {
        return Craft::$app->getCategories()->getCategoryById($id, $site);;
    }

    public function saveCategory(Category $category)
    {
        $success = Craft::$app->elements->saveElement($category);
        if (!$success) {
            Craft::error('Couldn’t save the category "'.$category->title.'"', __METHOD__);
        }
    }
}
