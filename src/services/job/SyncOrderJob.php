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

class SyncOrderJob extends BaseJob
{
    public $orderId;
    public $title;
    public $files;

    public function execute($queue): void
    {
        $order = Translations::$plugin->orderRepository->getOrderById($this->orderId);
        $translationService = $order->getTranslationService();
        $this->title = $order->title;
        $translationService->syncOrder($order, $this->files, $queue);
    }

    public function updateProgress($queue, $progress) {
        $queue->setProgress($progress);
    }

    protected function defaultDescription(): ?string
    {
        return 'Syncing order '. $this->title;
    }
}