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

interface TranslatableFieldInterface
{
    /**
     * Extract element field data suitable for a translation source document
     *
     * a scalar value
     * - OR -
     * array of key => value pairs
     *
     * @param  \acclaro\translations\services\ElementTranslator $elementTranslator
     * @param  \craft\base\Element                                      $element
     * @param  \craft\base\Field                                        $field
     * @return string|bool|int|float|array
     */
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field);

    /**
     * Update an element with data from translation target document
     *
     * @param  \acclaro\translations\services\ElementTranslator $elementTranslator
     * @param  \craft\base\Element                                      $element
     * @param  \craft\base\Field                                        $field
     * @param  string                                                   $sourceSite
     * @param  string                                                   $targetSite
     * @param  mixed                                                    $fieldData
     * @return string|bool|int|float|array
     */
    public function toPostArrayFromTranslationTarget(
        ElementTranslator $elementTranslator,
        Element $element,
        Field $field,
        $sourceSite,
        $targetSite,
        $fieldData
    );

    /**
     * Extract element field value as it would appear in POST array
     *
     * a scalar value
     * - OR -
     * array of key => value pairs
     *
     * @param  \acclaro\translations\services\ElementTranslator $elementTranslator
     * @param  \craft\base\Element                                      $element
     * @param  \craft\base\Field                                        $field
     * @return string|bool|int|float|array
     */
    public function toPostArray(ElementTranslator $elementTranslator, Element $element, Field $field);

    /**
     * Get word count of an element field
     *
     * @param  \acclaro\translations\services\ElementTranslator $elementTranslator
     * @param  \craft\base\Element                                      $element
     * @param  \craft\base\Field                                        $field
     * @return int
     */
    public function getWordCount(ElementTranslator $elementTranslator, Element $element, Field $field);
}