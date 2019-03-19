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
use acclaro\translations\services\App;
use acclaro\translations\Translations;

class SyncOrders implements JobInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle()
    {
        $orders = Translations::$plugin->orderRepository->getInProgressOrders();

        foreach ($orders as $order) {
            Translations::$plugin->jobFactory->dispatchJob(SyncOrder::class, $order);
        }
    }
}