<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\job\acclaro;

use Craft;
use Exception;

use craft\queue\BaseJob;
use acclaro\translations\Translations;

class SendOrder extends BaseJob
{
    public $order;
    public $sandboxMode;
    public $settings;

    public function execute($queue)
    {

        Translations::$plugin->orderRepository->sendAcclaroOrder($this->order, $this->settings, $queue);
    }

    public function updateProgress($queue, $progress) {
        $this->setProgress($queue, $progress);
    }

    protected function defaultDescription()
    {
        return 'Sending order to Acclaro';
    }
}