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
use Exception;

use craft\queue\BaseJob;
use craft\elements\Entry;
use acclaro\translations\Translations;
use acclaro\translations\services\job\UdpateReviewFileUrls;

class RegeneratePreviewUrls extends BaseJob
{
    public $order;

    public function execute($queue)
    {
        $totalElements = count($this->order->files);
        $currentElement = 0;

        foreach ($this->order->files as $file) {
            $this->setProgress($queue, $currentElement++ / $totalElements);
            $transaction = Craft::$app->getDb()->beginTransaction();

            try {
                $draft = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);

                if ($draft) {
                    $element = Craft::$app->getElements()->getElementById($file->elementId, null, $file->sourceSite);
                    $file->previewUrl = Translations::$plugin->urlGenerator->generateElementPreviewUrl($draft, $file->targetSite);
                    $file->source = Translations::$plugin->elementToXmlConverter->toXml(
                        $element,
                        $file->draftId,
                        $file->sourceSite,
                        $file->targetSite,
                        $file->previewUrl
                    );
                }

                Translations::$plugin->fileRepository->saveFile($file);
                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }
        }

        if ($this->order->translator->service !== 'export_import') {
            $translator = $this->order->getTranslator();

            $translationService = Translations::$plugin->translatorFactory->makeTranslationService($translator->service, $translator->getSettings());

            $translationService->udpateReviewFileUrls($this->order);
        }
    }

    protected function defaultDescription()
    {
        return 'Regenerating preview urls';
    }
}