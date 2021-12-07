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
use craft\fields\data\MultiOptionsFieldData;
use acclaro\translations\Translations;
use acclaro\translations\services\ElementTranslator;

class MultiSelectFieldTranslator extends GenericFieldTranslator
{
/**
     * {@inheritdoc}
     */
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $source = array();

        $fieldData = $element->getFieldValue($field->handle);

        if ($fieldData) {
            if ($fieldData instanceof MultiOptionsFieldData) {
                foreach ($fieldData->getOptions() as $option) {
                    if ($option->selected) {
                        $key = sprintf('%s.%s', $field->handle, $option->value);

                        $source[$key] = $option->label;
                    }
                }
            } else {
                $settings = $field->settings;

                foreach ($settings['options'] as $option) {
                    if (in_array($option['value'], $fieldData, true)) {
                        $key = sprintf('%s.%s', $field->handle, $option['value']);

                        $source[$key] = $option['label'];
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
        $fieldData = $element->getFieldValue($field->handle);

        if ($fieldData instanceof MultiOptionsFieldData) {
            $fieldData = array_map(
                function ($option) {
                    return $option->value;
                },
                array_filter(
                    $fieldData->getOptions(),
                    function ($option) {
                        return $option->selected;
                    }
                )
            );
        }

        return array(
            $field->handle => $fieldData ? $fieldData : '',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function toPostArrayFromTranslationTarget(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite, $targetSite, $fieldData)
    {
        $fieldHandle = $field->handle;

        $values = array();

        foreach ($fieldData as $value => $target) {
            $source = null;

            //check if translation already exists
            foreach ($field->settings['options'] as $option) {
                if ($option['value'] === $value) {
                    $source = $option['label'];
                    break;
                }
            }

            if ($source) {
                Translations::$plugin->translationRepository->addTranslation(
                    $sourceSite,
                    $targetSite,
                    $source,
                    $target
                );

                $values[] = $value;
            }
        }

        return array($fieldHandle => $values);
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldValue(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $fieldData = $element->getFieldValue($field->handle);

        if ($fieldData instanceof MultiOptionsFieldData) {
            $fieldData = array_map(
                function ($option) {
                    return $option->value;
                },
                array_filter(
                    $fieldData->getOptions(),
                    function ($option) {
                        return $option->selected;
                    }
                )
            );
        }

        return $fieldData;
    }

    /**
     * {@inheritdoc}
     */
    public function getWordCount(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $value = $this->getFieldValue($elementTranslator, $element, $field);

        $wordCount = 0;

        foreach ((array) $value as $v) {
            $wordCount += Translations::$plugin->wordCounter->getWordCount($v);
        }

        return $wordCount;
    }
}