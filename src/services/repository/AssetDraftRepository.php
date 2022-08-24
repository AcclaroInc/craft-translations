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
use craft\base\Element;
use craft\elements\Asset;
use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\models\AssetDraftModel;
use acclaro\translations\records\AssetDraftRecord;

class AssetDraftRepository
{
    /**
     * Create Asset Draft Model object
     *
     * @return AssetDraftModel
     */
    public function makeNewDraft()
    {
        return new AssetDraftModel();
    }

    /**
     * Find asset draft by id
     *
     * @param int $draftId
     * @return AssetDraftModel
     */
    public function getDraftById($draftId)
    {
        $record = AssetDraftRecord::findOne($draftId);
        $assetDraft = null;

        if ($record) {
            $assetDraft = new AssetDraftModel($record->toArray([
                'id',
                'name',
                'title',
                'assetId',
                'site',
                'data'
            ]));

            $assetDraft->avoidFilenameConflicts = true;
            $assetDraft->setScenario(Asset::SCENARIO_CREATE);

            $asset = Craft::$app->assets->getAssetById($assetDraft->assetId);

            $assetDraft->tempFilePath = null;
            $assetDraft->setFilename($asset->getFilename());
            $assetDraft->newFolderId = $asset->folderId;
            $assetDraft->setVolumeId($asset->volumeId);

            $assetDraft->draftId = $assetDraft->id;
            $assetDraft->folderId = $asset->folderId;

            $assetData = json_decode($record['data'], true);
            $fieldContent = isset($assetData['fields']) ? $assetData['fields'] : null;

            if ($fieldContent) {
                $post = array();

                foreach ($fieldContent as $fieldId => $fieldValue) {
                    $field = Craft::$app->fields->getFieldById($fieldId);

                    if ($field) {
                        $post[$field->handle] = $fieldValue;
                    }
                }

                $assetDraft->setFieldValues($post);
                Craft::$app->getElements()->saveElement($assetDraft);
            }
        }

        return $assetDraft;
    }

    /**
     * Find Source Asset by id
     *
     * @param int $id
     * @param int $site
     * @return Asset
     */
    public function getAssetById($id, $site = null)
    {
        return Craft::$app->getAssets()->getAssetById($id, $site);
    }

    /**
     * Find asset draft row
     *
     * @param \craft\elements\Asset $draft
     * @return AssetDraftRecord
     */
    public function getDraftRecord(Asset $draft)
    {
        if (isset($draft->draftId)) {
            $record = AssetDraftRecord::findOne($draft->draftId);

            if (!$record) {
                throw new Exception(Translations::$plugin->translator->translate('app', 'No draft exists with the ID “{id}”.', array('id' => $draft->draftId)));
            }
        } else {
            $record = new AssetDraftRecord();
            $record->assetId = $draft->id;
            $record->site = $draft->site;
            $record->name = $draft->name;
            $record->title = $draft->title;
        }

        return $record;
    }

    public function saveDraft(Asset $draft, array $content = [])
    {
        $record = $this->getDraftRecord($draft);

        if (!$draft->name && $draft->id) {
            $totalDrafts = Craft::$app->getDb()->createCommand()
                ->from('translations_assetdrafts')
                ->where(
                    array('and', 'assetId = :assetId', 'site = :site'),
                    array(':assetId' => $draft->id, ':site' => $draft->site)
                )
                ->count('id');

            $draft->name = Translations::$plugin->translator->translate('app', 'Draft {num}', array('num' => $totalDrafts + 1));
        }

        if (is_null($draft->assetId)) {
            $record->assetId = $draft->id; // This works for creating an order
        } else {
            $record->assetId = $draft->assetId; // This works post-order
        }
        $record->site = $draft->site;
        $record->name = $draft->name;
        $record->title = $draft->title;

        $data = array(
            'fields' => array(),
        );

        $draft->siteId = $draft->site;

        $content = $content ?? [];

        if (empty($content)) {
            foreach ($draft->getDirtyFields() as $key => $fieldHandle) {
                $content[$fieldHandle] = $draft->getBehavior('customFields')->$fieldHandle;
            }
        }

        $asset = $this->getAssetById($record->assetId, $draft->site);

        foreach ($asset->getFieldLayout()->getCustomFields() as $layoutField) {
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

    public function publishDraft(Asset $draft)
    {
        $post = [];

        foreach ($draft->getDirtyFields() as $key => $fieldHandle) {
            $post[$fieldHandle] = $draft->getBehavior('customFields')->$fieldHandle;
        }

        $asset = Craft::$app->assets->getAssetById($draft->assetId, $draft->site);
        $asset->title = $draft->title;
        $asset->setFieldValues($post);

        $asset->setScenario(Element::SCENARIO_LIVE);

        $success = Craft::$app->elements->saveElement($asset);

        if (!$success) {
            Translations::$plugin->logHelper->log( '['. __METHOD__ .'] Couldn’t publish draft "'.$draft->title.'"', Constants::LOG_LEVEL_ERROR );
            return false;
        }

        return true;
    }

    public function createDraft(Asset $asset, $site, $orderName, $sourceSite)
    {
        try {
            $draft = $this->makeNewDraft();

            $draft->name = sprintf('%s [%s]', $orderName, $site);
            $draft->id = $asset->id;
            $draft->title = $asset->title;
            $draft->site = $site;

            $post = Translations::$plugin->elementTranslator->toPostArray($asset);

            $draft->setFieldValues($post);

            $this->saveDraft($draft, $post);

            return $draft;
        } catch (Exception $e) {
            Translations::$plugin->logHelper->log( '['. __METHOD__ .'] CreateAssetDraft exception:: '.$e->getMessage(), Constants::LOG_LEVEL_ERROR);
            return [];
        }

    }

    public function deleteDraft(Asset $draft)
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
