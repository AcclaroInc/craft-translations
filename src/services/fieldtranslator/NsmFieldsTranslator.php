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
use newism\fields\fields\Address;
use newism\fields\fields\Embed;
use newism\fields\fields\Gender;
use newism\fields\fields\PersonName;
use newism\fields\fields\Telephone;

class NsmFieldsTranslator extends GenericFieldTranslator
{

    private $fields = ['locality', 'dependentLocality', 'postalCode', 'addressLine1', 'addressLine2', 'organization', 'recipient', 'givenName', 'additionalName', 'familyName'];
    private $nameFields = ['honorificPrefix', 'givenNames', 'additionalNames', 'familyNames', 'honorificSuffix'];

    private $fieldData;

    private $fieldHandle;
    /**
     * {@inheritdoc}
     */
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field)
    {

        $source = [];

        if(get_class($field) == Address::class){

            $source = $this->addressTranslationSource($field, $element);
        } else if(get_class($field) == PersonName::class){

            $source = $this->nameTranslationSource($field, $element);
        } else if(get_class($field) == Telephone::class){

            $source = $this->telephoneTranslationSource($field, $element);
        } else if(get_class($field) == Gender::class){

            $source = $this->genderTranslationSource($field, $element);
        } else if(get_class($field) == Embed::class){

            $source = $this->embedTranslationSource($field, $element);
        }


        return $source;
    }

    /**
     * {@inheritdoc}
     */
    public function toPostArray(ElementTranslator $elementTranslator, Element $element, Field $field)
    {        
        $source = array();

        $this->fieldHandle = $field->handle;

        $this->fieldData = $element->getFieldValue($this->fieldHandle);

        if(get_class($field) == Address::class){

            $source = $this->addressTranslationSource($field, $element);
        } else if(get_class($field) == PersonName::class){

            $source = $this->namePostArray();
        } else if(get_class($field) == Telephone::class){

            //$source = $this->telephoneTranslationSource($field, $element);
        } else if(get_class($field) == Gender::class){

            //$source = $this->genderPostArray();
        } else if(get_class($field) == Embed::class){

            //$source = $this->embedTranslationSource($field, $element);
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

        if( $fieldData && $post)
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

        if (!is_array($data)) {
            return Translations::$plugin->wordCounter->getWordCount(strip_tags($data));
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

    /**
     * @param $field
     * @param $element
     * @return mixed
     */
    public function addressTranslationSource($field, $element){
        $fieldHandle = $field->handle;

        $fieldData = $element->getFieldValue($fieldHandle);

        $source = [];
        if($fieldData){

            foreach($fieldData as $key => $value)
            {
                $k = sprintf('%s.%s.%s', $fieldHandle, $field->id, $key);
                if ($key=='country') {
                    $source[$k] = $value->getName();
                } else if (in_array($key, $this->fields)) {
                    $source[$k] = $value;
                }
            }
        }

        return $source;
    }

    /**
     * @param $field
     * @param $element
     * @return mixed
     */
    public function nameTranslationSource($field, $element){
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
     * @param $field
     * @param $element
     * @return mixed
     */
    public function telephoneTranslationSource($field, $element){
        $fieldHandle = $field->handle;

        $fieldData = $element->getFieldValue($fieldHandle);

        $source = [];
        if(!empty($fieldData['rawInput'])){

            $k = sprintf('%s.%s.%s', $fieldHandle, $field->id, 'phoneNumber');
            $source[$k] = $fieldData['rawInput'];
        }

        return $source;
    }

    /**
     * @param $field
     * @param $element
     * @return mixed
     */
    public function genderTranslationSource($field, $element){
        $fieldHandle = $field->handle;

        $fieldData = $element->getFieldValue($fieldHandle);

        $source = [];
        if(!empty($fieldData['identity'])){
            $k = sprintf('%s.%s.%s', $fieldHandle, $field->id, 'identity');
            $source[$k] = $fieldData['identity'];
        }

        return $source;
    }

    /**
     * @param $field
     * @param $element
     * @return mixed
     */
    public function embedTranslationSource($field, $element){
        $fieldHandle = $field->handle;

        $fieldData = $element->getFieldValue($fieldHandle);

        $source = [];
        if(isset($fieldData['embedData'])){

            foreach($fieldData as $key => $value)
            {
                if (!empty($value) && in_array($key, ['title', 'description'])) {
                    $k = sprintf('%s.%s.%s.%s', $fieldHandle, $field->id, 'embedData', $key);
                    $source[$k] = $value;
                }
            }
        }

        return $source;
    }

    /**
     * @return array
     */
    public function namePostArray() {

        $source = [];
        if( $this->fieldData )
        {
            $fieldHandle = $this->fieldHandle;
            foreach($this->fieldData as $key => $value)
            {
                if (in_array($key, $this->nameFields)) {
                    $source[$fieldHandle]['type'] = $this->fieldData->type ?? null;
                    $source[$fieldHandle][$key] = $value;
                }
            }
        }

        return $source;
    }

    /**
     * @return array
     */
    public function genderPostArray() {

        $source = [];
        if( $this->fieldData )
        {
            $fieldHandle = $this->fieldHandle;

            $source[$fieldHandle]['type'] = $this->fieldData->type ?? null;
            $source['identity'] = $this->fieldData['identity'];
        }

        return $source;
    }

}
