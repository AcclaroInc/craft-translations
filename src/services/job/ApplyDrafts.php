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
use craft\elements\Entry;
use craft\queue\BaseJob;
use acclaro\translations\Translations;

class ApplyDrafts extends BaseJob
{
    public $orderId;
    public $elementIds;

    public function execute($queue)
    {

        Translations::$plugin->draftRepository->applyDrafts($this->orderId, $this->elementIds, $queue);
    }

    protected function defaultDescription()
    {
        return 'Applying translation drafts';
    }
}