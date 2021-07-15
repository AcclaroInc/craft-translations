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
use benf\neo\elements\Block;
use acclaro\translations\services\App;
use acclaro\translations\Translations;
use acclaro\translations\services\ElementTranslator;

class NeoFieldTranslator extends GenericFieldTranslator
{
    /**
     * {@inheritdoc}
     */
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $source = array();

        $blocks = $element->getFieldValue($field->handle)->all();

        if ($blocks) {
            // foreach ($blocks->level(1) as $block) { // removed in 3.2
            $new = 0;
            foreach ($blocks as $block) {
                $blockId = $block->fieldId . "_" . $block->typeId ?? 'new' . ++$new;
                $keyPrefix = sprintf('%s.%s', $field->handle, $blockId);

                $source = array_merge($source, $this->blockToTranslationSource($elementTranslator, $block, $keyPrefix));
            }
        }

        return $source;
    }

    public function blockToTranslationSource(ElementTranslator $elementTranslator, Block $block, $keyPrefix = '')
    {
        $source = array();

        $blockSource = $elementTranslator->toTranslationSource($block);

        foreach ($blockSource as $key => $value) {
            $key = sprintf('%s.%s', $keyPrefix, $key);

            $source[$key] = $value;
        }

        $block = $block->getChildren()->all();
        $new = 0;
        foreach ($block as $childBlock) {
            $childBlockId = $childBlock->id ?? 'new' . ++$new;
            $key = sprintf('%s.%s', $keyPrefix, $childBlockId);

            $childBlockSource = $this->blockToTranslationSource($elementTranslator, $childBlock, $key);

            foreach ($childBlockSource as $key => $value) {
                $source[$key] = $value;
            }
        }

        return $source;
    }

    protected function parseBlockData(&$allBlockData, $blockData, $blockId=null)
    {
        $newBlockData = array();
        $newToParse = array();

        foreach ($blockData as $key => $value) {
            if (is_numeric($key) || strpos($key, "_") !== false) {
                $newToParse[$key] = $value;
            } else {
                $newBlockData[$key] = $value;
            }
        }

        if ($newBlockData) {
            if($blockId)
            {
                $allBlockData[$blockId] = $newBlockData;
            } else {
                $allBlockData[] = $newBlockData;
            }
        }

        foreach ($newToParse as $blockId => $blockData) {
            $this->parseBlockData($allBlockData, $blockData, $blockId);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toPostArrayFromTranslationTarget(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceLanguage, $targetLanguage, $fieldData)
    {
        $fieldHandle = $field->handle;

        $blocks = $element->getFieldValue($fieldHandle)->all();

        $post = array(
            $fieldHandle => array(),
        );

        $allBlockData = array();
        $this->parseBlockData($allBlockData, $fieldData);

        $new = 0;
        foreach ($blocks as $i => $block) {
            $blockId = $block->id ?? 'new' . ++$new;
            $i = $block->fieldId . "_" . $block->typeId ?? 'new' . ++$new;
            $blockData = isset($allBlockData[$i]) ? $allBlockData[$i] : array();

            $post[$fieldHandle][$blockId] = array(
                'modified' => '1',
                'type' => $block->getType()->handle,
                'enabled' => $block->enabled,
                'collapsed' => $block->collapsed,
                'level' => $block->level,
                'fields' => $elementTranslator->toPostArrayFromTranslationTarget($block, $sourceLanguage, $targetLanguage, $blockData, true),
            );
        }

        return $post;
    }

    /**
     * {@inheritdoc}
     */
    public function getWordCount(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $blocks = $this->getFieldValue($elementTranslator, $element, $field)->all();

        if (!$blocks) {
            return 0;
        }

        $wordCount = 0;

        foreach ($blocks as $i => $block) {
            $wordCount += $elementTranslator->getWordCount($block);
        }

        return $wordCount;
    }

    /**
     * {@inheritdoc}
     */
    public function toPostArray(ElementTranslator $elementTranslator, Element $element, Field $field)
    {
        $fieldHandle = $field->handle;

        $blocks = $element->getFieldValue($fieldHandle)->all();

        if (!$blocks) {
            return [];
        }

        $post = array(
            $fieldHandle => array(),
        );
        $new = 0;
        foreach ($blocks as $i => $block) {

            $blockId = $block->id ?? 'new' . ++$new;
            $post[$fieldHandle][$blockId] = array(
                'type' => $block->getType()->handle,
                'enabled' => $block->enabled,
                'fields' => $block->getSerializedFieldValues(),
            );
        }

        return $post;
    }
}
