<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\services\job;

use Craft;
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\TranslationsForCraft;

class SyncOrders implements JobInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle()
    {
        $orders = TranslationsForCraft::$plugin->orderRepository->getInProgressOrders();

        foreach ($orders as $order) {
            TranslationsForCraft::$plugin->jobFactory->dispatchJob(SyncOrder::class, $order);
        }
    }
}