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
	private $skipNsmFields = ['sex', 'country', 'countryCode', 'administrativeArea', 'mapUrl', 'sortingCode', 'placeData', 'recipient', 'locale', 'dependentLocality', 'additionalName'];

	/**
	 * {@inheritdoc}
	 */
	public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite = null)
	{
		$source = [];

		$blocks = $element->getFieldValue($field->handle)->all();
		if ($blocks) {
			foreach ($blocks as $index => $block) {
				if ($block instanceof \verbb\vizy\nodes\VizyBlock) {
					foreach ($block->getFieldLayout()->getCustomFields() as $innerField) {
						if ($this->getIsTranslatable($innerField)) {
							$key = sprintf('%s.%s.%s', $field->handle, $block->id, $innerField->handle);
							$value = $block->getFieldvalue($innerField->handle);

							// NOTE: The reason we are parsing any type of fields inside this file but not calling again element translator is because of vizy does not return cafts interface class thus it error out as invalid handle at the end when it reaches generic field translator file.
							switch (true) {
								case is_null($value):
									break;
								case is_string($value):
									$source[$key] = $value;
									break;
								case $innerField instanceof craft\redactor\Field:
									$source[$key] = $value->getRawContent();
									break;
								case $innerField instanceof craft\fields\Checkboxes:
									foreach ($value->getOptions() as $option) {
										$k = sprintf('%s.%s', $key, $option->value);
										$source[$k] = $option->label;
									}
									break;
								case $innerField instanceof \fruitstudios\linkit\fields\LinkitField:
									$k = sprintf('%s.%s.customText', $key, $index);

									$source[$k] = $innerField->serializeValue($value)['customText'];

									break;
								case $innerField instanceof craft\fields\Assets:
									foreach ($value->siteId($sourceSite)->all() as $asset) {
										$source[sprintf('%s.%s.%s', $key, $asset->id, 'title')] = $asset->title;
									}
									break;
								case $innerField instanceof \newism\fields\fields\PersonName:
								case $innerField instanceof \newism\fields\fields\Address:
								case $innerField instanceof \newism\fields\fields\Email:
								case $innerField instanceof \newism\fields\fields\Telephone:
								case $innerField instanceof \newism\fields\fields\Gender:
								case $innerField instanceof \newism\fields\fields\Embed:
									foreach ($value as $nsmKey => $nsmVal) {
										if (in_array($nsmKey, $this->skipNsmFields)) continue;
										$k = sprintf('%s.%s', $key, $nsmKey);
										$source[$k] = $nsmVal;
									}
									break;
								default:
									$source = array_merge($source, $this->fieldToTranslationSource($value, $key, $index));
							}
						}
					}
				} else {
					$key = sprintf('%s.new%s', $field->handle, ++$index);
					$data = $this->customFieldsToSourceArray($block->serializeValue(), $key);
					$source = array_merge($source, $data);
				}
			}
		}

		return $source;
	}

	/**
	 * {@inheritdoc}
	 */
	public function toPostArrayFromTranslationTarget(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite, $targetSite, $targetData)
	{
		$postArray = [];

		$blocks = $element->getFieldValue($field->handle)->all();

		foreach ($blocks as $index => $block) {
			$blockArray = $block['rawNode'];
			if ($block instanceof \verbb\vizy\nodes\VizyBlock) {
				foreach ($block->getFieldLayout()->getCustomFields() as $innerField) {
					if (isset($targetData[$block->id][$innerField->handle])) {
						$value = $targetData[$block->id][$innerField->handle];
						$innerBlock = $block['attrs']['values']['content']['fields'][$innerField->handle];

						$newValue = $this->fieldToPostArrayFromTranslationTarget($block, $innerField, $innerBlock, $value, $targetSite, $index);

						$blockArray['attrs']['values']['content']['fields'][$innerField->handle] = $newValue;
					}
				}
				$postArray[$field->handle][$index] = $blockArray;
			} else {
				$key = sprintf('new%s', $index + 1);
				$data = $this->customFieldToPostArray($blockArray, $targetData[$key]);
				$postArray[$field->handle][$index] = $data;
			}
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
	private function fieldToTranslationSource($value, $key, $mainIndex)
	{
		$source = [];

		foreach ($value->all() as $index => $nestedField) {
			$index += 1;
			foreach ($nestedField->getFieldLayout()->getCustomFields() as $field) {
				if ($this->getIsTranslatable($field)) {
					$newKey = sprintf('%s.new%s.%s', $key, $index, $field->handle);

					$newValue = $nestedField->getFieldvalue($field->handle);

					switch ($newValue) {
						case is_null($newValue):
							break;
						case is_string($newValue):
							$source[$newKey] = $newValue;
							break;
						case $field instanceof craft\redactor\Field:
							$source[$newKey] = $newValue->getRawContent();
							break;
						case $field instanceof craft\fields\Checkboxes:
							foreach ($newValue->getOptions() as $option) {
								$k = sprintf('%s.%s', $newKey, $option->value);
								$source[$k] = $option->label;
							}
							break;
						case $field instanceof \fruitstudios\linkit\fields\LinkitField:
							$k = sprintf('%s.%s.customText', $newKey, $mainIndex);

							$source[$k] = $field->serializeValue($newValue)['customText'];

							break;
						case $field instanceof craft\fields\Assets:
							foreach ($newValue->all() as $asset) {
								$source[sprintf('%s.%s.%s', $newKey, $asset->id, 'title')] = $asset->title;
							}
							break;
						case $field instanceof \newism\fields\fields\PersonName:
						case $field instanceof \newism\fields\fields\Address:
						case $field instanceof \newism\fields\fields\Email:
						case $field instanceof \newism\fields\fields\Telephone:
						case $field instanceof \newism\fields\fields\Gender:
						case $field instanceof \newism\fields\fields\Embed:
							foreach ($value as $nsmKey => $nsmVal) {
								if (in_array($nsmKey, $this->skipNsmFields)) continue;
								$k = sprintf('%s.%s', $key, $nsmKey);
								$source[$k] = $nsmVal;
							}
							break;
						default:
							$source = array_merge($source, $this->fieldToTranslationSource($newValue, $newKey, $mainIndex));
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
	 * @param mixed $attributes
	 * @param array $targetData
	 * @return array
	 */
	private function fieldToPostArrayFromTranslationTarget($block, $field, $attributes, $targetData, $targetSite, $mainIndex)
	{
		$handle = $field->handle;
		$value = $block->getFieldValue($handle);
		$postArray = $attributes;

		switch (true) {
			case is_null($value):
				break;
			case is_string($value):
			case $field instanceof craft\redactor\Field:
				$postArray = $targetData;
				break;
			case $field instanceof craft\fields\Checkboxes:
				foreach ($value->getOptions() as $option) {
					$postArray['label'] = $targetData[$option->value];
				}
				break;
			case $field instanceof \fruitstudios\linkit\fields\LinkitField:
				$postArray['customText'] = $targetData[$mainIndex]['customText'];
				break;
			case $field instanceof craft\fields\Assets:
				foreach ($attributes as $assetId) {
					$asset = Craft::$app->assets->getAssetById($assetId, $targetSite);
					$asset->siteId = $targetSite;

					foreach ($targetData[$assetId] as $handle => $value) {
						$asset->$handle = $targetData[$assetId][$handle];
					}
					Translations::$plugin->draftRepository->saveDraft($asset);
				}
				break;
			case $field instanceof \newism\fields\fields\PersonName:
			case $field instanceof \newism\fields\fields\Email:
			case $field instanceof \newism\fields\fields\Telephone:
			case $field instanceof \newism\fields\fields\Gender:
			case $field instanceof \newism\fields\fields\Embed:
				foreach ($attributes as $nsmKey => $nsmVal) {
					if (key_exists($nsmKey, $targetData)) {
						$postArray[$nsmKey] = $targetData[$nsmKey];
					}
				}
				break;
			case $field instanceof \newism\fields\fields\Address:
				$tmp = [];
				foreach ($value as $nsmKey => $nsmVal) {
					if (key_exists($nsmKey, $targetData)) {
						$tmp[$nsmKey] = $targetData[$nsmKey];
					} else {
						$tmp[$nsmKey] = $nsmVal;
					}
				}
				$postArray = json_encode($tmp);
				break;
			default:
				foreach ($value->all() as $index => $block) {
					$index++;
					$index = "new" . $index;
					foreach ($block->getFieldLayout()->getCustomFields() as $field) {
						if (isset($targetData[$index][$field->handle])) {
							$value = $targetData[$index][$field->handle];

							if (!is_string($value)) {
								$innerBlock = $attributes[$index]['fields'][$field->handle];
								$value = $this->fieldToPostArrayFromTranslationTarget($block, $field, $innerBlock, $value, $targetSite, $mainIndex);
							}

							$postArray[$index]['fields'][$field->handle] = $value;
						}
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
	private function getIsTranslatable($field)
	{
		return $field->getIsTranslatable() || in_array(get_class($field), Constants::NESTED_FIELD_TYPES);
	}

	/**
	 * function to parse custom fields attribute to source array
	 *
	 * @param array $attrs
	 * @param string $key
	 * @return array
	 */
	private function customFieldsToSourceArray($attrs, $key)
	{
		$source = [];
		$type = $attrs['type'];

		$key = sprintf('%s.%s', $key, $type);
		switch ($type) {
			case 'text':
				$source[$key] = $attrs[$type];
				break;
			case 'image':
				// Image does not have title as of now so skipping this type
				$source[$key] = $attrs['attrs']['title'] ?? '';
				break;
			default:
				foreach ($attrs['content'] as $value) {
					$source = array_merge($source, $this->customFieldsToSourceArray($value, $key));
				}
		}

		return $source;
	}

	/**
	 * converts target data array to post array for custom fields
	 *
	 * @param array $attrs
	 * @param array $fieldData
	 * @return array
	 */
	private function customFieldToPostArray($attrs, $fieldData)
	{
		$type = $attrs['type'];

		switch ($type) {
			case 'text':
				$attrs[$type] = $fieldData[$type];
				break;
			case 'image':
				$attrs['attrs']['title'] = $fieldData[$type];
				break;
			default:
				foreach ($attrs['content'] as $key => $value) {
					$attrs['content'][$key] = $this->customFieldToPostArray($value, $fieldData[$type]);
				}
		}

		return $attrs;
	}
}
