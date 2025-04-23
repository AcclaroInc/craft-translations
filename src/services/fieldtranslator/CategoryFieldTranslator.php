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
use acclaro\translations\Translations;
use acclaro\translations\services\ElementTranslator;

class CategoryFieldTranslator extends TaxonomyFieldTranslator
{
    public function translateRelated(ElementTranslator $elementTranslator, Element $element, $category, $sourceSite, $targetSite, $fieldData)
    {
        $translatedCategory = Translations::$plugin->categoryRepository->getCategoryById($category->id, $targetSite);

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
