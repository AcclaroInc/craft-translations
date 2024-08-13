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
     * Create a new NavigationDraftModel object
     *
     * @return NavigationDraftModel
     */
    public function makeNewDraft()
    {
        return new NavigationDraftModel();
    }

    /**
     * Find navigation draft by id
     *
     * @param int $draftId
     * @return NavigationDraftModel|null
     */
    public function getDraftById($draftId)
    {
        $record = NavigationDraftRecord::findOne($draftId);
        $navDraft = null;

        if ($record) {
            $navDraft = new NavigationDraftModel($record->toArray([
                'id', 'name', 'title', 'navId', 'site', 'data', 'draftId', 'canonicalId'
            ]));

            $assetData = json_decode($record['data'], true);
            $fieldContent = $assetData['fields'] ?? null;

            if ($fieldContent) {
                $post = [];
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
     * Find navigation node by id
     *
     * @param int $id
     * @param int|null $site
     * @return Node|null
     */
    public function getNavById($id, $site = null)
    {
        return Navigation::getInstance()->getNodes()->getNodesForNav($id, $site)[0] ?? null;
    }

    /**
     * Find or create navigation draft record
     *
     * @param Node $draft
     * @return NavigationDraftRecord
     * @throws Exception
     */
    public function getDraftRecord(Node $draft, bool $isNew = false)
    {
        if (! $isNew) {
            $record = NavigationDraftRecord::findOne($draft->id);

            if (!$record) {
                throw new Exception(Translations::$plugin->translator->translate('app', 'No draft exists with the ID “{id}”.', array('id' => $draft->draftId)));
            }
        } else {
            $record = new NavigationDraftRecord();
            $record->navId = $draft->navId;
            $record->site = $draft->site;
            $record->name = $draft->name;
            $record->title = $draft->title;
            $record->canonicalId = $draft->canonicalId;
        }
        return $record;
    }

    /**
     * Save the navigation draft
     *
     * @param Node $draft
     * @return bool
     * @throws Exception
     */
    public function saveDraft(Node &$draft, array $content = [])
    {
        $isnew = $content['isnew'] ?? false;
        $record = $this->getDraftRecord($draft, $isnew);

        if (!$draft->name && $draft->id) {
            $totalDrafts = (int) NavigationDraftRecord::find()
                ->andwhere(['navId' => $draft->navId, 'site' => $draft->site])
                ->count();
            $draft->name = Translations::$plugin->translator->translate('app', 'Draft {num}', ['num' => $totalDrafts + 1]);
        }

        $record->navId = $draft->navId;
        $record->site = $draft->site;
        $record->name = $draft->name;
        $record->title = $draft->title;

        if ($isnew) {
            $record->canonicalId = $draft->canonicalId;
        }

        $data = ['fields' => []];
        $content = $content ?? Translations::$plugin->elementTranslator->toPostArray($draft);

        foreach ($draft->getFieldLayout()->getCustomFields() as $layoutField) {
            $field = Craft::$app->fields->getFieldById($layoutField->id);
            if ($field->getIsTranslatable($draft) || in_array(get_class($field), Constants::NESTED_FIELD_TYPES)) {
                if (isset($content[$field->handle])) {
                    $data['fields'][$field->id] = $content[$field->handle];
                }
            }
        }

        $record->data = json_encode($data);
        $transaction = Craft::$app->db->beginTransaction();

        try {
            if ($record->save(false)) {
                $transaction->commit();
                $draft->draftId = $record->id;
                return true;
            }
        } catch (Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        return false;
    }

    /**
     * Publish the navigation draft
     *
     * @param Node $draft
     * @return bool
     */
    public function publishDraft(Node $draft)
    {
        $node = $draft->createAnother();
        $success = Craft::$app->elements->saveElement($node, false);
        if (!$success) {
            Translations::$plugin->logHelper->log('[' . __METHOD__ . '] Couldn’t publish draft "' . $draft->title . '"', Constants::LOG_LEVEL_ERROR);
            return false;
        }
        Craft::$app->elements->deleteElement($node, false);

        return true;
    }

    /**
     * Create a new navigation draft
     *
     * @param Node $node
     * @param int $site
     * @param string $orderName
     * @param int $sourceSite
     * @return NavigationDraftModel
     */
    public function createDraft(Node $node, $site, $orderName, $sourceSite)
    {
        try {
            $draft = $this->makeNewDraft();
            $draft->name = sprintf('%s [%s]', $orderName, $site);
            $draft->id = $node->id;
            $draft->site = $site;
            $draft->title = $node->title;
            $draft->sourceSite = $sourceSite;
            $draft->navId = $node->navId;
            $draft->data = [];
            $draft->canonicalId = $node->canonicalId;
            $this->saveDraft($draft, ["isnew" => true]);
            return $draft;
        } catch (Exception $e) {
            Translations::$plugin->logHelper->log('[' . __METHOD__ . '] CreateDraft exception: ' . $e->getMessage(), Constants::LOG_LEVEL_ERROR);
            return null;
        }
    }

    /**
     * Delete a navigation draft
     *
     * @param Node $draft
     * @return bool
     * @throws Exception
     */
    public function deleteDraft(Node $draft)
    {
        $record = $this->getDraftRecord($draft);
        $transaction = Craft::$app->db->beginTransaction();

        try {
            if ($record->delete(false)) {
                $transaction->commit();
                return true;
            }
        } catch (Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        return false;
    }
}
