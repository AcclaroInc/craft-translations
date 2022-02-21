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

class EntriesFieldTranslator extends GenericFieldTranslator
{
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $source = [];

        $blocks = $element->getFieldValue($field->handle)->all();

        if ($blocks) {
            foreach ($blocks as $block) {
                foreach ($block as $key => $value) {
                    $k = sprintf('%s.%s.%s', $field->handle, $block->id, $key);

                    if ($key !== 'id') {
                        continue;
                    }

                    $source[$k] = $value;
                }
            }
        }
        return $source;
    }

    /**
     * {@inheritdoc}
     */
    public function toPostArray(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $fieldHandle = $field->handle;

        $blocks = $element->getFieldValue($fieldHandle)->all();

        $post[$fieldHandle] = [];

        if (!$blocks) {
            return '';
        }

        foreach ($blocks as $i => $block) {
            $post[$fieldHandle][$block->id] = $block->id;
        }
        return $post;
    }

    /**
     * {@inheritdoc}
     */
    public function toPostArrayFromTranslationTarget(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite, $targetSite, $fieldData)
    {
        $fieldHandle = $field->handle;

        $post = [
            $fieldHandle => [],
        ];

		foreach ($fieldData as $data) {
			if ($entryId = $data['id'] ?? null) {
				$post[$fieldHandle][$entryId] = $entryId;
			}
        }

        return $post;
    }

    /**
     * {@inheritdoc}
     */
    public function getWordCount(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
    }
}
