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
use DateTime;
use Exception;
use craft\db\Query;
use craft\elements\User;
use craft\elements\Entry;
use craft\elements\Category;
use craft\elements\GlobalSet;
use yii\web\NotFoundHttpException;
use acclaro\translations\Translations;
use craft\errors\InvalidElementException;
use acclaro\translations\models\FileModel;
use acclaro\translations\records\FileRecord;
use acclaro\translations\services\job\ApplyDrafts;
use acclaro\translations\services\job\CreateDrafts;

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

    public function isTranslationDraft($draftId, $elementId=null)
    {
        $data = [];

        $attributes = [
            'draftId' => (int) $draftId
        ];
        if ($elementId) {
            $attributes['elementId'] = $elementId;
        }

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
            // Translations::$plugin->draftRepository->deleteAutoPropagatedDrafts($file->draftId, $file->targetSite);

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

    public function createOrderDrafts($orderId, $wordCounts, $queue=null)
    {

        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        $elements = ($order->getElements() instanceof Element) ? $order->getElements()->all() : (array) $order->getElements();
        $totalElements = (count($elements) * count($order->getTargetSitesArray()));
        $currentElement = 0;

        $creatDrafts = new CreateDrafts();
        foreach ($order->getTargetSitesArray() as $key => $site) {
            foreach ($elements as $element) {

                if ($queue) {
                    $creatDrafts->updateProgress($queue, $currentElement++/$totalElements);
                }

                $this->createDrafts($element, $order, $site, $wordCounts);
            }
        }

        // Only send order to translation service when not Manual
        if ($order->translator->service !== 'export_import') {
            $translator = $order->getTranslator();

            $translationService = Translations::$plugin->translatorFactory->makeTranslationService($translator->service, $translator->getSettings());

            if ($queue) {
                $translationService->sendOrder($order);
            } else {
                Translations::$plugin->orderRepository->sendAcclaroOrder($order, $translator->getSettings());
            }
        } else {
            $order->status = 'in progress';
            $order->dateOrdered = new DateTime();
            //echo ' status '.$order->status; die;

            $success = Craft::$app->getElements()->saveElement($order);
            if (!$success) {
                Craft::info('Couldn’t save the order :: '.$orderId);
                Craft::error('Couldn’t save the order', __METHOD__);
            }
        }
    }

    public function createDrafts($element, $order, $site, $wordCounts, $file=null)
    {

        switch (get_class($element)) {
            case Entry::class:
                $draft = $this->createEntryDraft($element, $site, $order->title);
                break;
            case GlobalSet::class:
                $draft = $this->createGlobalSetDraft($element, $site, $order->title);
                break;
            case Category::class:
                $draft = $this->createCategoryDraft($element, $site, $order->title, $order->sourceSite);
                break;
        }

        if (!($file instanceof FileModel)){
            $file = Translations::$plugin->fileRepository->makeNewFile();
        }


        if (empty($draft)) {

            Craft::error('Empty draft found: Order'.json_decode($order), __METHOD__);
            return false;
        }
        if ($draft instanceof GlobalSet || $draft instanceof Category) {
            $targetSite = $draft->site;
        } else {
            $targetSite = $draft->siteId;
        }

        try {
            // Prevent duplicate files
            $isExistingFile = $this->isTranslationDraft($draft->draftId, $draft->sourceId);
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
                ($draft instanceof Entry) ? $draft : $element, // Send the element for custom drafts (GlobalSets, Categories)
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
            $draft->siteId = $site;

            $post = Translations::$plugin->elementTranslator->toPostArray($globalSet);

            $draft->setFieldValues($post);

            Translations::$plugin->globalSetDraftRepository->saveDraft($draft, $post);

            return $draft;
        } catch (Exception $e) {

            Craft::error('CreateGlobalSetDraft exception:: '.$e->getMessage());
            return [];
        }

    }

    public function createCategoryDraft(Category $category, $site, $orderName, $sourceSite)
    {
        try {
            $draft = Translations::$plugin->categoryDraftRepository->makeNewDraft();
            
            $draft->name = sprintf('%s [%s]', $orderName, $site);
            $draft->id = $category->id;
            $draft->title = $category->title;
            $draft->site = $site;
            $draft->siteId = $site;
            $draft->sourceSite = $sourceSite;

            $post = Translations::$plugin->elementTranslator->toPostArray($category);

            $draft->setFieldValues($post);
            
            
            Translations::$plugin->categoryDraftRepository->saveDraft($draft, $post);
            return $draft;
        } catch (Exception $e) {

            Craft::error('CreateCategoryDraft exception:: '.$e->getMessage());
            return [];
        }

    }

    /**
     * @param $orderId
     * @param $elementIds
     * @param null $queue
     * @throws NotFoundHttpException
     */
    public function applyDrafts($orderId, $elementIds, $queue=null)
    {

        $order = Translations::$plugin->orderRepository->getOrderById($orderId);
        $files = $order->getFiles();

        $filesCount = count($files);

        $totalElements = (count($elementIds) * count($order->getTargetSitesArray()));
        $currentElement = 0;
        $publishedFilesCount = 0;

        $applyDraft = new ApplyDrafts();

        foreach ($files as $file) {
            if (!in_array($file->elementId, $elementIds)) {
                continue;
            }

            if ($file->status !== 'complete') {
                continue;
            }

            if ($queue) {
                $applyDraft->updateProgress($queue, $currentElement++ / $totalElements);
            }

            $element = Craft::$app->getElements()->getElementById($file->elementId, null, $file->sourceSite);

            if ($element instanceof GlobalSet) {
                $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($file->draftId);

                // keep original global set name
                $draft->name = $element->name;

                if ($draft) {
                    $success = Translations::$plugin->globalSetDraftRepository->publishDraft($draft);
                } else {
                    $success = false;
                }

                $uri = Translations::$plugin->urlGenerator->generateFileUrl($element, $file);
            } else if ($element instanceof Category) {
                $draft = Translations::$plugin->categoryDraftRepository->getDraftById($file->draftId);

                // keep original category name
                $draft->name = $element->title;
                $draft->site = $file->targetSite;

                if ($draft) {
                    $success = Translations::$plugin->categoryDraftRepository->publishDraft($draft);
                } else {
                    $success = false;
                }

                $uri = Translations::$plugin->urlGenerator->generateFileUrl($element, $file);
            } else {
                $draft = $this->getDraftById($file->draftId, $file->targetSite);

                if ($draft) {
                    $success = $this->applyTranslationDraft($file->id);
                } else {
                    $success = false;
                }

                $uri = Translations::$plugin->urlGenerator->generateFileUrl($element, $file);
            }

            if ($success) {
                $oldTokenRoute = json_encode(array(
                    'action' => 'entries/view-shared-entry',
                    'params' => array(
                        'draftId' => $file->draftId,
                    ),
                ));

                $newTokenRoute = json_encode(array(
                    'action' => 'entries/view-shared-entry',
                    'params' => array(
                        'entryId' => $draft->id,
                        'locale' => $file->targetSite,
                    ),
                ));

                Craft::$app->db->createCommand()->update(
                    'tokens',
                    array('route' => $newTokenRoute),
                    'route = :oldTokenRoute',
                    array(':oldTokenRoute' => $oldTokenRoute)
                );
            } else {
                $order->logActivity(Translations::$plugin->translator->translate('app', 'Couldn’t apply draft for '. '"'. $element->title .'"'));
                Translations::$plugin->orderRepository->saveOrder($order);

                continue;
            }

            $file->draftId = 0;
            $file->status = 'published';
            $publishedFilesCount++;

            Translations::$plugin->fileRepository->saveFile($file);
        }

        if ($publishedFilesCount === $filesCount) {
            $order->status = 'published';

            $order->logActivity(Translations::$plugin->translator->translate('app', 'Drafts applied'));

            Translations::$plugin->orderRepository->saveOrder($order);
        }

    }
}
