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

use craft\queue\BaseJob;
use acclaro\translations\Translations;

class SyncOrder extends BaseJob
{
    public $order;

    public function execute($queue)
    {

        Translations::$plugin->orderRepository->syncOrder($this->order, $queue);
    }

    public function updateProgress($queue, $progress) {
        $queue->setProgress($progress);
    }

    protected function defaultDescription()
    {
        return 'Syncing order '. $this->order->title;
    }
}