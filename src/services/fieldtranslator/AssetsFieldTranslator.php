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

use Craft;
use Exception;
use craft\base\Field;
use craft\base\Element;
use acclaro\translations\Translations;
use acclaro\translations\services\ElementTranslator;

class AssetsFieldTranslator extends GenericFieldTranslator
{
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite=null)
    {
        $source = array();

        $blocks = $element->getFieldValue($field->handle)->siteId($sourceSite)->all();

        if ($blocks)
        {
            foreach ($blocks as $block)
            {
                $source[sprintf('%s.%s.%s', $field->handle, $block->id, 'title')] = $block->title;

                $element = Craft::$app->assets->getAssetById($block->id, $sourceSite);
                foreach ($element->getFieldLayout()->getCustomFields() as $layoutField) {
                    $assetField = Craft::$app->fields->getFieldById($layoutField->id);
                    $fieldSource = $elementTranslator->fieldToTranslationSource($element, $assetField, $sourceSite);

                    foreach ($fieldSource as $key => $value) {
                        $k = sprintf('%s.%s.%s', $field->handle, $block->id, $key);
                        $source[$k] = $value;
                    }
                }
            }
        }
        return $source;
    }

    public function toPostArray(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite=null)
    {
        $fieldHandle = $field->handle;

        $blocks = $element->getFieldValue($fieldHandle)->siteId($sourceSite)->all();


        $post[$fieldHandle] = [];

        if (!$blocks)
        {
            return '';
        }

        foreach ($blocks as $i => $block)
        {
            $post[$fieldHandle][$block->id] = $block->id;
        }
        return $post;
    }

    public function toPostArrayFromTranslationTarget(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite, $targetSite, $fieldData)
    {
        $postArray = [];

        $fieldHandle = $field->handle;

        $blocks = $element->getFieldValue($fieldHandle)->siteid($sourceSite)->all();

        $title = '';

        foreach ($blocks as $i => $block)
        {
            try{
                $i = $block->id;

                if (isset($fieldData[$i]['title'])) {
                    $title = $fieldData[$i]['title'];
                }

                $element = Craft::$app->assets->getAssetById($block->id, $targetSite);
                $assetFields = $element->getFieldValues();

                $post = [];
                $element->siteId = $targetSite;
                foreach ($assetFields as $assetField) {
                    $blockData = isset($fieldData[$i]) ? $fieldData[$i] : array();
                    $post['fields'] = $elementTranslator->toPostArrayFromTranslationTarget($block, $sourceSite, $targetSite, $blockData, true);
                }

                if ($title || !empty($post['fields'])) {
                    $element->title = ($title) ? $title : $element->title;
                    !empty($post['fields']) ? $element->setFieldValues($post['fields']) : '';
                    Translations::$plugin->draftRepository->saveDraft($element);
                }

                $postArray[$fieldHandle][$block->id] = $block->id;
            } catch (Exception $e) {
                continue;
            }
        }

        return $postArray;
    }

    public function getWordCount(ElementTranslator $elementTranslator, Element $element, Field $field)
    {

        $blocks = $this->getFieldValue($elementTranslator, $element, $field)->all();

        if (!$blocks) {
            return 0;
        }

        $wordCount = 0;

        foreach ($blocks as $i => $block) {
            $wordCount += $elementTranslator->getWordCount($block);
        }

        return $wordCount;
    }
}
