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
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use yii\web\NotFoundHttpException;
use craft\commerce\elements\Product;
use craft\errors\InvalidElementException;

use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\base\AlertsTrait;
use acclaro\translations\models\FileModel;
use acclaro\translations\records\FileRecord;
use acclaro\translations\services\job\ApplyDrafts;
use acclaro\translations\services\job\CreateDrafts;
use verbb\navigation\elements\Node;

class DraftRepository
{
    use AlertsTrait;

    public function getDraftById($draftId, $siteId)
    {
        $draft = Entry::find()
            ->draftId($draftId)
            ->siteId($siteId)
            ->status(null)
            ->one();

        return $draft;
    }

    public function saveDraft($element)
    {
        $element->validate();
        if($element->getErrors()) {
            // Extract the error messages from the element's errors
            $errorMessages = $this->extractErrorMessages($element->getErrors());
            throw new Exception("Validation failed: " . $errorMessages);
        }

        return Craft::$app->elements->saveElement($element, true, true, false);
    }

    public function publishDraft($element, FileModel $file, $draft)
    {
        $success = null;

        if ($draft) {
            switch (get_class($element)) {
                case Asset::class:
                    $assetDraftRepo = Translations::$plugin->assetDraftRepository;

                    // keep original asset name
                    $draft->name = $element->title;
                    $draft->site = $file->targetSite;

                    $success = $assetDraftRepo->publishDraft($draft);

                    if ($success) {
                        $assetDraftRepo->deleteDraft($draft);
                    }
                    break;
                case GlobalSet::class:
                    $globalSetDraftRepo = Translations::$plugin->globalSetDraftRepository;

                    // keep original global set name
                    $draft->name = $element->name;
                    $success = $globalSetDraftRepo->publishDraft($draft);

                    if ($success) {
                        $globalSetDraftRepo->deleteDraft($draft);
                    }
                    break;
                case Node::class:
                    $navRepository = Translations::$plugin->navigationDraftRepository;
                    $success = $navRepository->publishDraft($draft);

                    if ($success) {
                        $navRepository->deleteDraft($draft);
                    }
                    break;
                default:
                    $success = $this->applyTranslationDraft($file->id, $file, $draft);
            }
        }

        return $success;
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
            $draft = $file->hasDraft();
        }

        if (!$draft) {
            throw new NotFoundHttpException('Draft not found');
        }

