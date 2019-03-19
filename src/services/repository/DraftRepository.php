<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\services\repository;

use Craft;
use Exception;
use craft\models\EntryDraft;
use craft\base\ElementInterface;
use acclaro\translationsforcraft\TranslationsForCraft;

class DraftRepository
{
    /**
     * @return \craft\models\EntryDraft|null
     */
    public function makeNewDraft($config)
    {
        return new EntryDraft($config);
    }
    
    public function getDraftById($draftId)
    {
        return Craft::$app->entryRevisions->getDraftById($draftId);
    }

    public function saveDraft(EntryDraft $draft)
    {
        return Craft::$app->entryRevisions->saveDraft($draft);
    }
    
    public function publishDraft(EntryDraft $draft)
    {
        return Craft::$app->entryRevisions->publishDraft($draft);
    }
}