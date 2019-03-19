<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\services;

use Craft;
use Exception;
use DOMDocument;
use craft\base\Field;
use craft\base\Element;
use craft\elements\Tag;
use craft\elements\Entry;
use craft\records\EntryType;
use \craft\base\ElementTrait;
use craft\elements\Category;
use \craft\base\ContentTrait;
use \craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\TranslationsForCraft;

class ElementTranslator
{
    public function toTranslationSource(Element $element)
    {
        $source = array();
        
        // if ($element instanceof Element || $element instanceof Tag || $element instanceof Category) {
        if ($element instanceof Element) {
            $source['title'] = $element->title;
            $source['slug'] = $element->slug;
        }
        
        foreach ($element->getFieldLayout()->getFields() as $layoutField) {
            $field = Craft::$app->fields->getFieldById($layoutField->id);
            $fieldSource = $this->fieldToTranslationSource($element, $field);

            $source = array_merge($source, $fieldSource);
        }

        return $source;
    }

    public function getTargetDataFromXml($xml)
    {
        $dom = new DOMDocument('1.0', 'utf-8');

        $dom->loadXML($xml);

        $targetData = array();

        $contents = $dom->getElementsByTagName('content');

        
        foreach ($contents as $content) {
            $name = (string) $content->getAttribute('resname');
            $value = (string) $content->nodeValue;
            
            if (strpos($name, '.') !== false) {
                $parts = explode('.', $name);
                $container =& $targetData;

                while ($parts) {
                    $key = array_shift($parts);

                    if (!isset($container[$key])) {
                        $container[$key] = array();
                    }

                    $container =& $container[$key];
                }

                $container = $value;
            } else {
                $targetData[$name] = $value;
            }
        }

        return $targetData;
    }

    public function toPostArrayFromTranslationTarget(Element $element, $sourceSite, $targetSite, $targetData, $includeNonTranslatable = false)
    {
        $post = array();
        foreach($element->getFieldLayout()->getFields() as $layoutField) {
            $field = Craft::$app->fields->getFieldById($layoutField->id);

            $fieldHandle = $field->handle;
            
            $fieldType = $field;

            $translator = TranslationsForCraft::$plugin->fieldTranslatorFactory->makeTranslator($fieldType);

            if (!$translator) {
                if ($includeNonTranslatable) {
                    $post[$fieldHandle] = $element->$fieldHandle;
                }

                continue;
            }

            if (isset($targetData[$fieldHandle])) {
                $fieldPost = $translator->toPostArrayFromTranslationTarget($this, $element, $field, $sourceSite, $targetSite, $targetData[$fieldHandle]);
            } else {
                $fieldPost = $translator->toPostArray($this, $element, $field);
            }
            
            if (!is_array($fieldPost)) {
                $fieldPost = array($fieldHandle => $fieldPost);
            }

            $post = array_merge($post, $fieldPost);
        }

        return $post;
    }

    public function toPostArray(Element $element)
    {
        $source = array();

        foreach($element->getFieldLayout()->getFields() as $layoutField) {
            $field = Craft::$app->fields->getFieldById($layoutField->id);

            $fieldSource = $this->fieldToPostArray($element, $field);

            $source = array_merge($source, $fieldSource);
        }

        return $source;
    }

    public function getWordCount(Element $element)
    {
        $wordCount = 0;

        if ($element instanceof Entry || $element instanceof Tag || $element instanceof Category) {
            $wordCount += TranslationsForCraft::$plugin->wordCounter->getWordCount($element->title);
            $wordCount += TranslationsForCraft::$plugin->wordCounter->getWordCount($element->slug);
        }
        
        foreach($element->getFieldLayout()->getFields() as $layoutField) {
            $field = Craft::$app->fields->getFieldById($layoutField->id);
            
            $wordCount += $this->getFieldWordCount($element, $field);
        }

        return $wordCount;
    }

    public function fieldToTranslationSource(Element $element, Field $field)
    {
        $fieldType = $field;

        $translator = TranslationsForCraft::$plugin->fieldTranslatorFactory->makeTranslator($fieldType);

        $fieldSource = array();

        if ($translator && $field->getIsTranslatable()) {
            $fieldSource = $translator->toTranslationSource($this, $element, $field);

            if (!is_array($fieldSource)) {
                $fieldSource = array($field->handle => $fieldSource);
            }
        }

        return $fieldSource;
    }

    public function fieldToPostArray(Element $element, Field $field)
    {
        $fieldType = $field;

        $fieldHandle = $field->handle;

        $translator = TranslationsForCraft::$plugin->fieldTranslatorFactory->makeTranslator($fieldType);

        $fieldSource = array();

        if ($translator && $field->getIsTranslatable()) {
            $fieldSource = $translator->toPostArray($this, $element, $field);

            if (!is_array($fieldSource)) {
                $fieldSource = array($fieldHandle => $fieldSource);
            }
        }
        // else {
        //     $fieldSource =  array($fieldHandle => $element->$fieldHandle);
        // }

        return $fieldSource;
    }

    public function getFieldWordCount(Element $element, Field $field)
    {
        $fieldType = $field;

        $fieldHandle = $field->handle;

        if ($field->getIsTranslatable()) {
            $translator = TranslationsForCraft::$plugin->fieldTranslatorFactory->makeTranslator($fieldType);

            return $translator ? $translator->getWordCount($this, $element, $field) : 0;
        }
    }
}