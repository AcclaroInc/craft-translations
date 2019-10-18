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

class SyncOrder extends BaseJob
{
    public $order;

    public function execute($queue)
    {
        $totalElements = count($this->order->files);
        $currentElement = 0;

        $translationService = Translations::$plugin->translatorFactory->makeTranslationService($this->order->translator->service, $this->order->translator->getSettings());
    
        // Don't update manual orders
        if ($this->order->translator->service === 'export_import') {
            return;
        }

        $translationService->updateOrder($this->order);

        Translations::$plugin->orderRepository->saveOrder($this->order);

        foreach ($this->order->files as $file) {
            $this->setProgress($queue, $currentElement++ / $totalElements);
            // Let's make sure we're not updating published files
            if ($file->status == 'published' || $file->status == 'canceled') {
                continue;
            }

            $translationService->updateFile($this->order, $file);

            Translations::$plugin->fileRepository->saveFile($file);
        }
    }

    protected function defaultDescription()
    {
        return 'Syncing order '. $this->order->title;
    }
}