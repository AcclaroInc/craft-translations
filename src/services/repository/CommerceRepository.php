<?php
/**
 * Translations for Craft plugin for Craft CMS 4.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\repository;

use Craft;
use Exception;
use craft\commerce\elements\Product;
use craft\commerce\Plugin as Commerce;
use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\models\CommerceDraftModel;
use acclaro\translations\records\CommerceDraftRecord;

class CommerceRepository
{
    public function makeNewDraft()
    {
        $draft = new CommerceDraftModel();

        return $draft;
    }

    public function getProductById($id, $site = null)
    {
        return Commerce::getInstance()->getProducts()->getProductById((int) $id, $site);
    }

    public function getDraftById($draftId)
    {
        $record = CommerceDraftRecord::findOne($draftId);

        if (!$record) return null;

        $commerceDraft = new CommerceDraftModel($record->toArray([
            'id',
            'name',
            'title',
            'productId',
            'typeId',
            'site',
            'variants',
            'data'
        ]));

        $commerceDraft->draftId = $commerceDraft->id;

        $commerceData = json_decode($record['data'], true);
        $variantData = json_decode($record['variants'], true);
        $fieldContent = isset($commerceData['fields']) ? $commerceData['fields'] : null;
        $product = $this->getProductById($commerceDraft->productId, $commerceDraft->site);

        if ($fieldContent) {
            $post = $product->getSerializedFieldValues();

            foreach ($fieldContent as $fieldId => $fieldValue) {
                $field = Craft::$app->fields->getFieldById($fieldId);

                if ($field) {
                    $post[$field->handle] = $fieldValue;
                }
            }

            $commerceDraft->setFieldValues($post);
        }
        
        $commerceDraft->slug = $commerceData['slug'] ?? $product->slug;

        $variants = $product->getVariants(true);

        if (! empty($variantData)) {
            foreach ($variants as $key => $variant) {
                if (isset($variantData[$variant->id])) {
                    $variant->title = $variantData[$variant->id]['title'];
                    foreach ($variant->getFieldLayout()->getCustomFields() as $layoutField) {
                        $field = Craft::$app->fields->getFieldById($layoutField->id);

                        if (isset($variantData[$variant->id][$field->id])) {
                            $variant->setFieldValue($field->handle, $variantData[$variant->id][$field->id]);
                        }
                    }
                }
                $variants[$key] = $variant;
            }
        }
        $commerceDraft->setVariants($variants);

        return $commerceDraft;
    }

    public function getDraftRecord(Product $draft)
    {
        if (isset($draft->draftId)) {
            $record = CommerceDraftRecord::findOne($draft->draftId);

            if (!$record) {
                throw new Exception(Translations::$plugin->translator->translate('app', 'No draft exists with the ID “{id}”.', array('id' => $draft->draftId)));
            }
        } else {
            $record = new CommerceDraftRecord();
            $record->productId = $draft->id;
            $record->site = $draft->site;
            $record->name = $draft->name;
            $record->title = $draft->title;
            $record->typeId = $draft->typeId;
        }

        return $record;
    }

    public function saveDraft(Product $draft, array $content = [])
    {
        $record = $this->getDraftRecord($draft);

        if (!$draft->name && $draft->id) {
            $totalDrafts = Craft::$app->getDb()->createCommand()
                ->from('translations_commercedrafts')
                ->where(
                    array('and', 'productId = :productId', 'site = :site'),
                    array(':productId' => $draft->id, ':site' => $draft->site)
                )
                ->count('id');

            $draft->name = Translations::$plugin->translator->translate('app', 'Draft {num}', array('num' => $totalDrafts + 1));
        }

        if (is_null($draft->productId)) {
            $record->productId = $draft->id; // This works for creating an order
        } else {
            $record->productId = $draft->productId; // This works post-order
        }

        $record->site = $draft->site;
        $record->name = $draft->name;
        $record->title = $draft->title;
        $record->typeId = $draft->typeId;

        $data = array(
            'fields' => array(),
            'slug'   => $draft->slug,
        );

        $content = $content ?? Translations::$plugin->elementTranslator->toPostArray($draft);

        foreach ($draft->getFieldLayout()->getCustomFields() as $layoutField) {
            $field = Craft::$app->fields->getFieldById($layoutField->id);

            if ($field->getIsTranslatable() || in_array(get_class($field), Constants::NESTED_FIELD_TYPES)) {
                if (isset($content[$field->handle]) && $content[$field->handle] !== null) {
                    $data['fields'][$field->id] = $content[$field->handle];
                }
            }
        }

        $record->data = $data;
        $data = [];
        
        if ($draft->getType()->hasVariants) {
            $variants = $draft->getVariants(true);

            foreach ($variants as $variant) {
                if (isset($content['variant'][$variant->id])) {
                    $data[$variant->id]['title'] = $content['variant'][$variant->id]['title'];
                    foreach ($variant->getFieldLayout()->getCustomFields() as $layoutField) {
                        $field = Craft::$app->fields->getFieldById($layoutField->id);
                        
                        if ($field->getIsTranslatable() || in_array(get_class($field), Constants::NESTED_FIELD_TYPES)) {
                            $fieldData = $content['variant'][$variant->id][$field->handle] ?? null;
                            
                            if ($fieldData) $data[$variant->id][$field->id] = $fieldData;
                        }
                    }
                }
            }
        }
        $record->variants = $data;

        $transaction = Craft::$app->db->getTransaction() === null ? Craft::$app->db->beginTransaction() : null;

        try {
            if ($record->save(false)) {
                if ($transaction !== null) {
                    $transaction->commit();
                }

                $draft->draftId = $record->id;

                return true;
            }
        } catch (Exception $e) {
            if ($transaction !== null) {
                $transaction->rollback();
            }

            throw $e;
        }

        return false;
    }

    public function publishDraft(Product $draft)
    {
        $post = [];

        $product = $this->getProductById($draft->productId, $draft->site);

        foreach ($draft->getDirtyFields() as $key => $fieldHandle) {
            $post[$fieldHandle] = $draft->getBehavior('customFields')->$fieldHandle;
        }

        $product->title = $draft->title;
        $product->setFieldValues($post);
        $product->setVariants($draft->getVariants(true));

        $success = Craft::$app->elements->saveElement($product, false);

        if (!$success) {
            Translations::$plugin->logHelper->log('[' . __METHOD__ . '] Couldn’t publish draft "' . $draft->title . '"', Constants::LOG_LEVEL_ERROR);
            return false;
        }

        return true;
    }

    public function deleteDraft(Product $draft)
    {
        try {
            $record = $this->getDraftRecord($draft);
        } catch (Exception $e) {
            return false;
        }

        $transaction = Craft::$app->db->getTransaction() === null ? Craft::$app->db->beginTransaction() : null;

        try {
            if ($record->delete(false)) {
                if ($transaction !== null) {
                    $transaction->commit();
                }

                return true;
            }
        } catch (Exception $e) {
            if ($transaction !== null) {
                $transaction->rollback();
            }

            throw $e;
        }
    }

    public function createDraft(Product $product, $site, $orderName)
    {
        try {
            $draft = $this->makeNewDraft();
            $draft->name = sprintf('%s [%s]', $orderName, $site);
            $draft->id = $product->id;
            $draft->site = $site;
            $draft->siteId = $site;
            $draft->title  = $product->title;
            $draft->slug   = $product->slug;
            $draft->typeId = $product->getType()->id;
            $draft->setVariants($product->getVariants(true));

            $post = Translations::$plugin->elementTranslator->toPostArray($product);
            $variants = $post['variant'];
            unset($post['variant']);
            $draft->setFieldValues($post);
            $post['variant'] = $variants;
            $this->saveDraft($draft, $post);

            return $draft;
        } catch (Exception $e) {

            Translations::$plugin->logHelper->log('[' . __METHOD__ . '] CreateDraft exception:: ' . $e->getMessage(), Constants::LOG_LEVEL_ERROR);
            return [];
        }
    }
}