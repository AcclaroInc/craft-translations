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
use acclaro\translations\Constants;
use acclaro\translations\services\ElementTranslator;

class VizyFieldTranslator extends GenericFieldTranslator
{
    /**
     * {@inheritdoc}
     */
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite = null)
    {
        $source = [];

		$blocks = $element->getFieldValue($field->handle)->all();

		if ($blocks) {
			foreach ($blocks as $block) {
				foreach ($block->getFieldLayout()->getFields() as $innerField) {
					if ($this->isFieldTranslatable($innerField)) {
						$key = sprintf('%s.%s', $field->handle, $innerField->handle);
						$value = $block->getFieldvalue($innerField->handle);

						if (is_string($value)) {
							$source[$key] = $value;
							continue;
						}

						if ($value instanceof craft\redactor\FieldData) {
							$source[$key] = $value->getRawContent();
							continue;
						}

						foreach ($value->all() as $index => $nestedField) {
							$source = array_merge($source, $this->fieldToTranslationSource($nestedField, $key, ++$index));
						}
					}
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
        $fieldHandle = $field->handle;

        $post= $this->getAttributeArray($element, $field);

		foreach ($post[$fieldHandle] as $index => $block) {
			foreach ($block['attrs']['values']['content']['fields'] as $key => $value) {
				$translatedValue = $fieldData[$key] ?? $value;

				if (is_array($translatedValue)) {
					$this->fieldToPostArrayTranslationTarget($block, $key, $value, $translatedValue);
					continue;
				}

				$block['attrs']['values']['content']['fields'][$key] = $translatedValue;
			}

			$post[$fieldHandle][$index] = $block;
		}

		return $post;
	}

	/**
	 * Convert nested fields to translation source array
	 *
	 * @param mixed $nestedField
	 * @param string $key
	 * @return array
	 */
	private function fieldToTranslationSource($nestedField, $key, $index)
	{
		$source = [];

		foreach ($nestedField->type->getFieldLayout()->getFields() as $field) {
			if ($this->isFieldTranslatable($field)) {
				$newKey = sprintf('%s.%s.%s%s.%s', $key, $nestedField->type->handle, "new", $index, $field->handle);

				$value = $nestedField->getFieldvalue($field->handle);

				if (is_string($value)) {
					$source[$newKey] = $value;
					continue;
				}

				if ($value instanceof craft\redactor\FieldData) {
					$source[$newKey] = $value->getRawContent();
					continue;
				}

				foreach ($value->all() as $nestedIndex => $innerField) {
					$source = array_merge($source, $this->fieldToTranslationSource($innerField, $newKey, ++$nestedIndex));
				}
			}
		}

		return $source;
	}

	private function fieldToPostArrayTranslationTarget(&$block, $key, $fields, $targetData)
	{
		foreach ($fields as $nestedKey => $value) {
			foreach ($value['fields'] as $handle => $handleValue) {
				$blockId = $block['attrs']['values']['content']['fields'][$key][$nestedKey]['type'];
				if ($key == "superTable") {
					$blockId = sprintf("%s_%s", $key, --$blockId);
				}
				$block['attrs']['values']['content']['fields'][$key][$nestedKey]['fields'][$handle] = $targetData[$blockId][$nestedKey][$handle] ?? $handleValue;
			}
		}
	}

	private function getAttributeArray($element, $field)
	{
		$fieldHandle = $field->handle;

        $blocks = $element->getFieldValue($fieldHandle)->all();

		$attributes = array(
			$fieldHandle => array()
		);

		foreach ($blocks as $block) {
			$attributes[$fieldHandle][] = [
				'type' => $block['rawNode']['type'],
				'attrs' => $block['attrs']
			];
		}

		return $attributes;
	}

	private function isFieldTranslatable($field)
	{
		return $field->getIsTranslatable() || in_array(get_class($field), Constants::NESTED_FIELD_TYPES);
	}
}
