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

class SeomaticMetaFieldTranslator extends GenericFieldTranslator
{
    private $translatableAttributes = array('seoTitle', 'seoDescription', 'seoKeywords');

    /**
     * {@inheritdoc}
     */
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $source = array();

        $meta = $element->getFieldValue($field->handle);

        if ($meta) {
            foreach ($this->translatableAttributes as $attribute) {
                $value = $meta->metaGlobalVars->$attribute;

                if ($value) {
                    $key = sprintf('%s.%s', $field->handle, $attribute);

                    $source[$key] = $value;
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

        $meta = $element->getFieldValue($fieldHandle);

        $source[$fieldHandle] = $field->serializeValue($meta);

        return $source;
    }

    /**
     * {@inheritdoc}
     */
    public function toPostArrayFromTranslationTarget(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceLanguage, $targetLanguage, $fieldData)
    {
        $fieldHandle = $field->handle;

        $post = $this->toPostArray($elementTranslator, $element, $field);

        foreach ($this->translatableAttributes as $attribute) {
            if (isset($fieldData[$attribute])) {
                $post[$fieldHandle]['metaGlobalVars'][$attribute] = $fieldData[$attribute];
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

        $meta = $element->getFieldValue($field->handle);

        $attributes = array('seoTitle', 'seoDescription', 'seoKeywords');

        if ($meta) {
            foreach ($attributes as $attribute) {
                $value = $meta->metaGlobalVars->$attribute;

                $wordCount += Translations::$plugin->wordCounter->getWordCount($value);
            }
        }

        return $wordCount;
    }
}