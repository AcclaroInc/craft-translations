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

class TableFieldTranslator extends GenericFieldTranslator
{
    /**
     * {@inheritdoc}
     */
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $source = array();

        $rows = $element->getFieldValue($field->handle);

        $settings = $field->settings;

        if ($rows) {
            foreach ($rows as $i => $row) {
                foreach ($settings['columns'] as $columnId => $column) {
                    if (!is_object($row[$columnId])) {
                        $key = sprintf('%s.%s.%s', $field->handle, $i, $column['handle']);
    
                        $source[$key] = isset($row[$columnId]) ? $row[$columnId] : '';
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

        $fieldData = $element->getFieldValue($fieldHandle);

        return $fieldData ? array($fieldHandle => $fieldData) : array();
    }

    /**
     * {@inheritdoc}
     */
    public function toPostArrayFromTranslationTarget(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite, $targetSite, $fieldData)
    {
        $fieldHandle = $field->handle;

        $settings = $field->settings;

        $post = $this->toPostArray($elementTranslator, $element, $field);

        foreach ($fieldData as $i => $row) {
            if (isset($post[$fieldHandle][$i])) {
                $postRow = array();

                foreach ($settings['columns'] as $columnId => $column) {
                    if (isset($row[$column['handle']])) {
                        $postRow[$columnId] = $row[$column['handle']];
                    }
                }

                $post[$fieldHandle][$i] = array_merge(
                    $post[$fieldHandle][$i],
                    $postRow
                );
            }
        }

        return $post;
    }

    /**
     * {@inheritdoc}
     */
    public function getWordCount(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $wordCount = 0;

        $rows = $element->getFieldValue($field->handle);

        $settings = $field->settings;
        
        if ($rows) {
            foreach ($rows as $i => $row) {
                foreach ($settings['columns'] as $columnId => $column) {
                    $value = isset($row[$columnId]) ? $row[$columnId] : '';
                    if (!is_object($value)) {
                        $wordCount += Translations::$plugin->wordCounter->getWordCount($value);
                    }
                }
            }
        }

        return $wordCount;
    }
}