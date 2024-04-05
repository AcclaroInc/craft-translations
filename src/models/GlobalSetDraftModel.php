<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\models;

use craft\elements\GlobalSet;
use craft\behaviors\DraftBehavior;
use craft\validators\SiteIdValidator;
use craft\validators\DateTimeValidator;
use craft\behaviors\CustomFieldBehavior;
use craft\behaviors\FieldLayoutBehavior;

use acclaro\translations\Translations;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class GlobalSetDraftModel extends GlobalSet
{
    protected $_globalSet = null;

    public $globalSetId;

    public $site;

    public $data;

    /**
     * @param array $attributes
     */
    public function __construct(
        $attributes = []
    ) {
        parent::__construct($attributes);
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['name', 'globalSetId', 'site', 'data'], 'required'];
        $rules[] = ['site', SiteIdValidator::class];
        $rules[] = [['dateCreated', 'dateUpdated'], DateTimeValidator::class];
        $rules[] = ['enabled', 'default', 'value' => true];
        $rules[] = ['archived', 'default', 'value' => false];
        $rules[] = ['enabledForSite', 'default', 'value' => true];

        return $rules;
    }

    public function getFieldLayout(): \craft\models\FieldLayout
    {
        $globalSet = $this->getGlobalSet();

        return $globalSet->getFieldLayout();
    }

    public function getHandle()
    {
        return $this->getGlobalSet()->handle;
    }

    public function getGlobalSet()
    {
        if (is_null($this->globalSetId)) {
            $this->_globalSet = Translations::$plugin->globalSetRepository->getSetById($this->id); // this works for creating orders
        } else {
            $this->_globalSet = Translations::$plugin->globalSetRepository->getSetById($this->globalSetId); // this works for edit draft
        }

        return $this->_globalSet;
    }

    public function getUrl(): ?string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getCpEditUrl(): ?string
    {
        $globalSet = $this->getGlobalSet();

        $path = 'translations/globals/'.$globalSet->handle.'/drafts/'.$this->draftId;

        return Translations::$plugin->urlHelper->cpUrl($path);
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['customFields'] = [
            'class' => CustomFieldBehavior::class,
            'hasMethods' => false,
        ];
        $behaviors['fieldLayout'] = [
            'class' => FieldLayoutBehavior::class,
            'elementType' => GlobalSet::class,
        ];
        $behaviors['draft'] = [
            'class' => DraftBehavior::class,
            'creatorId' => 1,
            'draftName' => 'Global Draft',
            'draftNotes' => '',
            'trackChanges' => true,
        ];
        return $behaviors;
    }
}
