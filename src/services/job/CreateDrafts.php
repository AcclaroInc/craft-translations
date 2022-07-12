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
use acclaro\translations\Constants;
use acclaro\translations\Translations;

class CreateDrafts extends BaseJob
{

    public $orderId;
    public $wordCounts;
    public $publish;
    public $fileIds;

    public function execute($queue): void
    {
        Translations::$plugin->draftRepository->createOrderDrafts(
            $this->orderId, $this->wordCounts, $this->publish, $this->fileIds, $queue
        );
    }

    public function updateProgress($queue, $progress) {
        $queue->setProgress($progress);
    }

    protected function defaultDescription(): ?string
    {
        return $this->publish ? Constants::JOB_APPLYING_DRAFT : Constants::JOB_CREATING_DRAFT;
    }

}
