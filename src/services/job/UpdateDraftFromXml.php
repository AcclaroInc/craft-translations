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
use craft\helpers\Path;
use yii\web\HttpException;
use craft\models\EntryDraft;
use craft\helpers\FileHelper;
use craft\helpers\ElementHelper;
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\TranslationsForCraft;
use acclaro\translationsforcraft\services\repository\SiteRepository;
use acclaro\translationsforcraft\models\GlobalSetDraftModel;

class UpdateDraftFromXml implements JobInterface
{
     /**
     * @var \craft\base\Element
     */
    protected $element;

    /**
     * @var \craft\models\EntryDraft|\acclaro\translationsforcraft\models\GlobalSetDraftModel
     */
    protected $draft;

    /**
     * @var string
     */
    protected $sourceSite;

    /**
     * @var string
     */
    protected $targetSite;

    /**
     * @var string
     */
    protected $xml;

    /**
     * @param \Craft\BaseElementModel                                               $element
     * @param \Craft\EntryDraftModel|\Craft\AcclaroTranslations_GlobalSetDraftModel $draft
     * @param string                                                                $xml
     * @param string                                                                $sourceSite
     * @param string                                                                $targetSite
     */
    public function __construct(
        Element $element,
        $draft,
        $xml,
        $sourceSite,
        $targetSite
    ) {
        $this->element = $element;

        $this->draft = $draft;

        $this->xml = $xml;

        $this->sourceSite = $sourceSite;

        $this->targetSite = $targetSite;
    }

    /**
     * {@inheritdoc}
     */
    public function handle()
    {
        $targetData = TranslationsForCraft::$plugin->elementTranslator->getTargetDataFromXml($this->xml);

        if ($this->draft instanceof EntryDraft) {
            if (isset($targetData['title'])) {
                $this->draft->title = $targetData['title'];
            }

            if (isset($targetData['slug'])) {
                $this->draft->slug = $targetData['slug'];
            }
        }

        $post = TranslationsForCraft::$plugin->elementTranslator->toPostArrayFromTranslationTarget($this->element, $this->sourceSite, $this->targetSite, $targetData);
        
        $this->draft->setFieldValues($post);
        
        $this->draft->siteId = $this->targetSite;

        // save the draft
        if ($this->draft instanceof EntryDraft) {
            TranslationsForCraft::$plugin->draftRepository->saveDraft($this->draft);
        } elseif ($this->draft instanceof GlobalSetDraftModel) {
            TranslationsForCraft::$plugin->globalSetDraftRepository->saveDraft($this->draft);
        }
    }
}