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

class SyncOrders extends BaseJob
{
    public function execute($queue)
    {
        $orders = Translations::$plugin->orderRepository->getInProgressOrders();

        $totalElements = count($orders);
        $currentElement = 0;

        foreach ($orders as $order) {
            $translationService = Translations::$plugin->translatorFactory->makeTranslationService($order->translator->service, $order->translator->getSettings());
        
            // Don't update manual orders
            if ($order->translator->service === 'export_import') {
                return;
            }

            $translationService->updateOrder($order);

            Translations::$plugin->orderRepository->saveOrder($order);

            foreach ($order->files as $file) {

                // Let's make sure we're not updating published files
                if ($file->status == 'published') {
                    continue;
                }

                $translationService->updateFile($order, $file);

                Translations::$plugin->fileRepository->saveFile($file);
            }

            $this->setProgress($queue, $currentElement++ / $totalElements);
        }
    }

    protected function defaultDescription()
    {
        return 'Syncing translation orders';
    }
}