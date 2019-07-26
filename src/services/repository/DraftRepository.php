<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\repository;

use Craft;
use Exception;
use craft\elements\Entry;
use craft\base\ElementInterface;
use acclaro\translations\Translations;

class DraftRepository
{
    /**
     * @return \craft\elements\Entry|null
     */
    public function makeNewDraft($entry, $creatorId, $name, $notes, $newAttributes)
    {
        $draft = Craft::$app->drafts->createDraft(
            $entry,
            $creatorId,
            $name,
            $notes,
            $newAttributes
        );

        $draft->setAttributes($newAttributes, false);

        return $draft;
    }
    
    public function getDraftById($draftId)
    {
        return Craft::$app->elements->getElementById($draftId);
    }

    public function saveDraft($element, $creatorId, $name, $notes)
    {
        return Craft::$app->drafts->saveElementAsDraft($element, $creatorId, $name, $notes);
    }
    
    public function publishDraft(Entry $draft)
    {
        return Craft::$app->entryRevisions->publishDraft($draft);
    }
}