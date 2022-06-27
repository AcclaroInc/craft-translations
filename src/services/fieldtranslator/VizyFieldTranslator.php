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
use acclaro\translations\Constants;
use acclaro\translations\services\ElementTranslator;

class VizyFieldTranslator extends GenericFieldTranslator
{
	protected $skipNsmFields = ['sex', 'country', 'countryCode', 'administrativeArea', 'mapUrl', 'sortingCode', 'placeData', 'recipient', 'locale', 'dependentLocality', 'additionalName'];

	/**
	 * {@inheritdoc}
	 */
	public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite = null)
	{
		$source = [];

		$blocks = $element->getFieldValue($field->handle)->all();

		if ($blocks) {
			foreach ($blocks as $index => $block) {
				$key = sprintf('%s.new%s', $field->handle, ++$index);
				$source = array_merge($source, $this->fieldToTranslationSource($block, $key, $sourceSite));
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

		if ($blocks) {
			foreach ($blocks as $index => $block) {
				$node = $block['rawNode'];
				$key = sprintf('new%s', $index + 1);
				if (isset($targetData[$key])) {
					$block = $this->fieldToPostArrayFromTranslationTarget($node, $targetData[$key]);
				} else {
					$block = $block->serializeValue();
				}

				$postArray[$field->handle][$index] = $block;
			}
		}

		return $postArray;
	}

	private function fieldToTranslationSource($block, $key, $sourceSite)
	{
		$source = [];

		switch (get_class($block)) {
			case \verbb\vizy\nodes\Text::class:
				break;
			case \verbb\vizy\nodes\Paragraph::class:
			case \verbb\vizy\nodes\BulletList::class:
			case \verbb\vizy\nodes\OrderedList::class:
			case \verbb\vizy\nodes\ListItem::class:
				$source = array_merge($source, $this->customFieldsToSourceArray($block->serializeValue(), $key));
				break;
			default:
				foreach ($block->getFieldLayout()->getCustomFields() as $field) {
					if ($this->getIsTranslatable($field)) {
						$newKey = sprintf('%s.%s', $key, $field->handle);
						$value = $block->getFieldValue($field->handle);

						$source = array_merge($source, $this->parseSourceValues($value, $newKey, $sourceSite));
					}
				}
		}

		return $source;
	}

	private function parseSourceValues($value, $key, $sourceSite)
	{
		$source = [];

		switch ($value) {
			case is_null($value):
				break;
			case is_string($value):
				$source[$key] = $value;
				break;
			case $value instanceof \craft\redactor\FieldData:
				$source[$key] = $value->getRawContent();
				break;
			case $value instanceof \craft\fields\Checkboxes:
				foreach ($value->getOptions() as $option) {
					$k = sprintf('%s.%s', $key, $option->value);
					$source[$k] = $option->label;
				}
				break;
			case $value instanceof \fruitstudios\linkit\fields\LinkitField:
				$source[$key] = $value->serializeValue($value)['customText'];

				break;
			case $value instanceof \craft\fields\Assets:
				foreach ($value->siteId($sourceSite)->all() as $asset) {
					$source[sprintf('%s.%s.%s', $key, $asset->id, 'title')] = $asset->title;
				}
				break;
			case $value instanceof \newism\fields\fields\PersonName:
			case $value instanceof \newism\fields\fields\Address:
			case $value instanceof \newism\fields\fields\Email:
			case $value instanceof \newism\fields\fields\Telephone:
			case $value instanceof \newism\fields\fields\Gender:
			case $value instanceof \newism\fields\fields\Embed:
				foreach ($value as $nsmKey => $nsmVal) {
					if (in_array($nsmKey, $this->skipNsmFields)) continue;
					$k = sprintf('%s.%s', $key, $nsmKey);
					$source[$k] = $nsmVal;
				}
				break;
			default:
				foreach ($value->all() as $innerIndex => $innerBlock) {
					$newKey = sprintf('%s.new%s', $key, $innerIndex + 1);
					$source = array_merge($source, $this->fieldToTranslationSource($innerBlock, $newKey, $sourceSite));
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
	private function fieldToPostArrayFromTranslationTarget($node, $targetData)
	{
		$postArray = $node;

		switch ($type = $node['type']) {
			case 'text':
				$postArray[$type] = $targetData[$type];
				break;
			case 'paragraph':
			case 'bulletList':
			case 'listItem':
			case 'orderedList':
				foreach ($node['content'] as $index => $value) {
					$key = sprintf('new%s', $index + 1);
					$postArray['content'][$index] = $this->fieldToPostArrayFromTranslationTarget($value, $targetData[$type][$key]);
				}
				break;
			case 'vizyBlock':
				foreach ($node['attrs']['values']['content']['fields'] as $handle => $values) {
					if (is_array($values)) {
						if (empty($values)) continue;

						foreach ($values as $index => $value) {
							// Skip index as matrix/superTable already provide as new1/new2
							if (strpos($index, 'new', 0) === 0) {
								$key = $index;
							} else {
								$key = sprintf('new%s', $index + 1);
							}

							if (isset($targetData[$handle][$key])) {
								$target = $targetData[$handle][$key];

								$postArray['attrs']['values']['content']['fields'][$handle][$index] = $this->fieldToPostArrayFromTranslationTarget($value, $target);
							}
						}
					} else {
						if (isset($targetData[$handle]))
							$postArray['attrs']['values']['content']['fields'][$handle] = $targetData[$handle];
					}
				}
				break;
			default:
				foreach ($node['fields'] as $handle => $value) {
					if (isset($targetData[$handle]))
						$postArray['fields'][$handle] = $targetData[$handle];
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
				foreach ($attrs['content'] as $index => $value) {
					$newKey = sprintf('%s.new%s', $key, ++$index);
					$source = array_merge($source, $this->customFieldsToSourceArray($value, $newKey));
				}
		}

		return $source;
	}
}
