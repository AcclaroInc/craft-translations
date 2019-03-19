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
use craft\elements\Tag;
use craft\elements\db\ElementQuery;
use acclaro\translationsforcraft\TranslationsForCraft;

class TagRepository
{
    public function find($attributes = null)
    {
        return Tag::find()
                ->id($attributes['id'])
                ->groupId($attributes['groupId'])
                ->siteId($attributes['siteId'])
                ->one();
    }

    public function saveTag(Tag $tag)
    {
        $success = Craft::$app->elements->saveElement($tag, true, false);
        if (!$success) {
            Craft::error('Couldn’t save the tag "'.$tag->title.'"', __METHOD__);
        }
    }
}