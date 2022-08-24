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
use Exception;
use craft\elements\GlobalSet;
use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\models\GlobalSetDraftModel;
use acclaro\translations\records\GlobalSetDraftRecord;

class GlobalSetDraftRepository
{
    public function makeNewDraft()
    {
        $draft = new GlobalSetDraftModel();

        return $draft;
    }

    public function getDraftById($draftId)
    {
        $record = GlobalSetDraftRecord::findOne($draftId);

        if (!$record) return null;

        $globalSetDraft = new GlobalSetDraftModel($record->toArray([
            'id',
            'name',
            'globalSetId',
            'site',
            'data'
        ]));


        $globalSetDraft->draftId = $globalSetDraft->id;

        $globalSetData = json_decode($record['data'], true);
        $fieldContent = isset($globalSetData['fields']) ? $globalSetData['fields'] : null;

        if ($fieldContent) {
            $post = array();

            foreach ($fieldContent as $fieldId => $fieldValue) {
                $field = Craft::$app->fields->getFieldById($fieldId);

                if ($field) {
                    $post[$field->handle] = $fieldValue;
                }
            }

            $globalSetDraft->setFieldValues($post);
        }

        return $globalSetDraft;
    }

    public function getDraftsByGlobalSetId($globalSetId, $site = null)
    {
        $attributes = array(
            'globalSetId' => $globalSetId,
            'site' => $site ?: Craft::$app->sites->getPrimarySite()->id
        );

        $records = GlobalSetDraftRecord::find()->where($attributes)->all();

        foreach ($records as $key => $record) {
            $globalSetDrafts[$key] = new GlobalSetDraftModel($record->toArray([
                'id',
                'name',
                'globalSetId',
                'site',
                'data'
            ]));

            $globalSetDrafts[$key]->draftId = $globalSetDrafts[$key]->id;
        }

        return $records ? $globalSetDrafts : array();
    }

    public function getDraftRecord(GlobalSet $draft)
    {
        if (isset($draft->draftId)) {
            $record = GlobalSetDraftRecord::findOne($draft->draftId);

            if (!$record) {
                throw new Exception(Translations::$plugin->translator->translate('app', 'No draft exists with the ID “{id}”.', array('id' => $draft->draftId)));
            }
        } else {
            $record = new GlobalSetDraftRecord();
            $record->globalSetId = $draft->id;
            $record->site = $draft->site;
            $record->name = $draft->name;
        }

        return $record;
    }

    public function saveDraft(GlobalSet $draft, array $content = [])
    {
        $record = $this->getDraftRecord($draft);

        if (!$draft->name && $draft->id) {
            $totalDrafts = Craft::$app->getDb()->createCommand()
                ->from('translations_globalsetdrafts')
                ->where(
                    array('and', 'globalSetId = :globalSetId', 'site = :site'),
                    array(':globalSetId' => $draft->id, ':site' => $draft->site)
                )
                ->count('id');

            $draft->name = Translations::$plugin->translator->translate('app', 'Draft {num}', array('num' => $totalDrafts + 1));
        }

        if (is_null($draft->globalSetId)) {
            $record->globalSetId = $draft->id; // This works for creating an order
        } else {
            $record->globalSetId = $draft->globalSetId; // This works post-order
        }

        $record->site = $draft->site;
        $record->name = $draft->name;

        $data = array(
            'fields' => array(),
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

    public function publishDraft(GlobalSet $draft)
    {
        $post = [];

        $globalSet = Craft::$app->globals->getSetById($draft->globalSetId, $draft->site);

        foreach ($draft->getDirtyFields() as $key => $fieldHandle) {
            $post[$fieldHandle] = $draft->getBehavior('customFields')->$fieldHandle;
        }

        $globalSet->setFieldValues($post);

        $success = Craft::$app->elements->saveElement($globalSet);

        if (!$success) {
            Translations::$plugin->logHelper->log( '['. __METHOD__ .'] Couldn’t publish draft "'.$draft->title.'"', Constants::LOG_LEVEL_ERROR);
            return false;
        }

        return true;
    }

    public function deleteDraft(GlobalSet $draft)
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

    public function createDraft(GlobalSet $globalSet, $site, $orderName)
    {
        try {
            $draft = $this->makeNewDraft();
            $draft->name = sprintf('%s [%s]', $orderName, $site);
            $draft->id = $globalSet->id;
            $draft->site = $site;
            $draft->siteId = $site;

            $post = Translations::$plugin->elementTranslator->toPostArray($globalSet);

            $draft->setFieldValues($post);

            $this->saveDraft($draft, $post);

            return $draft;
        } catch (Exception $e) {

            Translations::$plugin->logHelper->log( '['. __METHOD__ .'] CreateDraft exception:: '.$e->getMessage(), Constants::LOG_LEVEL_ERROR );
            return [];
        }

    }
}
