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

use acclaro\translations\Constants;
use Craft;
use craft\elements\User;
use craft\base\Component;
use craft\services\Drafts;
use craft\events\DraftEvent;
use craft\behaviors\DraftBehavior;
use acclaro\translations\Translations;
use craft\base\ElementInterface;

class EntryRepository extends Component
{
    public function createDraft(ElementInterface $entry, $site, $orderName)
    {
        $allSitesHandle = Translations::$plugin->siteRepository->getAllSitesHandle();

        try{
            $handle = isset($allSitesHandle[$site]) ? $allSitesHandle[$site] : "";
            $name = sprintf('%s [%s]', $orderName, $handle);
            $notes = '';
            $creator = User::find()
                ->admin()
                ->orderBy(['elements.id' => SORT_ASC])
                ->one();
            $elementURI = Craft::$app->getElements()->getElementUriForSite($entry->id, $site);

            $newAttributes = [
                'siteId' => $site,
                'uri' => $elementURI,
            ];

            $draft = $this->makeNewDraft($entry, $creator->id, $name, $notes, $newAttributes);

            return $draft;
        } catch (\Exception $e) {
            Translations::$plugin->logHelper->log( '['. __METHOD__ .'] CreateDraft exception:: '.$e->getMessage(), Constants::LOG_LEVEL_ERROR );
            return [];
        }
    }

	private function makeNewDraft($canonical, $creatorId, $name, $notes, $newAttributes, $provisional = false)
	{
		$canonical = $canonical->getIsDraft() ? $canonical->getCanonical() : $canonical;
		// Fire a 'beforeCreateDraft' event
        $event = new DraftEvent([
            'canonical' => $canonical,
            'creatorId' => $creatorId,
            'provisional' => $provisional,
            'draftName' => $name,
            'draftNotes' => $notes,
        ]);
        $this->trigger('beforeCreateDraft', $event);
        $name = $event->draftName;
        $notes = $event->draftNotes;

		$transaction = Craft::$app->getDb()->beginTransaction();
        try {
            // Create the draft row
            $draftId = (new Drafts())->insertDraftRow($name, $notes, $creatorId, $canonical->id, $canonical::trackChanges(), $provisional);

            // Duplicate the element
            $newAttributes['isProvisionalDraft'] = $provisional;
            $newAttributes['canonicalId'] = $canonical->id;
            $newAttributes['draftId'] = $draftId;
            $newAttributes['behaviors']['draft'] = [
                'class' => DraftBehavior::class,
                'creatorId' => $creatorId,
                'draftName' => $name,
                'draftNotes' => $notes,
                'trackChanges' => $canonical::trackChanges(),
            ];

            $draft = Craft::$app->getElements()->duplicateElement($canonical, $newAttributes);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Fire an 'afterCreateDraft' event
        if ($this->hasEventHandlers('afterCreateDraft')) {
            $this->trigger('afterCreateDraft', new DraftEvent([
                'canonical' => $canonical,
                'creatorId' => $creatorId,
                'provisional' => $provisional,
                'draftName' => $name,
                'draftNotes' => $notes,
                'draft' => $draft,
            ]));
        }

        return $draft;
	}
}
