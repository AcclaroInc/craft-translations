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

class LinkitFieldTranslator extends GenericFieldTranslator
{
    /**
     * {@inheritdoc}
     */
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field)
    { 

        $source = array();

        $fieldHandle = $field->handle;
        
        $fieldData = $element->getFieldValue($fieldHandle);
        
        if($fieldData){
            
            foreach($fieldData as $key => $value)
            {
                if($key === 'customText' || $key === 'defaultText')
                {
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
        $source = array();

        $fieldHandle = $field->handle;

        $fieldData = $element->getFieldValue($fieldHandle);

        if( $fieldData )
        {
            foreach($fieldData as $key => $value)
            {
                $source[$fieldHandle]['type'] = $fieldData->type;
                $source[$fieldHandle][$key] = $value;
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
            if($key === 'customText' || $key === 'defautlText')
            {
                $wordCount += Translations::$plugin->wordCounter->getWordCount(strip_tags($value));
            }
        }
        return $wordCount;
    }
}