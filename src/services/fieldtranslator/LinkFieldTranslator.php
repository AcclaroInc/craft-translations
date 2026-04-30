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
use craft\base\Field;
use craft\fields\data\LinkData;
use acclaro\translations\Translations;
use acclaro\translations\services\ElementTranslator;

class LinkFieldTranslator extends GenericFieldTranslator
{
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $fieldData = $this->getFieldValue($elementTranslator, $element, $field);

        if (!$fieldData instanceof LinkData) {
            return [];
        }

        $serialized = $fieldData->serialize();

        if (!isset($serialized['label']) || $serialized['label'] === '') {
            return [];
        }

        return [
            sprintf('%s.%s.label', $field->handle, $field->id) => $serialized['label'],
        ];
    }

    public function toPostArray(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $fieldData = $this->getFieldValue($elementTranslator, $element, $field);

        return [
            $field->handle => $fieldData instanceof LinkData ? $fieldData->serialize() : null,
        ];
    }

    public function toPostArrayFromTranslationTarget(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite, $targetSite, $fieldData)
    {
        $post = $this->toPostArray($elementTranslator, $element, $field);

        if (!is_array($post[$field->handle] ?? null)) {
            return $post;
        }

        foreach ($fieldData as $key => $row) {
            if ((string)$key !== (string)$field->id || !is_array($row)) {
                continue;
            }

            if (isset($row['label'])) {
                $post[$field->handle]['label'] = $row['label'];
            }
        }

        return $post;
    }

    public function getWordCount(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $fieldData = $this->getFieldValue($elementTranslator, $element, $field);

        if (!$fieldData instanceof LinkData) {
            return 0;
        }

        return Translations::$plugin->wordCounter->getWordCount(strip_tags($fieldData->getLabel(true) ?? ''));
    }
}