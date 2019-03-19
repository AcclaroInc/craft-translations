<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\models;

use craft\elements\GlobalSet;
use craft\validators\NumberValidator;
use craft\validators\SiteIdValidator;
use craft\validators\StringValidator;
use craft\validators\DateTimeValidator;
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\TranslationsForCraft;
use acclaro\translationsforcraft\records\GlobalSetDraftRecord;

use Craft;
use craft\base\Model;

/**
 * @author    Acclaro
 * @package   TranslationsForCraft
 * @since     1.0.0
 */
class GlobalSetDraftModel extends GlobalSet
{
    protected $_globalSet = null;

    public $id;

    public $draftId;

    public $name;

    public $globalSetId;

    public $site;

    public $data;

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
        $rules[] = [['name', 'globalSetId', 'site', 'data'], 'required'];
        $rules[] = ['site', SiteIdValidator::class];
        // $rules[] = ['wordCount', NumberValidator::class];
        $rules[] = [['dateCreated', 'dateUpdated'], DateTimeValidator::class];
        $rules[] = ['enabled', 'default', 'value' => true];
        $rules[] = ['archived', 'default', 'value' => false];
        // $rules[] = ['slug', 'default', StringValidator::class];
        // $rules[] = ['uri', 'default', StringValidator::class];
        $rules[] = ['enabledForSite', 'default', 'value' => true];

        return $rules;
    }

    public function getFieldLayout()
    {
        $globalSet = $this->getGlobalSet();
        
        return $globalSet->getFieldLayout();
    }

    public function getHandle()
    {
        return $this->getGlobalSet()->handle;
    }

    public static function populateModel($attributes)
    {
        if ($attributes instanceof GlobalSetDraftRecord) {
            $attributes = $attributes->getAttributes();
        }

        $globalSetData = json_decode($attributes['data'], true);
        $fieldContent = isset($globalSetData['fields']) ? $globalSetData['fields'] : null;
        // $attributes['draftId'] = $attributes['id'];
        $attributes['id'] = $attributes['globalSetId'];
        
        $attributes = array_diff_key($attributes, array_flip(array('data', 'fields', 'globalSetId')));
        
        $attributes = array_merge($attributes, $globalSetData);
        
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

    public function getGlobalSet()
    {
        if (is_null($this->globalSetId)) {
            $this->_globalSet = TranslationsForCraft::$plugin->globalSetRepository->getSetById($this->id); // this works for creating orders
        } else {
            $this->_globalSet = TranslationsForCraft::$plugin->globalSetRepository->getSetById($this->globalSetId); // this works for edit draft
        }

        return $this->_globalSet;
    }

    public function getUrl()
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getCpEditUrl()
    {
        $globalSet = $this->getGlobalSet();

        $path = 'translations-for-craft/globals/'.$globalSet->handle.'/drafts/'.$this->draftId;
        
        return TranslationsForCraft::$plugin->urlHelper->cpUrl($path);
    }
}
