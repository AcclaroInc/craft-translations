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
use craft\base\Field;
use craft\base\Element;
use benf\neo\elements\Block;
use verbb\supertable\SuperTable;
use craft\elements\db\ElementQuery;
use acclaro\translations\services\App;
use acclaro\translations\Translations;
use acclaro\translations\services\ElementTranslator;

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
                    if (is_array($block)) {
                        foreach ($block as $key => $elem) {
                            $blockSource = $elementTranslator->toTranslationSource($elem);
                            foreach ($blockSource as $key => $value) {
                                $key = sprintf('%s.%s.%s', $field->handle, $elem->id, $key);
                                
                                $source[$key] = $value;
                            }
                        }
                    } else {
                        $blockSource = $elementTranslator->toTranslationSource($block);
                        foreach ($blockSource as $key => $value) {
                            $key = sprintf('%s.%s.%s', $field->handle, $block->id, $key);
                            
                            $source[$key] = $value;
                        }
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

        $new = 0;
        foreach ($blocks as $i => $block) {
          if (!$block instanceof ElementQuery) {
              if (is_array($block)) {
                  $n = 0;
                  foreach ($block as $key => $elem) {
                    $blockId = $elem->id ?? 'new' . ++$new;
                    $blockData = isset($fieldData[$n]) ? $fieldData[$n] : array();
                    $post[$fieldHandle][$blockId] = array(
                      'type' => $blockType->id,
                      'fields' => $elementTranslator->toPostArrayFromTranslationTarget($elem, $sourceLanguage, $targetLanguage, $blockData, true),
                    );
                    $n++;
                  }
              } else {
                $blockData = isset($fieldData[$new]) ? $fieldData[$new] : array();
                $blockId = $block->id ?? 'new' . ++$new;
                $post[$fieldHandle][$blockId] = array(
                  'type' => $blockType->id,
                  'fields' => $elementTranslator->toPostArrayFromTranslationTarget($block, $sourceLanguage, $targetLanguage, $blockData, true),
                );
              }
          } else {
            $blockElem = $element->getFieldValue($fieldHandle);
            foreach ($blockElem as $key => $block) {
              $blockId = $block->id ?? 'new' . ++$new;
              $blockData = isset($fieldData[$key]) ? $fieldData[$key] : array();
              $post[$fieldHandle][$blockId] = array(
                'type' => $blockType->id,
                'fields' => $elementTranslator->toPostArrayFromTranslationTarget($block, $sourceLanguage, $targetLanguage, $blockData, true),
              );
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
                    if (is_array($block)) {
                        foreach ($block as $key => $elem) {
                            $wordCount += $elementTranslator->getWordCount($elem);
                        }
                    } else {
                        $wordCount += $elementTranslator->getWordCount($block);
                    }
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
