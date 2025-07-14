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

use acclaro\translations\Constants;
use acclaro\translations\Translations;
use craft\queue\BaseJob;

class SyncStaticTranslations extends BaseJob
{
	public function execute($queue): void
    {
        Translations::$plugin->staticTranslationsRepository->syncWithDB($queue);
    }

	public function updateProgress($queue, $progress) {
        $this->setProgress($queue, $progress);
    }

    protected function defaultDescription(): ?string
    {
        return Constants::JOB_SYNC_STATIC_TRANSLATIONS;
    }
}