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
use craft\base\Element;

class ElementCloner
{
    public function cloneElement(Element $existingElement)
    {
        $elementType = Craft::$app->elements->getElementTypeById($existingElement->id); //Might need to modify

        $elementClass = get_class($existingElement);

        $newElement = new $elementClass();

        foreach ($existingElement->attributeLabels() as $attribute) {
            if ($attribute === 'id' || $attribute === 'dateCreated' || $attribute === 'dateUpdated' || $attribute === 'uid') {
                continue;
            }

            $newElement->$attribute = $existingElement->$attribute;
        }

        if ($elementType->hasContent()) {
            if ($elementType->hasTitles()) {
                $newElement->title = $existingElement->title;
            }

            foreach ($existingElement->getFieldLayout()->getCustomFields() as $fieldLayoutField) {
                $this->cloneField($existingElement, $newElement, $fieldLayoutField->getField());
            }
        }

        return $newElement;
    }
}