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
use craft\validators\NumberValidator;
use craft\validators\SiteIdValidator;
use craft\validators\StringValidator;
use craft\validators\DateTimeValidator;
use acclaro\translations\services\App;
use acclaro\translations\Translations;
use craft\behaviors\FieldLayoutBehavior;
use craft\behaviors\CustomFieldBehavior;
use acclaro\translations\records\AssetDraftRecord;

use Craft;
use craft\base\Model;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class AssetDraftModel extends Asset
{
    protected $_asset = null;

    public $id;

    public $draftId;

    public $name;

    public $assetId;

    public $site;

    public $data;
    
    public $sourceSite;

    /**
     * @param array|null    $attributes
     */
    public function __construct(
        $attributes = null
    ) {
        parent::__construct($attributes);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [['name', 'assetId', 'site', 'data'], 'required'];
        $rules[] = ['site', SiteIdValidator::class];

        return $rules;
    }

    public function getFieldLayout()
    {
        
        return parent::getFieldLayout();
    }

    public function getHandle()
    {
        return $this->getAsset()->handle;
    }

    public static function populateModel($attributes)
    {
        if ($attributes instanceof AssetDraftRecord) {
            $attributes = $attributes->getAttributes();
        }

        $assetData = json_decode($attributes['data'], true);
        $fieldContent = isset($assetData['fields']) ? $assetData['fields'] : null;
        $attributes['id'] = $attributes['assetId'];
        
        $attributes = array_diff_key($attributes, array_flip(array('data', 'fields', 'assetId')));
        
        $attributes = array_merge($attributes, $assetData);
        
        $draft = parent::setAttributes($attributes);

        if ($fieldContent) {
            $post = array();

            foreach ($fieldContent as $fieldId => $fieldValue) {
                $field = Craft::$app->fields->getFieldById($fieldId);

                if ($field) {
                    $post[$field->handle] = $fieldValue;
                }
            }

            $draft->setFieldValues($post);
        }

        return $draft;
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

    /**
     * {@inheritdoc}
     */
    public function getCpEditUrl()
    {
        $asset = $this->getAsset();

        $catUrl = $asset->id . ($asset->slug ? '-' . $asset->slug : '');
        $path = 'translations/assets/'.$asset->getGroup()->handle.'/'.$catUrl.'/drafts/'.$this->draftId;
        
        return Translations::$plugin->urlHelper->cpUrl($path);
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
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
