<?php

/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\fieldtranslator;

use craft\base\Element;
use craft\elements\Category;
use acclaro\translations\Translations;
use acclaro\translations\services\ElementTranslator;

class CategoryFieldTranslator extends TaxonomyFieldTranslator
{
    public function translateRelated(ElementTranslator $elementTranslator, Element $element, Category $category, $sourceSite, $targetSite, $fieldData)
    {
        // search for existing translated category in the same group
        $translatedCategory = Translations::$plugin->categoryRepository->find(array(
            'groupId' => $category->groupId,
            'siteId' => $targetSite,
        ));

        if ($translatedCategory) {
            return $translatedCategory->id;
        }

        $translatedCategory = Translations::$plugin->categoryRepository->find(array(
            'id' => $category->id,
            'groupId' => $category->groupId,
            'siteId' => $targetSite,
        ));

        if ($translatedCategory) {
            $category = $translatedCategory;
        }

        $category->siteId = $targetSite;

        if (isset($fieldData['title'])) {
            $category->title = $fieldData['title'];
        }

        if (isset($fieldData['slug'])) {
            $category->slug = $fieldData['slug'];
        }

        $post = $elementTranslator->toPostArrayFromTranslationTarget($category, $sourceSite, $targetSite, $fieldData);

        $category->setFieldValues($post);

        Translations::$plugin->categoryRepository->saveCategory($category);

        return $category->id;
    }
}
