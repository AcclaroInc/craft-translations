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
use benf\neo\elements\Block;
use verbb\supertable\SuperTable;
use craft\elements\db\ElementQuery;
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\TranslationsForCraft;
use acclaro\translationsforcraft\services\ElementTranslator;

class SuperTableFieldTranslator extends GenericFieldTranslator
{
    /**
     * {@inheritdoc}
     */
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $source = array();

        $fieldHandle = $field->handle;

        $blocks = $element->getFieldValue($fieldHandle)->all();
        
        $blocks = $blocks ? array($fieldHandle => $blocks) : array();
        
        if ($blocks) {
            foreach ($blocks as $block) {
                if (!$block instanceof ElementQuery) {
                    $blockSource = $elementTranslator->toTranslationSource($block);
                    foreach ($blockSource as $key => $value) {
                        $key = sprintf('%s.%s.%s', $field->handle, $block->id, $key);
                        
                        $source[$key] = $value;
                    }
                } else {
                    $blockElem = $element->getFieldValue($fieldHandle);
                    foreach ($blockElem as $key => $block) {
                        $blockSource = $elementTranslator->toTranslationSource($block);
                        foreach ($blockSource as $key => $value) {
                            $key = sprintf('%s.%s.%s', $field->handle, $block->id, $key);
                            
                            $source[$key] = $value;
                        }
                    }
                }
            }
        }

        return $source;
    }

    /**
     * {@inheritdoc}
     */
    public function toPostArray(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $fieldHandle = $field->handle;

        $fieldData = $element->getFieldValue($fieldHandle)->all();

        return $fieldData ? array($fieldHandle => $fieldData) : array();
    }

    /**
     * {@inheritdoc}
     */
    public function toPostArrayFromTranslationTarget(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceLanguage, $targetLanguage, $fieldData)
    {
        $fieldHandle = $field->handle;

        $blocks = $element->getFieldValue($fieldHandle)->all();

        $blocks = $blocks ? array($fieldHandle => $blocks) : array();

        $post = array(
            $fieldHandle => array(),
        );

        $fieldData = array_values($fieldData);

        $blockTypes = SuperTable::$plugin->service->getBlockTypesByFieldId($field->id);
        $blockType = $blockTypes[0]; // There will only ever be one SuperTable_BlockType

        $j = 0;
        foreach ($blocks as $i => $block) {
          if (!$block instanceof ElementQuery) {
            $blockData = isset($fieldData[$j]) ? $fieldData[$j] : array();
            $post[$fieldHandle]['new'.($j+1)] = array(
              'type' => $blockType->id,
              'fields' => $elementTranslator->toPostArrayFromTranslationTarget($block, $sourceLanguage, $targetLanguage, $blockData, true),
            );
            $j++;
          } else {
            $blockElem = $element->getFieldValue($fieldHandle);
            foreach ($blockElem as $key => $block) {
              $blockData = isset($fieldData[$key]) ? $fieldData[$key] : array();
              $post[$fieldHandle]['new'.($j+1)] = array(
                'type' => $blockType->id,
                'fields' => $elementTranslator->toPostArrayFromTranslationTarget($block, $sourceLanguage, $targetLanguage, $blockData, true),
              );
              $j++;
            }
          }
        }
        return $post;
    }

    /**
     * {@inheritdoc}
     */
    public function getWordCount(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $blocks = $this->getFieldValue($elementTranslator, $element, $field)->all();

        if (!$blocks) {
            return 0;
        }

        $wordCount = 0;

        $blocks = $blocks ? array($field->handle => $blocks) : array();
        
        if ($blocks) {
            foreach ($blocks as $i => $block) {
                if (!$block instanceof ElementQuery) {
                    $wordCount += $elementTranslator->getWordCount($block);
                } else {
                    $blockElem = $element->getFieldValue($field->handle)->all();
                    foreach ($blockElem as $key => $block) {
                        $wordCount += $elementTranslator->getWordCount($block);
                    }
                }
            }
        }

        return $wordCount;
    }
}