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
			foreach ($blocks as $index => $block) {
				$key = sprintf('%s.new%s', $field->handle, ++$index);
				$source = array_merge($source, $this->fieldToTranslationSource($block['rawNode'], $key));
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
				$postArray[$field->handle][$index] = $this->fieldToPostArrayFromTranslationTarget($node, $targetData[$key]);
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
	private function fieldToTranslationSource($data, $key)
	{
		$source = [];

		switch ($type = $data['type']) {
			case 'text':
				$key = sprintf('%s.%s', $key, $type);
				$source[$key] = $data[$type];
				break;
			case 'paragraph':
			case 'bulletList':
			case 'listItem':
			case 'orderedList':
				foreach ($data['content'] as $index => $value) {
					$newKey = sprintf('%s.%s.new%s', $key, $type, ++$index);
					$source = array_merge($source, $this->fieldToTranslationSource($value, $newKey));
				}
				break;
			case 'vizyBlock':
				foreach ($data['attrs']['values']['content']['fields'] as $handle => $values) {
					if (is_array($values)) {
						foreach ($values as $index => $value) {
							// Skip index as matrix/superTable already procide as new1/new2
							if (strpos($index, 'new', 0) === 0) {
							} else {
								$index = sprintf('new%s', ++$index);
							}

							$newKey = sprintf('%s.%s.%s.%s', $key, $type, $handle, $index);
							$source = array_merge($source, $this->fieldToTranslationSource($value, $newKey));
						}
					} else {
						$newKey = sprintf('%s.%s.%s', $key, $type, $handle);
						$source[$newKey] = $values;
					}
				}
				break;
			default:
				foreach ($data['fields'] as $handle => $value) {
					$newKey = sprintf('%s.%s', $key, $handle);
					$source[$newKey] = $value;
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
							// Skip index as matrix/superTable already procide as new1/new2
							if (strpos($index, 'new', 0) === 0) {
								$key = $index;
							} else {
								$key = sprintf('new%s', $index + 1);
							}

							$target = $targetData[$type][$handle][$key];

							$postArray['attrs']['values']['content']['fields'][$handle][$index] = $this->fieldToPostArrayFromTranslationTarget($value, $target);
						}
					} else {
						$postArray['attrs']['values']['content']['fields'][$handle] = $targetData[$type][$handle];
					}
				}
				break;
			default:
				foreach ($node['fields'] as $handle => $value) {
					$postArray[$handle] = $targetData[$handle];
				}
		}

		return $postArray;
	}
}
