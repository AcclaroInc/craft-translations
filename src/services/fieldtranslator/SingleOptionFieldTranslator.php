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
use craft\fields\data\SingleOptionFieldData;
use acclaro\translations\Translations;
use acclaro\translations\services\ElementTranslator;

class SingleOptionFieldTranslator extends GenericFieldTranslator
{
    /**
     * {@inheritdoc}
     */
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $fieldData = $element->getFieldValue($field->handle);

        if ($fieldData instanceof SingleOptionFieldData) {
            if ($fieldData->selected) {
                $key = sprintf('%s.%s', $field->handle, $fieldData->value);

                return array($key => $fieldData->label);
            }

            return array();
        }

        $settings = $field->settings;

        foreach ($settings['options'] as $option) {
            if ($option['value'] === $fieldData) {
                $key = sprintf('%s.%s', $field->handle, $option['value']);

                return array($key => $option['label']);
            }
        }

        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function toPostArrayFromTranslationTarget(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite, $targetSite, $fieldData)
    {
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

                return $value;
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldValue(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $fieldData = $element->getFieldValue($field->handle);

        if ($fieldData instanceof SingleOptionFieldData) {
            $fieldData = $fieldData->selected ? $fieldData->value : '';
        }

        return $fieldData;
    }
}