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
use craft\base\Element;
use craft\base\Field;
use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\services\ElementTranslator;

class ContentBlockFieldTranslator extends GenericFieldTranslator
{
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite = null)
    {
        $contentBlock = $this->getContentBlock($element, $field);

        if (!$contentBlock) {
            return [];
        }

        $source = [];

        foreach ($this->getChildFields($contentBlock) as $childField) {
            $translator = Translations::$plugin->fieldTranslatorFactory->makeTranslator($childField);

            if (!$translator || !$this->shouldTranslateChildField($contentBlock, $childField)) {
                continue;
            }

            $fieldSource = $translator->toTranslationSource($elementTranslator, $contentBlock, $childField, $sourceSite);

            if (!is_array($fieldSource)) {
                $fieldSource = [$childField->handle => $fieldSource];
            }

            foreach ($fieldSource as $key => $value) {
                $source[sprintf('%s.%s', $field->handle, $key)] = $value;
            }
        }

        return $source;
    }

    public function toPostArray(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $contentBlock = $this->getContentBlock($element, $field);

        if (!$contentBlock) {
            return [
                $field->handle => [
                    'fields' => [],
                ],
            ];
        }

        $fields = [];

        foreach ($this->getChildFields($contentBlock) as $childField) {
            $translator = Translations::$plugin->fieldTranslatorFactory->makeTranslator($childField);

            if (!$translator || !$this->shouldTranslateChildField($contentBlock, $childField)) {
                continue;
            }

            $fieldPost = $translator->toPostArray($elementTranslator, $contentBlock, $childField);

            if (!is_array($fieldPost)) {
                $fieldPost = [$childField->handle => $fieldPost];
            }

            $fields = array_merge($fields, $fieldPost);
        }

        return [
            $field->handle => [
                'fields' => $fields,
            ],
        ];
    }

    public function toPostArrayFromTranslationTarget(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite, $targetSite, $fieldData)
    {
        $contentBlock = $this->getContentBlock($element, $field);

        if (!$contentBlock) {
            return [
                $field->handle => [
                    'fields' => [],
                ],
            ];
        }

        $fields = [];

        foreach ($this->getChildFields($contentBlock) as $childField) {
            $translator = Translations::$plugin->fieldTranslatorFactory->makeTranslator($childField);

            if (!$translator || !$this->shouldTranslateChildField($contentBlock, $childField)) {
                continue;
            }

            if (isset($fieldData[$childField->handle])) {
                $fieldPost = $translator->toPostArrayFromTranslationTarget(
                    $elementTranslator,
                    $contentBlock,
                    $childField,
                    $sourceSite,
                    $targetSite,
                    $fieldData[$childField->handle]
                );
            } else {
                $fieldPost = $translator->toPostArray($elementTranslator, $contentBlock, $childField);
            }

            if (!is_array($fieldPost)) {
                $fieldPost = [$childField->handle => $fieldPost];
            }

            $fields = array_merge($fields, $fieldPost);
        }

        return [
            $field->handle => [
                'fields' => $fields,
            ],
        ];
    }

    public function getWordCount(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $contentBlock = $this->getContentBlock($element, $field);

        if (!$contentBlock) {
            return 0;
        }

        $wordCount = 0;

        foreach ($this->getChildFields($contentBlock) as $childField) {
            $translator = Translations::$plugin->fieldTranslatorFactory->makeTranslator($childField);

            if ($translator && $this->shouldTranslateChildField($contentBlock, $childField)) {
                $wordCount += $translator->getWordCount($elementTranslator, $contentBlock, $childField);
            }
        }

        return $wordCount;
    }

    private function getContentBlock(Element $element, Field $field)
    {
        try {
            return $element->getFieldValue($field->handle);
        } catch (\Exception $e) {
            Translations::$plugin->logHelper->log(
                '[' . __METHOD__ . "] {$field->handle} not found.",
                Constants::LOG_LEVEL_ERROR
            );
            return null;
        }
    }

    private function getChildFields(Element $contentBlock): array
    {
        $fields = [];

        foreach ($contentBlock->getFieldLayout()->getCustomFields() as $layoutField) {
            $field = Craft::$app->fields->getFieldById($layoutField->id);

            if (!$field) {
                continue;
            }

            $field->handle = $layoutField->handle;
            $fields[] = $field;
        }

        return $fields;
    }

    private function shouldTranslateChildField(Element $contentBlock, Field $childField): bool
    {
        return $childField->getIsTranslatable($contentBlock)
            || in_array(get_class($childField), Constants::NESTED_FIELD_TYPES, true);
    }
}