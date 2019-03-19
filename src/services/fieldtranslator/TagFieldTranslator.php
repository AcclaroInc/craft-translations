<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\services\fieldtranslator;

use Craft;
use craft\base\Field;
use craft\base\Element;
use craft\elements\Tag;
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\TranslationsForCraft;
use acclaro\translationsforcraft\services\ElementTranslator;

class TagFieldTranslator extends TaxonomyFieldTranslator
{
    public function translateRelated(ElementTranslator $elementTranslator, Element $element, Tag $existingTag, $sourceSite, $targetSite, $fieldData)
    {
        $translatedTag = TranslationsForCraft::$plugin->tagRepository->find(array(
            'id' => $existingTag->id,
            'groupId' => $existingTag->groupId,
            'siteId' => $existingTag->siteId
        ));
        
        if ($translatedTag) {
            $tag = $translatedTag;
        } else {
            $tag = TranslationsForCraft::$plugin->elementCloner->cloneElement($existingTag);
        }

        $tag->siteId = $targetSite;

        if (isset($fieldData['title'])) {
            $tag->title = $fieldData['title'];
        }

        if (isset($fieldData['slug'])) {
            $tag->slug = $fieldData['slug'];
        }
        
        $post = $elementTranslator->toPostArrayFromTranslationTarget($tag, $sourceSite, $targetSite, $fieldData);
        
        $tag->setFieldValues($post, mt_rand(10000, 99999));

        TranslationsForCraft::$plugin->tagRepository->saveTag($tag);

        return $tag->id;
    }
}