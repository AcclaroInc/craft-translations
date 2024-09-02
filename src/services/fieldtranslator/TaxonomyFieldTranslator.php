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
use acclaro\translations\services\ElementTranslator;
use craft\elements\Category;
use craft\elements\Tag;

class TaxonomyFieldTranslator extends GenericFieldTranslator
{
    /**
     * {@inheritdoc}
     */
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $source = array();

        $relations = $element->getFieldValue($field->handle)->all();

        if ($relations) {
            foreach ($relations as $i => $relation) {
                foreach ($elementTranslator->toTranslationSource($relation) as $childKey => $childValue) {
                    $key = sprintf('%s.%s.%s', $field->handle, $i, $childKey);

                    $source[$key] = $childValue;
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

        try {
            $relations = $element->getFieldValue($fieldHandle)->all();
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

        if (!$relations) {
            return '';
        }

        $post = array(
            $fieldHandle => array(),
        );

        foreach ($relations as $i => $relation) {
            $post[$fieldHandle][] = $relation->id;
        }

        return $post;
    }

    /**
     * {@inheritdoc}
     */
    public function toPostArrayFromTranslationTarget(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite, $targetSite, $fieldData)
    {
        $fieldHandle = $field->handle;

        try {
            $relations = $element->getFieldValue($fieldHandle)->all();
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

        if (!$relations) {
            return array();
        }

        $post = $this->toPostArray($elementTranslator, $element, $field);

        $fieldData = array_values($fieldData);

        foreach ($relations as $i => $related) {
            if (isset($fieldData[$i])) {
                $post[$fieldHandle][$i] = $this->translateRelated($elementTranslator, $element, $related, $sourceSite, $targetSite, $fieldData[$i]);
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

        $relations = $element->getFieldValue($field->handle)->all();

        if ($relations) {
            foreach ($relations as $i => $relation) {
                $wordCount += $elementTranslator->getWordCount($relation);
            }
        }

        return $wordCount;
    }

    /**
     * To be overridden by child class
     */
    protected function translateRelated(ElementTranslator $elementTranslator, Element $element, Tag|Category $category, $sourceSite, $targetSite, $fieldData) {
        // 
    }
}