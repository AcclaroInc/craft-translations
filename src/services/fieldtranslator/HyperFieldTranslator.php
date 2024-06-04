<?php
/**
 * Translations for Craft plugin for Craft CMS 4.x
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

class HyperFieldTranslator extends GenericFieldTranslator
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
                $k = sprintf('%s.%s.%s', $fieldHandle, $field->id, $key);
                $source[$k] = $value->linkText;
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
                            $value['linkText'] = $row[$key];
                            if (isset($value['linkSiteId'])) {
                                $value['linkSiteId'] = $targetSite;
                            }
                            $post[$fieldHandle][$key] = $value;
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
            $wordCount += Translations::$plugin->wordCounter->getWordCount(strip_tags($value->linkText));
        }

        return $wordCount;
    }
}
