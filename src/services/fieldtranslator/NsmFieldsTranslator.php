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

use newism\fields\fields\Address;
use newism\fields\fields\Embed;
use newism\fields\fields\Gender;
use newism\fields\fields\PersonName;
use newism\fields\fields\Telephone;
use newism\fields\fields\Email;

class NsmFieldsTranslator extends GenericFieldTranslator
{

    private $addressFields = ['countryCode', 'locality', 'dependentLocality', 'postalCode', 'addressLine1', 'addressLine2', 'organization', 'recipient', 'givenName', 'additionalName', 'familyName'];
    private $nameFields = ['honorificPrefix', 'givenNames', 'additionalNames', 'familyNames', 'honorificSuffix'];

    private $fieldData;

    private $fieldHandle;
    /**
     * {@inheritdoc}
     */
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field)
    {

        $source = [];

        $fieldHandle = $field->handle;
        $fieldData = $element->getFieldValue($fieldHandle);

        if ($fieldData) {
            switch (true) {
                case get_class($field) == Address::class:
                    foreach($fieldData as $key => $value)
                    {
                        $k = sprintf('%s.%s.%s', $fieldHandle, $field->id, $key);
                        if (in_array($key, $this->addressFields)) {
                            $source[$k] = $value;
                        }
                    }
                    break;
                case get_class($field) == PersonName::class:
                    foreach($fieldData as $key => $value)
                    {
                        if (!empty($value) && in_array($key, $this->nameFields)) {
                            $k = sprintf('%s.%s.%s', $fieldHandle, $field->id, $key);
                            $source[$k] = $value;
                        }
                    }
                    break;
                case get_class($field) == Telephone::class:
                    foreach($fieldData as $key => $value)
                    {
                        if(!empty($fieldData['rawInput']) && in_array($key, ['countryCode', 'rawInput'])) {
                            $k = sprintf('%s.%s.%s', $fieldHandle, $field->id, $key);
                            $source[$k] = $value;
                        }
                    }
                    break;
                case get_class($field) == Gender::class:
                    foreach($fieldData as $key => $value)
                    {
                        if(!empty($fieldData['identity'])) {
                            $k = sprintf('%s.%s.%s', $fieldHandle, $field->id, $key);
                            $source[$k] = $value;
                        }
                    }
                    break;
                case get_class($field) == Embed::class:
                    foreach($fieldData as $key => $value)
                    {
                        if(!empty($fieldData['rawInput']) && $key === 'rawInput') {
                            $k = sprintf('%s.%s.%s', $fieldHandle, $field->id, $key);
                            $source[$k] = $value;
                        }
                    }
                    break;
                case get_class($field) == Email::class:
                    if(!empty($fieldData)){
                        $source[$fieldHandle] = $fieldData;
                    }
                    break;

                default:
                    foreach($fieldData as $key => $value)
                    { 
                        $k = sprintf('%s.%s.%s', $fieldHandle, $field->id, $key);
                        $source[$k] = $value;
                    }
                    break;
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
            switch (true)
            {
                case get_class($field) == Email::class:
                    $source[$fieldHandle] = $fieldData;
                    break;
                
                default:
                    foreach($fieldData as $key => $value)
                    {
                        $source[$fieldHandle][$key] = $value;
                    }
                    break;
            }
        }

        return $source;
    }

    /**
     * {@inheritdoc} 
     */
    public function toPostArrayFromTranslationTarget(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite, $targetSite, $fieldData)
    {

        $fieldHandle = $field->handle;
        
        $post = array();
        
        $postRow = array();

        $post = $this->toPostArray($elementTranslator, $element, $field);

        if( $fieldData && $post)
        {
            switch (true)
            {
                case get_class($field) == Email::class:
                    $post[$fieldHandle] = $fieldData;
                    break;
                
                default:
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
                    break;
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

        if (!is_array($data)) {
            switch (true) {
                case get_class($field) == Gender::class:
                    break;
                
                default:
                    $wordCount = Translations::$plugin->wordCounter->getWordCount(strip_tags($data));
                    break;
            }
            
            return $wordCount;
        }

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
