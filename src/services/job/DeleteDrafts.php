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

class DeleteDrafts extends BaseJob
{
    public $drafts;

    public function execute($queue)
    {
        $totalElements = count($this->drafts);
        $currentElement = 0;

        foreach ($this->drafts as $id) {
            $this->setProgress($queue, $currentElement++ / $totalElements);
            $elementsService = Craft::$app->getElements();
            $transaction = Craft::$app->getDb()->beginTransaction();

            try {
                $draft = Entry::find()
                            ->draftId($id)
                            ->anyStatus()
                            ->siteId('*')
                            ->one();

                $elementsService->deleteElement($draft, true);
                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }
        }
    }

    protected function defaultDescription()
    {
        return 'Deleting Translation Drafts';
    }
}