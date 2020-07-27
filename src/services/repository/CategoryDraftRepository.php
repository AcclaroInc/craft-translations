<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\repository;

use Craft;
use craft\fields\Matrix;
use craft\elements\Category;
use craft\base\ElementInterface;
use acclaro\translations\Translations;
use acclaro\translations\models\CategoryDraftModel;
use acclaro\translations\records\CategoryDraftRecord;

class CategoryDraftRepository
{
    public function makeNewDraft()
    {
        return new CategoryDraftModel();
    }

    public function getDraftById($draftId)
    {
        $record = CategoryDraftRecord::findOne($draftId);

        $categoryDraft = new CategoryDraftModel($record->toArray([
            'id',
            'name',
            'title',
            'categoryId',
            'site',
            'data'
        ]));

        $category = Craft::$app->categories->getCategoryById($categoryDraft->categoryId);
        $categoryDraft->groupId = $category->groupId;

        $categoryDraft->draftId = $categoryDraft->id;
        
        $categoryData = json_decode($record['data'], true);
        $fieldContent = isset($categoryData['fields']) ? $categoryData['fields'] : null;

        if ($fieldContent) {
            $post = array();

            foreach ($fieldContent as $fieldId => $fieldValue) {
                $field = Craft::$app->fields->getFieldById($fieldId);

                if ($field) {
                    $post[$field->handle] = $fieldValue;
                }
            }

            $categoryDraft->setFieldValues($post);
        }

        return $categoryDraft;
    }
    
    public function getDraftsByCategoryId($categoryId, $site = null)
    {
        $attributes = array(
            'categoryId' => $categoryId,
            'site' => $site ?: Craft::$app->sites->getPrimarySite()->id
        );

        $records = CategoryDraftModel::find()->where($attributes)->all();

        foreach ($records as $key => $record) {
            $categoryDrafts[$key] = new CategoryDraftModel($record->toArray([
                'id',
                'name',
                'title',
                'categoryId',
                'site',
                'data'
            ]));

            $categoryDrafts[$key]->draftId = $categoryDrafts[$key]->id;
        }

        return $records ? $categoryDrafts : array();
    }

    public function getDraftRecord(Category $draft)
    {
        if (isset($draft->draftId)) {
            $record = CategoryDraftRecord::findOne($draft->draftId);

            if (!$record) {
                throw new Exception(Translations::$plugin->translator->translate('app', 'No draft exists with the ID “{id}”.', array('id' => $draft->draftId)));
            }
        } else {
            $record = new CategoryDraftRecord();
            $record->categoryId = $draft->id;
            $record->site = $draft->site;
            $record->name = $draft->name;
            $record->title = $draft->title;
        }

        return $record;
    }

    public function saveDraft(Category $draft)
    {
        $record = $this->getDraftRecord($draft);

        if (!$draft->name && $draft->id) {
            $totalDrafts = Craft::$app->getDb()->createCommand()
                ->from('translations_categorydrafts')
                ->where(
                    array('and', 'categoryId = :categoryId', 'site = :site'),
                    array(':categoryId' => $draft->id, ':site' => $draft->site)
                )
                ->count('id');
            
            $draft->name = Translations::$plugin->translator->translate('app', 'Draft {num}', array('num' => $totalDrafts + 1));
        }

        if (is_null($draft->categoryId)) {
            $record->categoryId = $draft->id; // This works for creating an order
        } else {
            $record->categoryId = $draft->categoryId; // This works post-order
        }
        $record->site = $draft->site;
        $record->name = $draft->name;
        $record->title = $draft->title;

        $data = array(
            'fields' => array(),
        );

        if (empty($draft->groupId)) {
            $category = Craft::$app->getCategories()->getCategoryById($draft->id, $draft->site);
            $draft->groupId = $category->groupId;
        }
        $content = Translations::$plugin->elementTranslator->toPostArray($draft);

        $nestedFieldType = [
            'craft\fields\Matrix',
            'verbb\supertable\fields\SuperTableField',
            'benf\neo\Field'
        ];

        foreach ($draft->getFieldLayout()->getFields() as $layoutField) {
            $field = Craft::$app->fields->getFieldById($layoutField->id);

            if ($field->getIsTranslatable() || in_array(get_class($field), $nestedFieldType)) {
                $data['fields'][$field->id] = $content[$field->handle];
                if (isset($content[$field->handle]) && $content[$field->handle] !== null) { 
                    $data['fields'][$field->id] = $content[$field->handle];
                }
            }
        }

        $record->data = $data;
        
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

    public function publishDraft(Category $draft)
    {
        $category = Craft::$app->categories->getCategoryById($draft->categoryId, $draft->site);
        $category->title = $draft->title;
        $category->setFieldValues(Translations::$plugin->elementTranslator->toPostArray($draft));
        
        $success = Craft::$app->elements->saveElement($category);
        
        if (!$success) {
            Craft::error('Couldn’t publish draft "'.$draft->title.'"', __METHOD__);
            return false;
        }

        return true;
    }

    public function deleteDraft(Category $draft)
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
}
