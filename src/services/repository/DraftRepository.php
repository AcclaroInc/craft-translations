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
use craft\elements\User;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use yii\web\NotFoundHttpException;
use acclaro\translations\Translations;
use craft\errors\InvalidElementException;
use acclaro\translations\models\FileModel;
use acclaro\translations\records\FileRecord;

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

    public function isTranslationDraft($draftId)
    {
        $data = [];

        $attributes = [
            'draftId' => (int) $draftId
        ];

        $record = FileRecord::findOne($attributes);

        if (!$record) {
            return $data;
        }
        
        $file = new FileModel($record->toArray([
            'id',
            'targetSite',
            'status'
        ]));

        if ($file) {
            $data = [
                'id' => $file->id,
                'targetSite' => $file->targetSite,
                'status' => $file->status,
            ];
        }

        return $data;
    }

    public function applyTranslationDraft($fileId)
    {
        $file = Translations::$plugin->fileRepository->getFileById($fileId);

        // Get file's draft
        $draft = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);

        if (!$draft) {
            throw new NotFoundHttpException('Draft not found');
        }

        try {
            // Let's try saving the element prior to applying draft
            if (!Craft::$app->getElements()->saveElement($draft)) {
                throw new InvalidElementException($draft);
            }

            // Let's remove the auto-propagated drafts
            Translations::$plugin->draftRepository->deleteAutoPropagatedDrafts($file->draftId, $file->targetSite);

            // Apply the draft to the entry
            $newEntry = Craft::$app->getDrafts()->applyDraft($draft);


        } catch (InvalidElementException $e) {
            Craft::$app->getSession()->setError(Craft::t('app', 'Couldn’t publish draft.'));
            // Send the draft back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'entry' => $draft
            ]);
            return null;
        }

        return $newEntry;
    }

    public function createDrafts($element, $order, $site, $wordCounts, $file=null) {

        switch (get_class($element)) {
            case Entry::class:
                $draft = $this->createEntryDraft($element, $site, $order->title);
                break;
            case GlobalSet::class:
                $draft = $this->createGlobalSetDraft($element, $site, $order->title);
                break;
        }

        if (!($file instanceof FileModel)){
            $file = Translations::$plugin->fileRepository->makeNewFile();
        }

        if ($draft instanceof GlobalSet) {
            $targetSite = $draft->site;
        } else {
            $targetSite = $draft->siteId;
        }

        try {
            // Prevent duplicate files
            $isExistingFile = $this->isTranslationDraft($draft->draftId);
            if (!empty($isExistingFile)) {
                return;
            }

            $element = Craft::$app->getElements()->getElementById($draft->sourceId, null, $order->sourceSite);

            $file->orderId = $order->id;
            $file->elementId = $draft->sourceId;
            $file->draftId = $draft->draftId;
            $file->sourceSite = $order->sourceSite;
            $file->targetSite = $targetSite;
            $file->previewUrl = Translations::$plugin->urlGenerator->generateElementPreviewUrl($draft, $targetSite);
            $file->source = Translations::$plugin->elementToXmlConverter->toXml(
                $element,
                $draft->draftId,
                $order->sourceSite,
                $targetSite,
                $file->previewUrl
            );
            $file->wordCount = isset($wordCounts[$element->id]) ? $wordCounts[$element->id] : 0;

            Translations::$plugin->fileRepository->saveFile($file);

            // Delete draft elements that are automatically propagated for other sites
            // Translations::$plugin->draftRepository->deleteAutoPropagatedDrafts($file->draftId, $file->targetSite);

            return $file;
        } catch (Exception $e) {

            $file->orderId = $order->id;
            $file->elementId = $draft->sourceId;
            $file->draftId = $draft->draftId;
            $file->sourceSite = $order->sourceSite;
            $file->targetSite = $targetSite;
            $file->status = 'failed';
            $file->wordCount = isset($wordCounts[$draft->id]) ? $wordCounts[$draft->id] : 0;

            Translations::$plugin->fileRepository->saveFile($file);

            return false;
        }

    }

    public function createEntryDraft(Entry $entry, $site, $orderName)
    {

        try{
            $creator = User::find()
                ->admin()
                ->orderBy(['elements.id' => SORT_ASC])
                ->one();
            $creatorId = $creator->id;

            $name = sprintf('%s [%s]', $orderName, Craft::$app->getSites()->getSiteById($site)->handle);

            $notes = '';
            //$supportedSites = Translations::$plugin->entryRepository->getSupportedSites($entry);
            $newAttributes = [
                // 'enabledForSite' => in_array($site, $supportedSites),
                'siteId' => $site,
            ];

            $draft = Translations::$plugin->draftRepository->makeNewDraft($entry, $creatorId, $name, $notes, $newAttributes);

            return $draft;
        } catch (Exception $e) {

            Craft::error('CreateEntryDraft exception:: '.$e->getMessage());
            return [];
        }

    }

    public function createGlobalSetDraft(GlobalSet $globalSet, $site, $orderName)
    {
        try {
            $draft = Translations::$plugin->globalSetDraftRepository->makeNewDraft();
            $draft->name = sprintf('%s [%s]', $orderName, $site);
            $draft->id = $globalSet->id;
            $draft->site = $site;

            $post = Translations::$plugin->elementTranslator->toPostArray($globalSet);

            $draft->setFieldValues($post);

            Translations::$plugin->globalSetDraftRepository->saveDraft($draft);

            return $draft;
        } catch (Exception $e) {

            Craft::error('CreateGlobalSetDraft exception:: '.$e->getMessage());
            return [];
        }

    }
}