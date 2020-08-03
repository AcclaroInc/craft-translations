<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services;

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
use acclaro\translations\services\App;
use acclaro\translations\Translations;

class ElementTranslator
{
    public function toTranslationSource(Element $element, $sourceSite=null)
    {
        $source = array();
        
        // if ($element instanceof Element || $element instanceof Tag || $element instanceof Category) {
        if ($element instanceof Element) {
            if ($element->title) {
                $source['title'] = $element->title;
            }
            if ($element->slug) {
                $source['slug'] = $element->slug;
            }

        }
        
        foreach ($element->getFieldLayout()->getFields() as $layoutField) {
            $field = Craft::$app->fields->getFieldById($layoutField->id);
            $fieldSource = $this->fieldToTranslationSource($element, $field, $sourceSite);

            $source = array_merge($source, $fieldSource);
        }

        // echo '</pre>';
        // echo "//======================================================================<br>// return source toTranslationSource()<br>//======================================================================<br>";
        // var_dump($source);
        // echo '</pre>';

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
        // echo '<pre>';
        // echo "//======================================================================<br>// toPostArrayFromTranslationTarget()<br>//======================================================================<br>";
        // var_dump($element->id);
        // echo "//======================================================================<br>// sourceSite()<br>//======================================================================<br>";
        // var_dump($sourceSite);
        // echo "//======================================================================<br>// targetSite()<br>//======================================================================<br>";
        // var_dump($targetSite);
        // echo "//======================================================================<br>// field layout count()<br>//======================================================================<br>";
        // var_dump(count($element->getFieldLayout()->getFields()));
        
        foreach($element->getFieldLayout()->getFields() as $key => $layoutField) {
            $field = Craft::$app->fields->getFieldById($layoutField->id);
            // echo "//======================================================================<br>// field<br>//======================================================================<br>";
            // var_dump($key);
            // var_dump($field);
            
            $fieldHandle = $field->handle;
            
            $fieldType = $field;
            
            $translator = Translations::$plugin->fieldTranslatorFactory->makeTranslator($fieldType);
            
            if (!$translator) {
                if ($includeNonTranslatable) {
                    $post[$fieldHandle] = $element->$fieldHandle;
                }
                
                continue;
            }
            
            // echo '<pre>';
            // echo "//======================================================================<br>// targetData<br>//======================================================================<br>";
            // var_dump($targetData);
            // echo "//======================================================================<br>// field handle<br>//======================================================================<br>";
            // var_dump($fieldHandle);
            // echo "//======================================================================<br>// translator<br>//======================================================================<br>";
            // var_dump($translator);
            if (isset($targetData[$fieldHandle])) {
                // echo 'BLAH1';
                $fieldPost = $translator->toPostArrayFromTranslationTarget($this, $element, $field, $sourceSite, $targetSite, $targetData[$fieldHandle]);
            } else {
                // echo 'BLAH12';
                $fieldPost = $translator->toPostArray($this, $element, $field, $sourceSite);
            }
            
            
            if (!is_array($fieldPost)) {
                $fieldPost = array($fieldHandle => $fieldPost);
            }
            
            $post = array_merge($post, $fieldPost);
            // var_dump($fieldPost);
            // echo '</pre>';
        }
        
        // echo '<pre>';
        // echo "//======================================================================<br>// post toPostArrayFromTranslationTarget()<br>//======================================================================<br>";
        // var_dump($post);
        // echo '</pre>';
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
            $wordCount += Translations::$plugin->wordCounter->getWordCount($element->title);
            $wordCount += Translations::$plugin->wordCounter->getWordCount($element->slug);
        }
        foreach($element->getFieldLayout()->getFields() as $layoutField) {
            $field = Craft::$app->fields->getFieldById($layoutField->id);

            $wordCount += $this->getFieldWordCount($element, $field);
        }

        return $wordCount;
    }

    public function fieldToTranslationSource(Element $element, Field $field, $sourceSite=null)
    {
        $fieldType = $field;

        $nestedFieldType = [
            'craft\fields\Matrix',
            'craft\fields\Assets',
            'verbb\supertable\fields\SuperTableField',
            'benf\neo\Field'
        ];

        $translator = Translations::$plugin->fieldTranslatorFactory->makeTranslator($fieldType);

        $fieldSource = array();

        // Check if field is translatable or is nested field
        if ($translator && $field->getIsTranslatable() || $translator && in_array(get_class($field), $nestedFieldType)) {
            $fieldSource = $translator->toTranslationSource($this, $element, $field, $sourceSite);

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

        $nestedFieldType = [
            'craft\fields\Matrix',
            'craft\fields\Assets',
            'verbb\supertable\fields\SuperTableField',
            'benf\neo\Field'
        ];

        $translator = Translations::$plugin->fieldTranslatorFactory->makeTranslator($fieldType);

        $fieldSource = array();

        // Check if field is translatable or is nested field
        if ($translator && $field->getIsTranslatable() || $translator && in_array(get_class($field), $nestedFieldType)) {
            $fieldSource = $translator->toPostArray($this, $element, $field);

            if (!is_array($fieldSource)) {
                $fieldSource = array($fieldHandle => $fieldSource);
            }
        }

        return $fieldSource;
    }

    public function getFieldWordCount(Element $element, Field $field)
    {
        $fieldType = $field;

        $fieldHandle = $field->handle;

        $nestedFieldType = [
            'craft\fields\Matrix',
            'craft\fields\Assets',
            'verbb\supertable\fields\SuperTableField',
            'benf\neo\Field'
        ];

        // Check if field is translatable or is nested field
        if ($field->getIsTranslatable() || in_array(get_class($field), $nestedFieldType)) {
            $translator = Translations::$plugin->fieldTranslatorFactory->makeTranslator($fieldType);

            return $translator ? $translator->getWordCount($this, $element, $field) : 0;
        }
    }
}
