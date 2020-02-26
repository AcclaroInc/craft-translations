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
use acclaro\translations\services\job\UdpateReviewFileUrls;

class RegeneratePreviewUrls extends BaseJob
{
    public $order;

    public function execute($queue)
    {

        Translations::$plugin->fileRepository->regeneratePreviewUrls($this->order, $queue);
    }

    public function updateProgress($queue, $progress) {
        $this->setProgress($queue, $progress);
    }
    
    protected function defaultDescription()
    {
        return 'Regenerating preview urls';
    }
}