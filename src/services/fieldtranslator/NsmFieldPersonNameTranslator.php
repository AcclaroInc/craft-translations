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
use craft\base\Field;
use craft\base\Element;
use acclaro\translations\services\App;
use acclaro\translations\Translations;
use acclaro\translations\services\ElementTranslator;

class NsmFieldPersonNameTranslator extends GenericFieldTranslator
{

    private $nameFields = ['honorificPrefix', 'givenNames', 'additionalNames', 'familyNames', 'honorificSuffix'];

    /**
     * {@inheritdoc}
     */
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field)
    {

        $fieldHandle = $field->handle;

        $fieldData = $element->getFieldValue($fieldHandle);

        $source = [];
        if($fieldData){

            foreach($fieldData as $key => $value)
            {
                if (!empty($value) && in_array($key, $this->nameFields)) {
                    $k = sprintf('%s.%s.%s', $fieldHandle, $field->id, $key);
                    $source[$k] = $value;
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

        $fieldData = $element->getFieldValue($fieldHandle);

        $source = [];
        if( $fieldData )
        {
            foreach($fieldData as $key => $value)
            {
                if (in_array($key, $this->nameFields)) {
                    $source[$fieldHandle]['type'] = get_class($fieldData);
                    $source[$fieldHandle][$key] = $value;
                }
            }
        }

        return $source;
    }

    /**
     * {@inheritdoc} 
     */
    public function toPostArrayFromTranslationTarget(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite, $targetSite, $fieldData)
    {

        $post = array();
        
        $postRow = array();
        
        $fieldHandle = $field->handle;

        $post = $this->toPostArray($elementTranslator, $element, $field);

        if( $fieldData )
        {
            foreach ($fieldData as $i => $row) 
            {
                if ( $field->id == $i)
                {
                    foreach ($post[$fieldHandle] as $key => $value)
                    { 
                        if (isset($row[$key]))
                        {
                            $post[$fieldHandle][$key] = $row[$key];
                        }
                    }
                }
            }
        }

//        echo ' After toPostArrayFromTranslationTarget <pre>';  print_r($post);

        return $post;
    }

    /**
     * {@inheritdoc}
     */
    public function getWordCount(ElementTranslator $elementTranslator, Element $element, Field $field)
    {

        $data = $this->getFieldValue($elementTranslator, $element, $field);

        if (!$data) {
            return 0;
        }

        $wordCount = 0;

        foreach ($data as $key => $value) 
        {
            if($key === 'customText' || $key === 'ariaLabel' || $key === 'title')
            {
                $wordCount += Translations::$plugin->wordCounter->getWordCount(strip_tags($value));
            }
        }

        return $wordCount;
    }
}
