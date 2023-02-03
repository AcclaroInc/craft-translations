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
        if($element->getErrors()){
            return $element->getErrors();
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
                case Product::class:
                    $commerceRepository = Translations::$plugin->commerceRepository;
                    $success = $commerceRepository->publishDraft($draft);

                    if ($success) {
                        $commerceRepository->deleteDraft($draft);
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
            if (!Craft::$app->getElements()->saveElement($draft, true, true, false)) {
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
        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        $totalElements = count($order->getFiles());
        $currentElement = 0;

        $createDrafts = new CreateDrafts();
        $transaction = Craft::$app->db->beginTransaction();

        foreach ($order->getFiles() as $file) {
            if (! in_array($file->id, $fileIds)) {
                continue;
            }

            $element = Translations::$plugin->elementRepository->getElementById($file->elementId, $order->sourceSite);
            $isFileReady = $file->isReviewReady();

            if ($queue) {
                $createDrafts->updateProgress($queue, $currentElement++/$totalElements);
            }

            // Create draft only if not already exist
            if ($file->hasDraft()) {
                $file->status = Constants::FILE_STATUS_COMPLETE;
                Translations::$plugin->fileRepository->saveFile($file);
            } else {
                $isNewDraft = $this->createDrafts($element, $order, $file->targetSite, $wordCounts, $file);
            }

            try {
                if ($isFileReady) {
                    // Translation Service Always Local
                    $translationService = $order->getTranslationService();

                    $translationService->updateIOFile($order, $file);

                    $file->reference = null;

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
            } catch(Exception $e) {
                $transaction->rollback();
                throw $e;
            }
        }
        $transaction->commit();

        if ($isNewDraft)
			$order->logActivity(Translations::$plugin->translator->translate('app', 'Drafts created'));

		$newStatus = Translations::$plugin->orderRepository->getNewStatus($order);
		if ($order->status != $newStatus) {
			$order->status = $newStatus;
			$order->logActivity(sprintf('Order status changed to \'%s\'', $order->getStatusLabel()));
		}

        Translations::$plugin->orderRepository->saveOrder($order);
    }

    public function createDrafts($element, $order, $site, $wordCounts, $file=null)
    {
		$element = $element->getIsDraft() ? $element->getCanonical() : $element;

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
            default:
                $draft = Translations::$plugin->entryRepository->createDraft($element, $site, $order->title);
        }

        if (!($file instanceof FileModel)) {
            $file = Translations::$plugin->fileRepository->makeNewFile();
        }

        if (empty($draft)) {
            Translations::$plugin->logHelper->log(  '['. __METHOD__ .'] Empty draft found: Order'.json_decode($order), Constants::LOG_LEVEL_ERROR );
            return false;
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

            return false;
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
}
