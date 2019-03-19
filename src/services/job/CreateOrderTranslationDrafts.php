<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\services\job;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\models\EntryDraft;
use craft\elements\GlobalSet;
use craft\elements\db\ElementQuery;
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\elements\Order;
use acclaro\translationsforcraft\TranslationsForCraft;
use acclaro\translationsforcraft\models\GlobalSetDraftModel;

class CreateOrderTranslationDrafts implements JobInterface
{
    /**
     * @var array string
     */
    protected $targetSites;

    /**
     * @var array \craft\base\Element
     */
    protected $elements;

    /**
     * @var string
     */
    protected $orderName;
    
    /**
     * @var string
     */
    public $targetSite;

    /**
     * @param array                                $targetSites
     * @param craft\elements\db\ElementQuery       $elements
     * @param string                               $orderName
     */
    public function __construct(
        array $targetSites,
        $elements,
        $orderName,
        $targetSite = null
    ) {
        $this->targetSites = $targetSites;
        
        $this->elements = ($elements instanceof Element) ? $elements->all() : (array) $elements;
        
        $this->orderName = $orderName;
    }

    /**
     * {@inheritdoc}
     */
    public function handle()
    {
        $drafts = array();

        foreach ($this->targetSites as $key => $site) {
            foreach ($this->elements as $element) {
                switch (get_class($element)) {
                    case Entry::class:
                        $drafts[] = $this->createEntryDraft($element, $site);
                        break;
                    case GlobalSet::class:
                        $drafts[] = $this->createGlobalSetDraft($element, $site);
                        break;
                }
            }
        }

        return $drafts;
    }

    public function createEntryDraft(Entry $entry, $site)
    {
        $draftConfig = [
            'name' => sprintf('%s [%s]', $this->orderName, $site),
            'id' => $entry->id,
            'sectionId' => $entry->sectionId,
            'creatorId' => Craft::$app->session && Craft::$app->getUser() ? Craft::$app->getUser()->id : '1',
            'typeId' => $entry->typeId,
            'slug' => $entry->slug,
            'postDate' => $entry->postDate,
            'expiryDate' => $entry->expiryDate,
            'enabled' => $entry->enabled,
            'title' => $entry->title,
            'authorId' => $entry->authorId
        ];

        $supportedSites = TranslationsForCraft::$plugin->entryRepository->getSupportedSites($entry);

        $draftConfig['enabledForSite'] = in_array($site, $supportedSites);
        $draftConfig['siteId'] = $site;

        $draft = TranslationsForCraft::$plugin->draftRepository->makeNewDraft($draftConfig);

        TranslationsForCraft::$plugin->draftRepository->saveDraft($draft);

        return $draft;
    }

    public function createGlobalSetDraft(GlobalSet $globalSet, $site)
    {
        $draft = TranslationsForCraft::$plugin->globalSetDraftRepository->makeNewDraft();
        $draft->name = sprintf('%s [%s]', $this->orderName, $site);
        $draft->id = $globalSet->id;
        $draft->site = $site;

        $post = TranslationsForCraft::$plugin->elementTranslator->toPostArray($globalSet);

        $draft->setFieldValues($post);

        TranslationsForCraft::$plugin->globalSetDraftRepository->saveDraft($draft);

        return $draft;
    }
}