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
use craft\redactor\Field as RedactorField;
use craft\ckeditor\Field as CkEditorField;

class GenericFieldTranslator implements TranslatableFieldInterface
{
    public function getFieldValue(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        try {
            return $element->getFieldValue($field->handle);
        } catch (\Exception $e) {
            // Added this block to handle the fields created in craft 5 like heading4, body2, etc.
            foreach ($element->getFieldValues() as $key => $value) {
                if ($key === $field->handle) {
                    return $value;
                } elseif ($key === preg_replace('/\d+$/', '', $field->handle)) {
                    $result = $value;
                }
            }
            return $result;
        }
    }

    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $fieldValue = $this->getFieldValue($elementTranslator, $element, $field);

        return $field instanceof RedactorField || $field instanceof CkEditorField ? $field->serializeValue($fieldValue, $element) : $fieldValue;
    }

    public function toPostArrayFromTranslationTarget(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite, $targetSite, $fieldData)
    {
        if ($element->siteId != $targetSite) {
            // Get element in target site so redactor links can be set for target sites using normalise
            $element = Translations::$plugin->elementRepository->getElementById($element->id, $targetSite) ?? $element;
        }

        return $field instanceof RedactorField ? $field->normalizeValue($fieldData, $element) : $fieldData;
    }

    public function toPostArray(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        return $this->getFieldValue($elementTranslator, $element, $field);
    }

    public function getWordCount(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        return Translations::$plugin->wordCounter->getWordCount($this->getFieldValue($elementTranslator, $element, $field));
    }
}
