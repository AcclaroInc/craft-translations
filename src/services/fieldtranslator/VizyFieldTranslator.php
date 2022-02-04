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
						$key = sprintf('%s.%s.%s', $field->handle, $block->getBlockType()->id, $innerField->handle);
						$value = $block->getFieldvalue($innerField->handle);

						if (is_string($value)) {
							$source[$key] = $value;
							continue;
						}

						if ($value instanceof craft\redactor\FieldData) {
							$source[$key] = $value->getRawContent();
							continue;
						}

						if ($value instanceof \newism\fields\models\PersonNameModel) {
							foreach ($value as $handle => $data) {
								$source[$key.".".$handle] = $data;
							}
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
		$postArray = [];

		$blocks = $element->getFieldValue($field->handle)->all();

		foreach ($blocks as $index => $block) {
			$blockArray = $block['rawNode'];

			foreach ($block->getFieldLayout()->getFields() as $innerField) {
				if (isset($fieldData[$block->getBlockType()->id][$innerField->handle])) {
					$value = $fieldData[$block->getBlockType()->id][$innerField->handle];

					if (! is_string($value)) {
						$innerBlock = $block['attrs']['values']['content']['fields'][$innerField->handle];

						$value = $this->fieldToPostArrayFromTranslationTarget($block->getFieldvalue($innerField->handle), $innerBlock, $value);
					}

					$blockArray['attrs']['values']['content']['fields'][$innerField->handle] = $value;
				}
			}
			$postArray[$field->handle][$index] = $blockArray;
		}

		return $postArray;
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
				$newKey = sprintf('%s.new%s.%s', $key, $index, $field->handle);

				$value = $nestedField->getFieldvalue($field->handle);

				if (is_string($value)) {
					$source[$newKey] = $value;
					continue;
				}

				if ($value instanceof craft\redactor\FieldData) {
					$source[$newKey] = $value->getRawContent();
					continue;
				}

				if ($value instanceof \newism\fields\models\PersonNameModel) {
					foreach ($value as $handle => $data) {
						$source[$newKey.".".$handle] = $data;
					}
					continue;
				}

				foreach ($value->all() as $nestedIndex => $innerField) {
					$source = array_merge($source, $this->fieldToTranslationSource($innerField, $newKey, ++$nestedIndex));
				}
			}
		}

		return $source;
	}

	/**
	 * Converts Target data to post array
	 *
	 * @param mixed $nestedFields
	 * @param array $attributes
	 * @param array $targetData
	 * @return array
	 */
	private function fieldToPostArrayFromTranslationTarget($nestedFields, $attributes, $targetData)
	{
		$postArray = $attributes;

		if ($nestedFields instanceof \newism\fields\models\PersonNameModel) {
			foreach ($nestedFields as $handle => $field) {
				$postArray[$handle] = $targetData[$handle];
			}
			return $postArray;
		}

		foreach ($nestedFields->all() as $index => $block) {
			$index = "new" . $index+1;

			foreach ($block->getFieldLayout()->getFields() as $field) {
				if (isset($targetData[$index][$field->handle])) {
					$value = $targetData[$index][$field->handle];

					if (! is_string($value)) {
						$innerBlock = $attributes[$index]['fields'][$field->handle];
						$value = $this->fieldToPostArrayFromTranslationTarget($block->getFieldvalue($field->handle), $innerBlock, $value);
					}

					$postArray[$index]['fields'][$field->handle] = $value;
				}
			}
		}

		return $postArray;
	}

	/**
	 * Checks if a field can be translated
	 *
	 * @param [type] $field
	 * @return boolean
	 */
	private function isFieldTranslatable($field)
	{
		return $field->getIsTranslatable() || in_array(get_class($field), Constants::NESTED_FIELD_TYPES);
	}
}
