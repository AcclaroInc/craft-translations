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
use craft\elements\Asset;
use craft\models\Section;
use craft\elements\Entry;
use craft\elements\Category;
use craft\elements\GlobalSet;
use yii\web\NotFoundHttpException;
use craft\errors\InvalidElementException;

use acclaro\translations\Constants;
use acclaro\translations\Translations;
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

    public function createOrderDrafts($orderId, $wordCounts, $publish, $fileIds, $queue=null)
    {
        $isNewDraft = false;
        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        $totalElements = count($order->getFiles());
        $currentElement = 0;

        $createDrafts = new CreateDrafts();

        foreach ($order->getFiles() as $file) {
            if (! in_array($file->id, $fileIds)) {
                continue;
            }

            $element = Craft::$app->getElements()->getElementById($file->elementId, null, $order->siteId);
            if ($queue) {
                $createDrafts->updateProgress($queue, $currentElement++/$totalElements);
            }

            // Create draft only if not already exist
            if (! $file->draftId) {
                $isNewDraft = true;
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
                $order->logActivity(Translations::$plugin->translator->translate('app', 'Could not update draft Error: ' .$e->getMessage()));
            }
        }

        $order->status = Translations::$plugin->orderRepository->getNewStatus($order);

        if ($isNewDraft)
            $order->logActivity(Translations::$plugin->translator->translate('app', 'Drafts created'));

        Translations::$plugin->orderRepository->saveOrder($order);
    }

    public function createDrafts($element, $order, $site, $wordCounts, $file=null)
    {
		$element = $element->getIsDraft() ? $element->getCanonical() : $element;
        switch (get_class($element)) {
            case Entry::class:
                $draft = Translations::$plugin->entryRepository->createDraft($element, $site, $order->title);
                break;
            case GlobalSet::class:
                $draft = Translations::$plugin->globalSetDraftRepository->createDraft($element, $site, $order->title);
                break;
            case Category::class:
                $draft = Translations::$plugin->categoryDraftRepository->createDraft($element, $site, $order->title, $order->sourceSite);
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
            $isExistingFile = $this->isTranslationDraft($draft->draftId);
            if (!empty($isExistingFile)) {
                return;
            }

            $file->draftId = $draft->draftId;
            $file->previewUrl = Translations::$plugin->urlGenerator->generateElementPreviewUrl($draft, $targetSite);
            $file->status = Constants::FILE_STATUS_COMPLETE;

            Translations::$plugin->fileRepository->saveFile($file);

            return $file;
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

                $element = Craft::$app->getElements()->getElementById($file->elementId, null, $file->sourceSite);

                if ($element instanceof GlobalSet) {
                    $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($file->draftId);

                    // keep original global set name
                    $draft->name = $element->name;

                    if ($draft) {
                        $success = Translations::$plugin->globalSetDraftRepository->publishDraft($draft);

                        if ($success) {
                            Translations::$plugin->globalSetDraftRepository->deleteDraft($draft);
                        }
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

                        if ($success) {
                            Translations::$plugin->categoryDraftRepository->deleteDraft($draft);
                        }
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

                        if ($success) {
                            Translations::$plugin->assetDraftRepository->deleteDraft($draft);
                        }
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
}
