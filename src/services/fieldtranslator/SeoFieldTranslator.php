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

class SeoFieldTranslator extends GenericFieldTranslator
{
    private $translatableAttributes = ['titleRaw', 'descriptionRaw', 'keywords', 'social'];

    /**
     * {@inheritdoc}
     */
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $source = array();

        $meta = $element->getFieldValue($field->handle);

        if ($meta) {
            foreach ($this->translatableAttributes as $attribute) {

                if (is_array($meta->$attribute)) {
                    
                    foreach ($meta->$attribute as $key => $val){
                        if($attribute == 'social')
                        {
                            $new_key = sprintf('%s.%s.%s.%s', $field->handle, $attribute, $key, 'title');
                            $source[$new_key] = $val->title;

                            $new_key = sprintf('%s.%s.%s.%s', $field->handle, $attribute, $key, 'description');
                            $source[$new_key] = $val->description;
                        } else if($attribute == 'keywords') {
                            $new_key = sprintf('%s.%s.%s.%s', $field->handle, $attribute, $key,'keyword');
                            $source[$new_key] = $val['keyword'];
                        } else {
                            $key = sprintf('%s.%s.%s', $field->handle, $attribute, $key);
                            $source[$key] = $val;
                        }
                    }

                } else {
                    if ($meta->$attribute) {
                        $key = sprintf('%s.%s', $field->handle, $attribute);

                        $source[$key] = $meta->$attribute;
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

        $meta = $element->getFieldValue($fieldHandle);

        $source = array();

        if( $meta )
        {
            foreach($meta as $key => $value)
            {
                $source[$fieldHandle][$key] = $value;
            }
        }

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
                $post[$fieldHandle][$attribute] = $fieldData[$attribute];
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

        if ($meta) {
            foreach ($this->translatableAttributes as $attribute) {
                $value = $meta->$attribute;

                if (is_array($value)) {
                    foreach ($value as $key => $val){
                        if($attribute == 'social')
                        {
                            $wordCount += Translations::$plugin->wordCounter->getWordCount($val->title);
                            $wordCount += Translations::$plugin->wordCounter->getWordCount($val->description);
                        } else {
                            $val = is_array($val) ? $val['keyword'] : $val;
                            $wordCount += Translations::$plugin->wordCounter->getWordCount($val);
                        }
                    }
                } else {
                    $wordCount += Translations::$plugin->wordCounter->getWordCount($value);
                }

            }
        }

        return $wordCount;
    }
}
