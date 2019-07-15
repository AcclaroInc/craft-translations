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

use acclaro\translations\services\job\SendOrderToTranslationService;
use Craft;
use DateTime;
use Exception;
use craft\queue\BaseJob;
use yii\web\HttpException;
use craft\elements\GlobalSet;
use acclaro\translations\Translations;
use acclaro\translations\services\job\CreateOrderTranslationDrafts;

class OrderTranslationDrafts extends BaseJob
{

    public $mySetting;
    public $orderId;
    public $wordCounts;

    public function execute($queue)
    {

        Craft::info('OrderTranslationDrafts Execute Start!!');

        $order = Translations::$plugin->orderRepository->getOrderById($this->orderId);

        $drafts = Translations::$plugin->jobFactory->dispatchJob(CreateOrderTranslationDrafts::class, $order->getTargetSitesArray(), $order->getElements(), $order->title);

        $totalElements = count($drafts);
        $currentElement = 0;
        foreach ($drafts as $draft) {

            $this->setProgress($queue, $currentElement++ / $totalElements);

            $file = Translations::$plugin->fileRepository->makeNewFile();

            if ($draft instanceof GlobalSet) {
                $targetSite = $draft->site;
            } else {
                $targetSite = $draft->siteId;
            }

            try {

                //if (!$a++) throw new Exception('Custom exception!!');
                $element = Craft::$app->getElements()->getElementById($draft->id, null, $order->sourceSite);

                $file->orderId = $order->id;
                $file->elementId = $draft->id;
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

            } catch (Exception $e) {

                $file->orderId = $order->id;
                $file->elementId = $draft->id;
                $file->draftId = $draft->draftId;
                $file->sourceSite = $order->sourceSite;
                $file->targetSite = $targetSite;
                $file->status = 'failed';
                $file->wordCount = isset($this->wordCounts[$draft->id]) ? $this->wordCounts[$draft->id] : 0;

                Translations::$plugin->fileRepository->saveFile($file);
            }
        }

        // Only send order to translation service when not Manual
        if ($order->translator->service !== 'export_import') {
            Translations::$plugin->jobFactory->dispatchJob(SendOrderToTranslationService::class, $order);
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

        Craft::info('OrderJob Execute Ends Id :: '.$this->orderId);

    }

    protected function defaultDescription()
    {
        return 'Creating Translation Draft';
    }
}