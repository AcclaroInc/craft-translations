<?php
/**
 * Translations for Craft plugin for Craft CMS 5.x
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

class TableMakerFieldTranslator extends GenericFieldTranslator
{
    /**
     * {@inheritdoc}
     */
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $source = array();

        $data = $element->getFieldValue($field->handle);

        $columnsData = $data['columns'];
        $columns = $this->getTableColumns($columnsData);

        foreach ($data['rows'] as $i => $row) {
            foreach ($columns as $id => $name) {
                $columnKey = sprintf('%s.%s.%s', $field->handle, 'columnTitle', $id);
                $source[$columnKey] = $name;

                $key = sprintf('%s.%s.%s', $field->handle, $i, $name);

                // Check to translate dropdown lable not values
                if ($this->isColumnDropdown($columnsData[$id])) {
                    $source[$key] = $columnsData[$id]['options'][$i]['label'];
                } else {
                    $source[$key] = isset($row[$id]) ? $row[$id] : '';
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

        $post = $this->toPostArray($elementTranslator, $element, $field);
        foreach ($fieldData as $i => $row) {
            if ($i === 'columnTitle') {
                foreach ($row as $index => $columnTitle) {
                    $post[$fieldHandle]['columns'][$index]['heading'] = $columnTitle;
                }
            } else if (isset($post[$fieldHandle]['rows'][$i])) {
                // Custom logic to replace the dropdown lable in place of value
                $this->handleDropdownValues($post, $row, $fieldHandle, $i);
                $post[$fieldHandle]['rows'][$i] = array_values($row);
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

        $data = $element->getFieldValue($field->handle);

        $columns = $this->getTableColumns($data['columns']);
        
        foreach ($data['rows'] as $i => $row) {
            foreach ($columns as $id => $name) {
                $value = $row[$id] ?? "";

                $wordCount += Translations::$plugin->wordCounter->getWordCount($value);
            }
        }

        return $wordCount;
    }

    private function getTableColumns($columnsData)
    {
        $columns = [];

        foreach ($columnsData as $info) {
            array_push($columns, $info['heading']);
        }

        return $columns;
    }

    private function isColumnDropdown($columnsData)
    {
        return $columnsData['type'] === 'select';
    }

    private function handleDropdownValues(&$post, &$row, $fieldHandle, $i)
    {
        $columnsData = $post[$fieldHandle]['columns'];
        $columns = $this->getTableColumns($columnsData);

        foreach ($columns as $id => $name) {
            if ($this->isColumnDropdown($columnsData[$id])) {
                foreach ($post[$fieldHandle]['columns'][$id]['options'] as $index => $dd) {
                    if ($dd['value'] === $post[$fieldHandle]['rows'][$i][$id]) {
                        $post[$fieldHandle]['columns'][$id]['options'][$index]['label'] = $row[$name];
                        $row[$name] = $post[$fieldHandle]['rows'][$i][$id];
                    }
                }
            }
        }
    }
}