        try {
            // Let's try saving the element prior to applying draft
            if (!Craft::$app->getElements()->saveElement($draft)) {
                throw new InvalidElementException($draft);
            }

            // Apply the draft to the entry
            $newEntry = Craft::$app->getDrafts()->applyDraft($draft);
        } catch (InvalidElementException $e) {
            $this->setError('Couldn’t publish draft.');
            // Send the draft back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'entry' => $draft
            ]);
            return null;
        }

        return $newEntry;
    }

    public function createOrderDrafts($orderId, $wordCounts, $publish, $fileIds, $queue=null)
    {
        $isNewDraft = false;
        $draftsDeleted = false;
        $recreatedDraftFileIds = [];
        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        $totalElements = count($order->getFiles());
        $currentElement = 0;

        $createDrafts = new CreateDrafts();
        
        $fileDraftMap = $this->_getFileDraftMap($order); 
        foreach ($order->getFiles() as $file) {
            if (! in_array($file->id, $fileIds)) {
                continue;
            }
            $draftContent = $fileDraftMap[$file->id]['content'] ?? null; // stores the content of the draft temporarily
            unset($fileDraftMap[$file->id]);

            // Delete other drafts only if there is more than one fileId and drafts already exists
            if ($publish && !$draftsDeleted && (count($fileIds) > 1 || count($fileDraftMap) > 1)) {
                $deleted = $this->_deleteOtherDrafts($fileDraftMap, $file->id);
                $draftsDeleted = true;
                if (!empty($deleted)) {
                    $recreatedDraftFileIds = array_merge($recreatedDraftFileIds, $deleted);
                }
            }

            try {
                /* Create transaction per file so that in case a file has validation error
                only that will be rolledback and others can be processed */
                $transaction = Craft::$app->db->getTransaction() === null ? Craft::$app->db->beginTransaction() : null;
    
                if ($queue) {
                    $createDrafts->updateProgress($queue, $currentElement++/$totalElements);
                }

                $isRecreate = in_array($file->id, $recreatedDraftFileIds, true);

                $isNewDraft = $this->processFileDraft(
                    $file,
                    $order,
                    $wordCounts,
                    $draftContent,
                    $publish,
                    $queue,
                    $isRecreate
                );

                if ($transaction !== null) {
                    $transaction->commit();
                }
            } catch(Exception $e) {
                if ($transaction !== null) {
                    $transaction->rollBack();
                }
                $this->setError($e->getMessage());
                continue;
            }
        }

        /**
         *  Recreate drafts for deleted but unprocessed files
        */
        foreach ($fileDraftMap as $remainingFileId => $draftMap) {
            $file = Translations::$plugin->fileRepository->getFileById($remainingFileId);
            $isNewDraft = $this->processFileDraft(
                $file,
                $order,
                $wordCounts,
                $draftMap['content'],
                false
            );
        }

        if ($isNewDraft)
			$order->logActivity(Translations::$plugin->translator->translate('app', 'Drafts created'));

		$newStatus = Translations::$plugin->orderRepository->getNewStatus($order);
		if ($order->status != $newStatus) {
			$order->status = $newStatus;
			$order->logActivity(sprintf('Order status changed to \'%s\'', $order->getStatusLabel()));
		}

        Translations::$plugin->orderRepository->saveOrder($order);
        Translations::$plugin->cacheHelper->invalidateCache(Constants::CACHE_RESET_ORDER_CHANGES);
    }

    /**
     * Deletes old drafts to resolve an issue with non-localized matrix blocks containing localized fields.
     * 
     * When merging drafts while one or more drafts already exist, content may be lost 
     * both in the parent site and in all target sites except the last one.  
     * 
     * To prevent this, we delete existing drafts first. This mimics the behavior of 
     * creating drafts from scratch, ensuring the merge process runs without content loss.
     *
     * @param array $fileDraftMap
     * @param int $currentFileId
     */
    private function _deleteOtherDrafts(array $fileDraftMap, int $currentFileId): array
    {
        $deletedFileIds = [];
        Translations::$suppressDraftDeleteLog = true;
        try {
            foreach ($fileDraftMap as $fileId => $draftMap) {
                if ((int)$fileId !== (int)$currentFileId) {
                    $existingFile = Translations::$plugin->fileRepository->getFileById($fileId);
                    $this->deleteDraft($draftMap['draft_id'], $existingFile->targetSite);
                    $existingFile->status = Constants::FILE_STATUS_REVIEW_READY;
                    $existingFile->draftId = 0;
                    Translations::$plugin->fileRepository->saveFile($existingFile);
                    $deletedFileIds[] = (int)$fileId;
                }
            }
        } finally {
            Translations::$suppressDraftDeleteLog = false;
        }
        return $deletedFileIds;
    }

    /**
     * Gets an array of file id vs draft id mapping for the given order and files
     * @param OrderModel $order
     * @param array $fileIds
     * @return array
     */
    private function _getFileDraftMap($order)
    {
        $fileDraftMap = [];
        foreach ($order->getFiles() as $file) {
            $draft = $file->hasDraft();
            if ($draft) {
                $fileDraftMap[$file->id] = [
                    'draft_id' => $draft->draftId,
                    'content' => Translations::$plugin->elementToFileConverter->convert(
                        $draft,
                        Constants::FILE_FORMAT_XML,
                        [
                            'sourceSite'    => $order->sourceSite,
                            'targetSite'    => $file->targetSite,
                            'wordCount'     => Translations::$plugin->elementTranslator->getWordCount($draft),
                            'orderId'       => $order->id
                        ]
                    )
                ];
            }
        }

        return $fileDraftMap;
    }

    /**
    * Process a single file's draft creation/update logic.
    */
    private function processFileDraft($file, $order, $wordCounts, $draftContent, $publish, $queue=null, $isRecreate = true)
    {
        $isNewDraft = false;

        $element = Translations::$plugin->elementRepository->getElementById($file->elementId, $order->sourceSite);
        $currentFile = Translations::$plugin->fileRepository->getFileById($file->id);
        $isFileReady = $currentFile->isReviewReady();


        if ($file->hasDraft()) {
            $file->status = Constants::FILE_STATUS_COMPLETE;
            Translations::$plugin->fileRepository->saveFile($file);
        } else {
            $draftCreated = $this->createDrafts($element, $order, $file->targetSite, $wordCounts, $file);
            if ($draftCreated && !$isRecreate) {
                $isNewDraft = true;
            }
        }

        if ($isFileReady) {
            $translationService = $order->getTranslationService();
            $translationService->updateIOFile($order, $file, $draftContent);
            Translations::$plugin->fileRepository->saveFile($file);
        }

        /**
         * Updated applyDrafts logic to apply drafts individually v.s all at once
         * - https://github.com/AcclaroInc/pm-craft-translations/issues/388
         * - https://github.com/craftcms/cms/issues/9966
         * - https://github.com/AcclaroInc/craft-translations/pull/236/commits/813ef41548532ec41a5f53da6eb4194259f64071
        **/
        if ($publish) {
            $this->applyDrafts($order->id, [$element->id], [$file->id], $queue);
        }

        return $isNewDraft;
    }

    public function createDrafts($element, $order, $site, $wordCounts, $file=null)
    {
		$element = $element->getIsDraft() ? $element->getCanonical() : $element;

        try {
            switch (get_class($element)) {
                case Product::class:
                    $draft = Translations::$plugin->commerceRepository->createDraft($element, $site, $order->title);
                    break;
                case GlobalSet::class:
                    $draft = Translations::$plugin->globalSetDraftRepository->createDraft($element, $site, $order->title);
                    break;
                case Asset::class:
                    $draft = Translations::$plugin->assetDraftRepository->createDraft($element, $site, $order->title, $order->sourceSite);
                    break;
                case Node::class:
                    $draft = Translations::$plugin->navigationDraftRepository->createDraft($element, $site, $order->title, $order->sourceSite);
                    break;
                default:
                    $draft = Translations::$plugin->entryRepository->createDraft($element, $site, $order->title);
            }
        } catch(Exception $e) {
            throw $e;
        }

        if (!($file instanceof FileModel)) {
            $file = Translations::$plugin->fileRepository->getNewFile();
        }

        if (empty($draft)) {
            Translations::$plugin->logHelper->log(  '['. __METHOD__ .'] Empty draft found: Order'.json_decode($order), Constants::LOG_LEVEL_ERROR );
            throw new \Exception("Unable to create draft.");
        }

        if (!$file->hasPreview()) {
            $targetSite = $draft->site;
        } else {
            $targetSite = $draft->siteId;
        }

        try {
            $file->draftId = $draft->draftId;
            $file->previewUrl = Translations::$plugin->urlGenerator->generateElementPreviewUrl($draft, $targetSite);
            $file->status = Constants::FILE_STATUS_COMPLETE;

            Translations::$plugin->fileRepository->saveFile($file);

            return $draft;
        } catch (Exception $e) {
            $order->logActivity(Translations::$plugin->translator->translate('app', 'Could not create draft Error: ' .$e->getMessage()));
            $file->orderId = $order->id;
            $file->elementId = $draft->getCanonicalId();
            $file->draftId = $draft->draftId;
            $file->sourceSite = $order->sourceSite;
            $file->targetSite = $targetSite;
            $file->status = Constants::FILE_STATUS_CANCELED;
            $file->wordCount = isset($wordCounts[$draft->id]) ? $wordCounts[$draft->id] : 0;

            Translations::$plugin->fileRepository->saveFile($file);

            throw $e;
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

                $draft = $file->hasDraft();
                $element = $file->getElement();
                $success = $this->publishDraft($element, $file, $draft);

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
            Translations::$plugin->logHelper->log('Could not publish draft Error: ' .$e->getMessage(), Constants::LOG_LEVEL_ERROR);
        }

        $order->status = Translations::$plugin->orderRepository->getNewStatus($order);

        $order->logActivity(Translations::$plugin->translator->translate('app', 'Drafts applied'));

        Translations::$plugin->orderRepository->saveOrder($order);
    }

    /**
     * Delete a translation draft
     *
     * @param int|string $draftId
     *
     * @return void
     */
    public function deleteDraft($draftId, $siteId)
    {
        if (! $draftId) return;

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $draft = $this->getDraftById($draftId, $siteId);

            if ($draft) {
                Craft::$app->getElements()->deleteElement($draft, true);
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Extract error messages from an error object in string format
     */
    private function extractErrorMessages($errorObject)
    {
        $errorMessages = [];

        // Iterate through each key in the error object
        foreach ($errorObject as $field => $messages) {
            if (is_array($messages)) {
                // Iterate through each error message in the array
                foreach ($messages as $message) {
                    $errorMessages[] = $message; // Add the message to the list
                }
            }
        }

        // Return the concatenated error messages as a string
        return implode("\n", $errorMessages);
    }
}
