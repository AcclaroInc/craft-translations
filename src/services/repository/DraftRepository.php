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
use craft\db\Query;
use craft\elements\Entry;
use acclaro\translations\Translations;

class DraftRepository
{
    /**
     * @return \craft\elements\Entry|null
     */
    public function makeNewDraft($entry, $creatorId, $name, $notes, $newAttributes)
    {
        $draft = Craft::$app->getDrafts()->createDraft(
            $entry,
            $creatorId,
            $name,
            $notes,
            $newAttributes
        );

        $draft->setAttributes($newAttributes, false);

        return $draft;
    }
    
    public function getDraftById($draftId, $siteId)
    {
        $draft = Entry::find()
            ->draftId($draftId)
            ->siteId($siteId)
            ->anyStatus()
            ->one();

        return $draft;
    }

    public function saveDraft($element)
    {
        return Craft::$app->elements->saveElement($element);
    }
    
    public function publishDraft(Entry $draft)
    {
        // Let's save the draft before we pass it to applyDraft()
        Craft::$app->elements->saveElement($draft);

        return Craft::$app->getDrafts()->applyDraft($draft);
    }

    public function deleteAutoPropagatedDrafts($draftId, $targetSite)
    {
        if (empty($draftId) || empty($targetSite)) {
            return;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $query = (new Query())
                ->select('elements_sites.id')
                ->from(['{{%elements_sites}} elements_sites'])
                ->innerJoin('{{%elements}} elements', '[[elements.id]] = [[elements_sites.elementId]]')
                ->where(['elements.draftId' => $draftId,])
                ->andWhere(['!=', 'elements_sites.siteId', $targetSite])
                ->all();

            $propagatedElements = [];
            foreach ($query as $key => $id) {
                $propagatedElements[] = $id['id'];
            }
            $response = Craft::$app->db->createCommand()
                ->delete('{{%elements_sites}}', array('IN', 'id', $propagatedElements))
                ->execute();
            
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $response;
    }
}