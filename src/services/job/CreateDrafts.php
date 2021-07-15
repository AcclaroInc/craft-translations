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
use craft\elements\Entry;
use craft\queue\BaseJob;
use acclaro\translations\Translations;

class CreateDrafts extends BaseJob
{

    public $mySetting;
    public $orderId;
    public $wordCounts;
    public $defaultCreator;
    public $publish;
    public $elementIds;

    public function execute($queue)
    {
        if ($this->publish && $this->elementIds) {
            Translations::$plugin->draftRepository->createOrderDrafts(
                $this->orderId, $this->wordCounts, $queue, $this->publish, $this->elementIds
            );
        } else {
            Translations::$plugin->draftRepository->createOrderDrafts($this->orderId, $this->wordCounts, $queue);
        }
    }

    public function updateProgress($queue, $progress) {
        $queue->setProgress($progress);
    }

    protected function defaultDescription()
    {
        return 'Creating translation drafts';
    }

}