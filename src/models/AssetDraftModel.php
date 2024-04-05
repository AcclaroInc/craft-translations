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

use craft\elements\Asset;
use craft\behaviors\DraftBehavior;
use craft\validators\SiteIdValidator;
use acclaro\translations\Translations;
use craft\behaviors\FieldLayoutBehavior;
use craft\behaviors\CustomFieldBehavior;


/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class AssetDraftModel extends Asset
{
    protected $_asset = null;

    public $name;

    public $assetId;

    public $site;

    public $data;

    public $sourceSite;

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
        $rules[] = [['name', 'assetId', 'site', 'data'], 'required'];
        $rules[] = ['site', SiteIdValidator::class];

        return $rules;
    }

    public function getHandle()
    {
        return $this->getAsset()->handle;
    }

    public function getAsset()
    {
        if (is_null($this->assetId)) {
            $this->_asset = Translations::$plugin->assetDraftRepository->getAssetById($this->id); // this works for creating orders
        } else {
            $this->_asset = Translations::$plugin->assetDraftRepository->getAssetById($this->assetId); // this works for edit draft
        }

        return $this->_asset;
    }

    public function getUrl($transform = null, ?bool $generateNow = null): ?string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getCpEditUrl(): ?string
    {
        $asset = $this->getAsset();

        $path = 'translations/assets/'.$asset->id.'/drafts/'.$this->draftId;

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
            'elementType' => Asset::class,
        ];
        $behaviors['draft'] = [
            'class' => DraftBehavior::class,
            'creatorId' => 1,
            'draftName' => 'Asset Draft',
            'draftNotes' => '',
            'trackChanges' => true,
        ];
        return $behaviors;
    }
}
