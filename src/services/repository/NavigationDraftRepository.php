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
use craft\elements\Asset;
use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\models\NavigationDraftModel;
use acclaro\translations\records\NavigationDraftRecord;
use verbb\navigation\elements\Node;
use verbb\navigation\Navigation;

class NavigationDraftRepository
{
    /**
     * Create Asset Draft Model object
     *
     * @return NavigationDraftModel
     */
    public function makeNewDraft()
    {
        return new NavigationDraftModel();
    }

    /**
     * Find asset draft by id
     *
     * @param int $draftId
     * @return NavigationDraftModel
     */
    public function getDraftById($draftId)
    {
        $record = NavigationDraftRecord::findOne($draftId);
        $navDraft = null;

        if ($record) {
            $navDraft = new NavigationDraftModel($record->toArray([
                'id',
                'name',
                'title',
                'navId',
                'site',
                'data',
                'draftId',
            ]));

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

                $navDraft->setFieldValues($post);
            }
        }

        return $navDraft;
    }

    /**
     * Find Source Asset by id
     *
     * @param int $id
     * @param int $site
     * @return Asset
     */
    public function getNavById($id, $site = null)
    {
        return Navigation::getInstance()->getNodes()->getNodesForNav($id, $site)[0];
    }

    /**
     * Find asset draft row
     *
     * @param \craft\elements\Asset $draft
     * @return NavigationDraftRecord
     */
    public function getDraftRecord(Node $draft)
    {
        if (isset($draft->draftId)) {
            $record = NavigationDraftRecord::findOne($draft->draftId);

            if (!$record) {
                throw new Exception(Translations::$plugin->translator->translate('app', 'No draft exists with the ID “{id}”.', array('id' => $draft->draftId)));
            }
        } else {
            $record = new NavigationDraftRecord();
            $record->navId = $draft->navId;
            $record->site = $draft->site;
            $record->name = $draft->name;
            $record->title = $draft->titles;
        }

        return $record;
    }

    public function saveDraft(Node &$draft)
    {
        $record = $this->getDraftRecord($draft);
        if (!$draft->name && $draft->id) {
            $totalDrafts = Craft::$app->getDb()->createCommand()
                ->from('translations_navigationdrafts')
                ->where(
                    array('and', 'navId = :navId', 'site = :site'),
                    array(':navId' => $draft->navId, ':site' => $draft->site)
                )
                ->count('id');

            $draft->name = Translations::$plugin->translator->translate('app', 'Draft {num}', array('num' => $totalDrafts + 1));
        }

        if (is_null($draft->navId)) {
            $record->navId = $draft->id;
        } else {
            $record->navId = $draft->navId;
        }

        $record->site = $draft->site;
        $record->name = $draft->name;
        $record->title = $draft->title;

        $data = array(
            'fields' => array(),
        );

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

    public function publishDraft(Node $draft)
    {
        $post = [];

        $product = $this->getNavById($draft->navId, $draft->site);

        foreach ($draft->getDirtyFields() as $key => $fieldHandle) {
            $post[$fieldHandle] = $draft->getBehavior('customFields')->$fieldHandle;
        }

        $product->title = $draft->title;
        $product->setFieldValues($post);

        $success = Craft::$app->elements->saveElement($product, false);

        if (!$success) {
            Translations::$plugin->logHelper->log('[' . __METHOD__ . '] Couldn’t publish draft "' . $draft->title . '"', Constants::LOG_LEVEL_ERROR);
            return false;
        }

        return true;
    }

    public function createDraft(Node $asset, $site, $orderName, $sourceSite)
    {
        try {
            $draft = $this->makeNewDraft();
            $draft->name = sprintf('%s [%s]', $orderName, $site);
            $draft->id = $asset->id;
            $draft->site = $site;
            $draft->title = $asset->title;
            $draft->sourceSite = $sourceSite;
            $draft->navId = $asset->navId;
            $draft->data = array();
            $this->saveDraft($draft);

            return $draft;
        } catch (Exception $e) {
            Translations::$plugin->logHelper->log( '['. __METHOD__ .'] CreateAssetDraft exception:: '.$e->getMessage(), Constants::LOG_LEVEL_ERROR);
            return [];
        }

    }

    public function deleteDraft(Node $draft)
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
