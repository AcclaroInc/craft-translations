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
use acclaro\translations\Translations;

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

						switch ($value) {
							case !is_object($value):
								$source[$key] = $value ?? "";
								break;
							case $value instanceof craft\redactor\FieldData:
								$source[$key] = $value->getRawContent();
								break;
							case $value instanceof \newism\fields\models\PersonNameModel:
								foreach ($value as $handle => $data) {
									$source[$key . "." . $handle] = $data;
								}
								break;
							default:
								$source = array_merge($source, $this->fieldToTranslationSource($value, $key));
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

						$value = $this->fieldToPostArrayFromTranslationTarget($block->getFieldvalue($innerField->handle), $innerBlock, $value, $targetSite);
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
	private function fieldToTranslationSource($value, $key)
	{
		$source = [];

		foreach ($value->all() as $index => $nestedField) {
			$index += 1;
			foreach ($nestedField->getFieldLayout()->getFields() as $field) {
				if ($this->isFieldTranslatable($field)) {
					$newKey = sprintf('%s.new%s.%s', $key, $index, $field->handle);

					$value = $nestedField->getFieldvalue($field->handle);

					switch ($value) {
						case (!is_object($value)):
							if ($nestedField instanceof craft\elements\Asset) {
								$newKey = sprintf('%s.%s.%s', $key, $nestedField->id, $field->handle);
								$source[sprintf('%s.%s.%s', $key, $nestedField->id, 'title')] = $nestedField->title;
							}
							$source[$newKey] = $value ?? "";
							break;
						case $value instanceof craft\redactor\FieldData:
							$source[$newKey] = $value->getRawContent();
							break;
						case $value instanceof \newism\fields\models\PersonNameModel:
							foreach ($value as $handle => $data) {
								$source[$newKey . "." . $handle] = $data;
							}
							break;
						default:
							$source = array_merge($source, $this->fieldToTranslationSource($value, $newKey));
					}
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
	private function fieldToPostArrayFromTranslationTarget($nestedFields, $attributes, $targetData, $targetSite)
	{
		$postArray = $attributes;

		if ($nestedFields instanceof \newism\fields\models\PersonNameModel) {
			foreach ($nestedFields as $handle => $field) {
				$postArray[$handle] = $targetData[$handle];
			}
			return $postArray;
		}

		if ($nestedFields instanceof craft\elements\db\AssetQuery) {
			foreach ($attributes as $assetId) {
				$asset = Craft::$app->assets->getAssetById($assetId, $targetSite);
				$asset->siteId = $targetSite;

				foreach ($targetData[$assetId] as $handle => $value) {
					$asset->$handle = $value;
				}
				Translations::$plugin->draftRepository->saveDraft($asset);
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
						$value = $this->fieldToPostArrayFromTranslationTarget($block->getFieldvalue($field->handle), $innerBlock, $value, $targetSite);
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
