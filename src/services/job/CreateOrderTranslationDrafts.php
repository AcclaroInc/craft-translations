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
use craft\base\Element;
use craft\elements\Entry;
use craft\models\EntryDraft;
use craft\elements\GlobalSet;
use craft\elements\db\ElementQuery;
use acclaro\translations\services\App;
use acclaro\translations\elements\Order;
use acclaro\translations\Translations;
use acclaro\translations\models\GlobalSetDraftModel;

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
                var_dump(get_class($element));
                switch (get_class($element)) {
                    case Entry::class:
                        $drafts[] = $this->createEntryDraft($element, $site);
                        break;
                    case EntryDraft::class:
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

        $supportedSites = Translations::$plugin->entryRepository->getSupportedSites($entry);

        $draftConfig['enabledForSite'] = in_array($site, $supportedSites);
        $draftConfig['siteId'] = $site;

        $draft = Translations::$plugin->draftRepository->makeNewDraft($draftConfig);

        Translations::$plugin->draftRepository->saveDraft($draft);

        return $draft;
    }

    public function createGlobalSetDraft(GlobalSet $globalSet, $site)
    {
        $draft = Translations::$plugin->globalSetDraftRepository->makeNewDraft();
        $draft->name = sprintf('%s [%s]', $this->orderName, $site);
        $draft->id = $globalSet->id;
        $draft->site = $site;

        $post = Translations::$plugin->elementTranslator->toPostArray($globalSet);

        $draft->setFieldValues($post);

        Translations::$plugin->globalSetDraftRepository->saveDraft($draft);

        return $draft;
    }
}