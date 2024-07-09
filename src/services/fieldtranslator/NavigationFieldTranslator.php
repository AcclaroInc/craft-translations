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

use craft\base\Field;
use craft\base\Element;
use acclaro\translations\Translations;
use acclaro\translations\services\ElementTranslator;
use craft\elements\Entry;

class NavigationFieldTranslator extends GenericFieldTranslator
{
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite = null)
    {
        $source = array();
        $handlenav = $this->getFieldValue($elementTranslator, $element, $field);

        if (! $handlenav) {
            return $source;
        }

        $fieldData = \verbb\navigation\elements\Node::find()
            ->handle($handlenav)
            ->all();

    
        if ($fieldData) {
            foreach ($fieldData as $key => $value) {
                $parent = sprintf('%s.%s.%s.%s', $handlenav, $field->id, $key, 'level' . $value->level);
                $source[$parent] = $value->title;
    
                $fieldDataSerializedValue = $fieldData[$key]->getSerializedFieldValues();
    
                if ($fieldDataSerializedValue) {
                    $this->addFieldsToSource($source, $fieldDataSerializedValue, $parent);
                }
            }
        }
    
        return $source;
    }
    
    private function addFieldsToSource(&$source, $data, $parent)
    {
        foreach ($data as $childKey => $childValue) {
            $child = sprintf('%s.%s', $parent, $childKey);
            if (is_string($childValue)) {
                $source[$child] = $childValue;
            } elseif (is_array($childValue)) {
                // Recursively process the array
                $this->addFieldsToSource($source, $childValue, $child);
            }
        }
    }

    public function getWordCount(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $handlenav = $this->getFieldValue($elementTranslator, $element, $field);

        if (!$handlenav) {
            return 0;
        }

        $blocks = \verbb\navigation\elements\Node::find()
            ->handle($handlenav)
            ->all();
        if (!$blocks) {
            return 0;
        }
        $wordCount = 0;
    
        foreach ($blocks as $block) {
            // Count words in the block title
            $wordCount += Translations::$plugin->wordCounter->getWordCount($block->title);
            
            $fieldDataSerializedValue = $block->getSerializedFieldValues();
    
            if ($fieldDataSerializedValue) {
                // Recursively count words in all string fields
                $wordCount += $this->countWordsInFields($fieldDataSerializedValue);
            }
        }
    
        return $wordCount;
        
    }

    private function countWordsInFields($data)
    {
        $wordCount = 0;
    
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $wordCount += Translations::$plugin->wordCounter->getWordCount($value);
            } elseif (is_array($value)) {
                $wordCount += $this->countWordsInFields($value);
            }
        }
    
        return $wordCount;
    }
    

    /**
     * {@inheritdoc}
     */
    public function toPostArray(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $fieldHandle = $field->handle;

        $blocks = $element->getFieldValue($fieldHandle);

        if (!$blocks) {
            return [];
        }

        $post = array(
            $fieldHandle => array(),
        );

        $post[$fieldHandle] = array(
            'field_handle' => $blocks,
        );
        return $post;
    }
}
