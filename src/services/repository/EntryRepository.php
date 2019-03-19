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
use craft\elements\Entry;
use craft\base\ElementInterface;
use acclaro\translations\Translations;

class EntryRepository
{
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
            Craft::error('Couldn’t save the entry "'.$entry->title.'"', __METHOD__);
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
}