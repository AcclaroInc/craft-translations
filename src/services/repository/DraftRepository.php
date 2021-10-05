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

use acclaro\translations\Constants;
use Craft;
use DateTime;
use Exception;
use craft\db\Query;
use craft\elements\User;
use craft\models\Section;
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
use craft\elements\Asset;

class DraftRepository
{
    private $creatorId;

    private $allSitesHandle = [];

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
        $element->validate();
        if($element->getErrors()){
            return $element->getErrors();
        }

        return Craft::$app->elements->saveElement($element, true, true, false);
    }
    
    public function publishDraft(Entry $draft)
    {
        // Let's save the draft before we pass it to publishDraft()
        Craft::$app->elements->saveElement($draft, true, true, false);

        return Craft::$app->getDrafts()->publishDraft($draft);
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

    /**
     * @param $fileId
     * @param  string  $file
     * @param  string  $draft
     * @return \craft\base\ElementInterface|null
     * @throws NotFoundHttpException
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \craft\errors\MissingComponentException
     * @throws \yii\base\Exception
     */
    public function applyTranslationDraft($fileId, $file='', $draft='')
    {
        if(!$file){
            $file = Translations::$plugin->fileRepository->getFileById($fileId);
        }

        // Get file's draft
        if(!$draft){
            $draft = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);
        }

        if (!$draft) {
            throw new NotFoundHttpException('Draft not found');
        }

        // this will add and enable any site missing in enabled sites of element only if section propagation
        $this->enableForAllSupportedSites($file);

        try {
            // Let's try saving the element prior to applying draft
            if (!Craft::$app->getElements()->saveElement($draft, true, true, false)) {
                throw new InvalidElementException($draft);
            }

            // Let's remove the auto-propagated drafts
            //Translations::$plugin->draftRepository->deleteAutoPropagatedDrafts($file->draftId, $file->targetSite);

            // Apply the draft to the entry
            $newEntry = Craft::$app->getDrafts()->publishDraft($draft);


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

    public function enableForAllSupportedSites($file)
    {
        $element = Craft::$app->getElements()->getElementById($file->elementId, null, $file->sourceSite);
        $element = $element->getIsDraft() ? $element->getCanonical() : $element;

        $supportedSites = array_column($element->getSupportedSites(), 'siteId');

        $enabledSites = (new Query())
                ->select('siteId')
                ->from('{{%elements_sites}}')
                ->where([
                                'enabled' => true,
                                'elementId' => $element->id
                        ])
                ->column();

        if ($element->getSection()->propagationMethod === Section::PROPAGATION_METHOD_CUSTOM && array_diff($supportedSites, $enabledSites)) {
            $missingSites = [];
            foreach ($supportedSites as $supportedSiteId) {
                $missingSites[$supportedSiteId] = true;
            }

            $element->setEnabledForSite($missingSites);

            Craft::$app->getElements()->saveElement($element);
        }
    }

    public function createOrderDrafts($orderId, $wordCounts, $queue=null, $publish = true, $elementIds = null, $fileIds = null)
    {
        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        $totalElements = (count($elementIds) * count($order->getTargetSitesArray()));
        $currentElement = 0;

        $createDrafts = new CreateDrafts();
        $creator = User::find()
                ->admin()
                ->orderBy(['elements.id' => SORT_ASC])
                ->one();

        $this->creatorId = $creator->id;

        $this->allSitesHandle = $this->getAllSitesHandle();
        
        $orderFiles = Translations::$plugin->fileRepository->getFilesByOrderId($order->id);

        foreach ($orderFiles as $file) {
            if (! in_array($file->id, $fileIds)) {
                continue;
            }

            if (! $file) {
                Craft::$app->getSession()->setError(Craft::t('app', 'File Not Found.'));
                continue;
            }

            $element = Craft::$app->getElements()->getElementById($file->elementId, null, $order->siteId);
            if ($queue) {
                $createDrafts->updateProgress($queue, $currentElement++/$totalElements);
            }

            if ($element->getIsDraft()) {
                $element = $element->getCanonical();
            }
            
            // Create draft only if not already exist
            if (! $file->draftId) {
                $this->createDrafts($element, $order, $file->targetSite, $wordCounts, $file);
            } else {
                $file->status = Constants::FILE_STATUS_COMPLETE;
                Translations::$plugin->fileRepository->saveFile($file);

            }

            try {
                $translation_service = $order->translator->service;
                if ($translation_service !== Constants::TRANSLATOR_DEFAULT) {
                    $translation_service = Constants::TRANSLATOR_DEFAULT;
                }

                //Translation Service
                $translationService = Translations::$plugin->translatorFactory
                    ->makeTranslationService($translation_service, $order->translator->getSettings());

                $translationService->updateIOFile($order, $file);
            } catch(Exception $e) {
                $order->logActivity(Translations::$plugin->translator->translate('app', 'Could not update draft Error: ' .$e->getMessage()));
            }
        }

        if ($publish) {
            $this->applyDrafts($order->id, $elementIds, $fileIds, $queue);
        } else {
            $order->status = Translations::$plugin->orderRepository->getNewStatus($order);
    
            $order->logActivity(Translations::$plugin->translator->translate('app', 'Drafts created'));
    
            Translations::$plugin->orderRepository->saveOrder($order);
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
            case Asset::class:
                $draft = Translations::$plugin->assetDraftRepository->createDraft($element, $site, $order->title, $order->sourceSite);
                break;
        }

        if (!($file instanceof FileModel)) {
            $file = Translations::$plugin->fileRepository->makeNewFile();
        }

        if (empty($draft)) {
            Craft::error(  '['. __METHOD__ .'] Empty draft found: Order'.json_decode($order), 'translations' );
            return false;
        }

        if ($draft instanceof GlobalSet || $draft instanceof Category || $draft instanceof Asset) {
            $targetSite = $draft->site;
        } else {
            $targetSite = $draft->siteId;
        }

        try {
            // Prevent duplicate files
            $isExistingFile = $this->isTranslationDraft($draft->draftId, $draft->getCanonicalId());
            if (!empty($isExistingFile)) {
                return;
            }

            // $element = Craft::$app->getElements()->getElementById($draft->getCanonicalId(), null, $order->sourceSite);

            $file->draftId = $draft->draftId;
            $file->previewUrl = Translations::$plugin->urlGenerator->generateElementPreviewUrl($draft, $targetSite);
            $file->status = Constants::FILE_STATUS_COMPLETE;
            
            Translations::$plugin->fileRepository->saveFile($file);
            
            // Delete draft elements that are automatically propagated for other sites
            // Translations::$plugin->draftRepository->deleteAutoPropagatedDrafts($file->draftId, $file->targetSite);
            
            return $file;
        } catch (Exception $e) {
            $order->logActivity(Translations::$plugin->translator->translate('app', 'Could not create draft Error: ' .$e->getMessage()));
            $file->orderId = $order->id;
            $file->elementId = $draft->getCanonicalId();
            $file->draftId = $draft->draftId;
            $file->sourceSite = $order->sourceSite;
            $file->targetSite = $targetSite;
            $file->status = 'canceled';
            $file->wordCount = isset($wordCounts[$draft->id]) ? $wordCounts[$draft->id] : 0;
            
            Translations::$plugin->fileRepository->saveFile($file);
            
            return false;
        }
    }

    public function createEntryDraft(Entry $entry, $site, $orderName)
    {

        try{
            $handle = isset($this->allSitesHandle[$site]) ? $this->allSitesHandle[$site] : "";
            $name = sprintf('%s [%s]', $orderName, $handle);
            $notes = '';
            $elementURI = Craft::$app->getElements()->getElementUriForSite($entry->id, $site);
            //$supportedSites = Translations::$plugin->entryRepository->getSupportedSites($entry);

            $newAttributes = [
                // 'enabledForSite' => in_array($site, $supportedSites),
                'siteId' => $site,
                'uri' => $elementURI,
            ];

            $draft = Translations::$plugin->draftRepository->makeNewDraft($entry, $this->creatorId, $name, $notes, $newAttributes);
            
            return $draft;
        } catch (Exception $e) {

            Craft::error( '['. __METHOD__ .'] CreateEntryDraft exception:: '.$e->getMessage(), 'translations' );
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

            Craft::error( '['. __METHOD__ .'] CreateGlobalSetDraft exception:: '.$e->getMessage(), 'translations' );
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

            Craft::error( '['. __METHOD__ .'] CreateCategoryDraft exception:: '.$e->getMessage(), 'translations');
            return [];
        }

    }

    /**
     * @param $orderId
     * @param $elementIds
     * @param null $queue
     * @throws NotFoundHttpException
     */
    public function applyDrafts($orderId, $elementIds, $fileIds, $queue=null)
    {
        $order = Translations::$plugin->orderRepository->getOrderById($orderId);
        $files = $order->getFiles();

        $totalElements = (count($elementIds) * count($order->getTargetSitesArray()));
        $currentElement = 0;

        $applyDraft = new ApplyDrafts();

        try {
            foreach ($files as $file) {
                if (
                    ! in_array($file->id, $fileIds) ||
                    $file->status !== Constants::FILE_STATUS_COMPLETE
                ) {
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
    
                    // $uri = Translations::$plugin->urlGenerator->generateFileUrl($element, $file);
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
    
                    // $uri = Translations::$plugin->urlGenerator->generateFileUrl($element, $file);
                } else if ($element instanceof Asset) {
                    $draft = Translations::$plugin->assetDraftRepository->getDraftById($file->draftId);
    
                    // keep original asset name
                    $draft->name = $element->title;
                    $draft->site = $file->targetSite;
    
                    if ($draft) {
                        $success = Translations::$plugin->assetDraftRepository->publishDraft($draft);
                    } else {
                        $success = false;
                    }
    
                    // $uri = Translations::$plugin->urlGenerator->generateFileUrl($element, $file);
                } else {
                    $draft = $this->getDraftById($file->draftId, $file->targetSite);
    
                    if ($draft) {
                        $success = $this->applyTranslationDraft($file->id, $file, $draft);
                    } else {
                        $success = false;
                    }
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
                $file->status = Constants::FILE_STATUS_PUBLISHED;

                Translations::$plugin->fileRepository->saveFile($file);
            }
        } catch(Exception $e) {
            $order->logActivity(Translations::$plugin->translator->translate('app', 'Could not publish draft Error: ' .$e->getMessage()));
            Craft::error('Could not publish draft Error: ' .$e->getMessage());
        }

        $order->status = Translations::$plugin->orderRepository->getNewStatus($order);

        $order->logActivity(Translations::$plugin->translator->translate('app', 'Drafts applied'));

        Translations::$plugin->orderRepository->saveOrder($order);
    }
    
    /**
     * getAllSitesHandle
     *
     * @return void
     */
    public function getAllSitesHandle()
    {
        $allSitesHandle = [];
        $allSites = Craft::$app->getSites()->getAllSites();
        
        foreach($allSites as $site)
        {
            $allSitesHandle[$site->id] = $site->handle;
        }

        return $allSitesHandle;
    }
}
