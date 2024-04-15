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

class TagFieldTranslator extends TaxonomyFieldTranslator
{
    public function translateRelated(ElementTranslator $elementTranslator, Element $element, $existingTag, $sourceSite, $targetSite, $fieldData)
    {
        $tag = Translations::$plugin->tagRepository->getTagById($existingTag->id, $targetSite);

        $tag->siteId = $targetSite;

        if (isset($fieldData['title'])) {
            $tag->title = $fieldData['title'];
        }

        if (isset($fieldData['slug'])) {
            $tag->slug = $fieldData['slug'];
        }
        
        $post = $elementTranslator->toPostArrayFromTranslationTarget($tag, $sourceSite, $targetSite, $fieldData);
        
        $tag->setFieldValues($post, mt_rand(10000, 99999));

        Translations::$plugin->tagRepository->saveTag($tag);

        return $tag->id;
    }
}