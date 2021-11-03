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
use craft\elements\User;
use craft\elements\Entry;
use acclaro\translations\Translations;

class EntryRepository
{
    private $allSitesHandle = [];

    /**
     * @param  int|string $orderId
     * @return \craft\elements\Entry|null
     */
    public function makeNewEntry()
    {
        return new Entry();
    }
    
    /**
     * @param  int|string $orderId
     * @return \craft\elements\Entry
     */
    public function getEntryById($entryId, $siteId)
    {
        return Entry::$app->entries->getEntryById($entryId, $siteId);
    }

    /**
     * @return \craft\elements\Entry
     */
    public function getEntriesById($entryIds, $siteId)
    {
        $entries = Entry::find()->ids($entryIds)->siteId($siteId)->all();
        return $entries;
    }

    public function saveEntry(Entry $entry)
    {
        $success = Craft::$app->elements->saveElement($entry);
        if (!$success) {
            Craft::error( '['. __METHOD__ .'] Couldn’t save the entry "'.$entry->title.'"', 'translations' );
        }
    }

    public function getSupportedSites(Entry $entry): array
    {
        $section = $this->getSection($entry->sectionId);
        $sites = [];
        foreach ($section->getSiteSettings() as $siteSettings) {
            if ($section->propagateEntries || $siteSettings->siteId == $this->siteId) {
                $sites[] = [
                    'siteId' => $siteSettings->siteId,
                    'enabledByDefault' => $siteSettings->enabledByDefault
                ];
            }
        }
        return $sites;
    }

    public function getSection($id)
    {
        if ($id === null) {
            throw new InvalidConfigException('Entry is missing its section ID');
        }
        if (($section = Craft::$app->getSections()->getSectionById($id)) === null) {
            throw new InvalidConfigException('Invalid section ID: ' . $id);
        }
        return $section;
    }

    public function createDraft(Entry $entry, $site, $orderName)
    {
        $this->allSitesHandle = Translations::$plugin->siteRepository->getAllSitesHandle();

        try{
            $handle = isset($this->allSitesHandle[$site]) ? $this->allSitesHandle[$site] : "";
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

            $draft = Translations::$plugin->draftRepository->makeNewDraft($entry, $creator->id, $name, $notes, $newAttributes);
            
            return $draft;
        } catch (\Exception $e) {
            Craft::error( '['. __METHOD__ .'] CreateDraft exception:: '.$e->getMessage(), 'translations' );
            return [];
        }

    }
}