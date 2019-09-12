<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\job;

use Craft;
use DateTime;
use Exception;

use craft\base\Element;
use craft\elements\Entry;
use craft\elements\User;
use craft\queue\BaseJob;
use yii\web\HttpException;
use craft\elements\GlobalSet;
use acclaro\translations\Translations;

class CreateDrafts extends BaseJob
{

    public $mySetting;
    public $orderId;
    public $wordCounts;
    public $defaultCreator;

    public function execute($queue)
    {
        $order = Translations::$plugin->orderRepository->getOrderById($this->orderId);

        $elements = ($order->getElements() instanceof Element) ? $order->getElements()->all() : (array) $order->getElements();
        $totalElements = (count($elements) * count($order->getTargetSitesArray()));
        $currentElement = 0;
        $drafts = array();
        $this->defaultCreator = User::find()
            ->admin()
            ->orderBy(['elements.id' => SORT_ASC])
            ->one();

        foreach ($order->getTargetSitesArray() as $key => $site) {
            foreach ($elements as $element) {
                switch (get_class($element)) {
                    case Entry::class:
                        $draft = $this->createEntryDraft($element, $site, $order->title);
                        break;
                    case GlobalSet::class:
                        $draft = $this->createGlobalSetDraft($element, $site, $order->title);
                        break;
                }

                $this->setProgress($queue, $currentElement++ / $totalElements);

                $file = Translations::$plugin->fileRepository->makeNewFile();

                if ($draft instanceof GlobalSet) {
                    $targetSite = $draft->site;
                } else {
                    $targetSite = $draft->siteId;
                }

                try {

                    //if (!$a++) throw new Exception('Custom exception!!');
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
                    $file->wordCount = isset($this->wordCounts[$draft->id]) ? $this->wordCounts[$draft->id] : 0;

                    Translations::$plugin->fileRepository->saveFile($file);

                    // Delete draft elements that are automatically propagated for other sites
                    // Translations::$plugin->draftRepository->deleteAutoPropagatedDrafts($file->draftId, $file->targetSite);

                } catch (Exception $e) {

                    $file->orderId = $order->id;
                    $file->elementId = $draft->sourceId;
                    $file->draftId = $draft->draftId;
                    $file->sourceSite = $order->sourceSite;
                    $file->targetSite = $targetSite;
                    $file->status = 'failed';
                    $file->wordCount = isset($this->wordCounts[$draft->id]) ? $this->wordCounts[$draft->id] : 0;

                    Translations::$plugin->fileRepository->saveFile($file);
                }
            }
        }

        // Only send order to translation service when not Manual
        if ($order->translator->service !== 'export_import') {
            $translator = $order->getTranslator();

            $translationService = Translations::$plugin->translatorFactory->makeTranslationService($translator->service, $translator->getSettings());

            $translationService->sendOrder($order);
        } else {
            $order->status = 'in progress';
            $order->dateOrdered = new DateTime();
            //echo ' status '.$order->status; die;

            $success = Craft::$app->getElements()->saveElement($order);
            if (!$success) {
                Craft::info('Couldn’t save the order :: '.$this->orderId);
                Craft::error('Couldn’t save the order', __METHOD__);
            }
        }
    }

    protected function defaultDescription()
    {
        return 'Creating translation drafts';
    }

    public function createEntryDraft(Entry $entry, $site, $orderName)
    {

        try{
            $creatorId = $this->defaultCreator->id;

            $name = sprintf('%s [%s]', $orderName, Craft::$app->getSites()->getSiteById($site)->handle);

            $notes = '';

            $supportedSites = Translations::$plugin->entryRepository->getSupportedSites($entry);
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