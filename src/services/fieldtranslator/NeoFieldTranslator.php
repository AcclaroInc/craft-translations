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
use benf\neo\elements\Block;
use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\services\ElementTranslator;

class NeoFieldTranslator extends GenericFieldTranslator
{
    /**
     * {@inheritdoc}
     */
    public function toTranslationSource(ElementTranslator $elementTranslator, Element $element, Field $field, $sourceSite = null)
    {
        $source = array();

        try {
            $blocks = $element->getFieldValue($field->handle)->all();
        } catch (\Exception $e) {
            Translations::$plugin->logHelper->log(
                `[' . __METHOD__ . '] $field->handle not found.`,
                Constants::LOG_LEVEL_ERROR
            );
            return $source;
        }

        if ($blocks) {
            $new = 0;
            foreach ($blocks as $block) {
                $blockId = 'new' . ++$new;
                $keyPrefix = sprintf('%s.%s', $field->handle, $blockId);

                $source = array_merge($source, $this->blockToTranslationSource($elementTranslator, $block, $keyPrefix, $sourceSite));
            }
        }

        return $source;
    }

    public function blockToTranslationSource(ElementTranslator $elementTranslator, Block $block, $keyPrefix = '', $sourceSite = null)
    {
        $source = array();

        $blockSource = $elementTranslator->toTranslationSource($block, $sourceSite);

        foreach ($blockSource as $key => $value) {
            $key = sprintf('%s.%s', $keyPrefix, $key);

            $source[$key] = $value;
        }

        return $source;
    }

    protected function parseBlockData(&$allBlockData, $blockData, $blockId=null)
    {
        $newBlockData = array();
        $newToParse = array();

        foreach ($blockData as $key => $value) {
            if (is_numeric($key) || strpos($key, "new", 0) !== false) {
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
            $i = 'new' . ++$new;

            $isTranslatable = $field->getIsTranslatable($element);
            $blockId = $isTranslatable ? $i : $this->getBlockUid($block);
            $blockData = $allBlockData[$i] ?? array();

            $data = [
                'modified' => '1',
                'type' => $block->getType()->handle,
                'enabled' => $block->enabled,
                'collapsed' => $block->collapsed,
                'level' => $block->level,
                'fields' => $elementTranslator->toPostArrayFromTranslationTarget($block, $sourceLanguage, $targetLanguage, $blockData, true),                
            ];

            if ($isTranslatable) {
                $post[$fieldHandle][$blockId] = $data;
            } else {
                $post[$fieldHandle]['blocks'][$blockId] = $data;
            }
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

    private function getBlockUid($block)
    {
        return sprintf('uid:%s', $block->getCanonicalUid());
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

        foreach ($blocks as $i => $block) {
            $isTranslatable = $field->getIsTranslatable($element);
            $blockId = $isTranslatable ? $i : $this->getBlockUid($block);

            $data = [
                'modified' => '1',
                'type' => $block->getType()->handle,
                'enabled' => $block->enabled,
                'collapsed' => $block->collapsed,
                'level' => $block->level,
                'fields' => $elementTranslator->toPostArray($block)
            ];

            if ($isTranslatable) {
                $post[$fieldHandle][$blockId] = $data;
            } else {
                $post[$fieldHandle]['blocks'][$blockId] = $data;
            }
        }

        return $post;
    }
}
