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

use craft\queue\BaseJob;
use yii\web\HttpException;
use acclaro\translations\Translations;

use craft\helpers\Path;
use craft\helpers\FileHelper;
use craft\helpers\ElementHelper;
use acclaro\translations\models\GlobalSetDraftModel;

class UpdateDraftFromXmlJob extends BaseJob
{

    public $element;
    public $draft;
    public $xml;
    public $sourceSite;
    public $targetSite;

    public function execute($queue)
    {

        Craft::info('UpdateDraftFromXmlJob Execute Start!!');

        $targetData = Translations::$plugin->elementTranslator->getTargetDataFromXml($this->xml);

        if ($this->draft instanceof EntryDraft) {
            if (isset($targetData['title'])) {
                $this->draft->title = $targetData['title'];
            }

            if (isset($targetData['slug'])) {
                $this->draft->slug = $targetData['slug'];
            }
        }

        $post = Translations::$plugin->elementTranslator->toPostArrayFromTranslationTarget($this->element, $this->sourceSite, $this->targetSite, $targetData);

        $this->draft->setFieldValues($post);

        $this->draft->siteId = $this->targetSite;

        // save the draft
        if ($this->draft instanceof EntryDraft) {
            Translations::$plugin->draftRepository->saveDraft($this->draft);
        } elseif ($this->draft instanceof GlobalSetDraftModel) {
            Translations::$plugin->globalSetDraftRepository->saveDraft($this->draft);
        }

        Craft::info('UpdateDraftFromXmlJob Execute Ends');

    }

    protected function defaultDescription()
    {
        return 'Updating Entry Drafts';
    }
}