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

use Craft;
use craft\base\Model;
use craft\validators\SiteIdValidator;
use craft\validators\StringValidator;
use craft\validators\UniqueValidator;
use craft\validators\DateTimeValidator;
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\TranslationsForCraft;

/**
 * @author    Acclaro
 * @package   TranslationsForCraft
 * @since     1.0.0
 */
class TranslatorModel extends Model
{
    /**
     * @var int|null
     */
    public $id;
    
    public $status;
    
    public $service;
    
    public $label;
    
    public $sites;
    
    public $settings;
    
    public $attributes;

    public function rules()
    {
        return [
            ['id', 'number', 'integerOnly' => true],
            [['id', 'label', 'service', 'sites', 'settings', 'uid'], 'required'],
            [['label','service'], 'StringValidator'],
            ['sites', SiteIdValidator::class],
            [['dateCreated', 'dateUpdated'], DateTimeValidator::class],
        ];
    }

    public function fields()
    {
        return [
            'label' => '',
            'service' => '',
            'status' => 'inactive'
        ];
    }

    public function getName()
    {
        return $this->label ? $this->label : TranslationsForCraft::$plugin->translatorRepository->getTranslatorServiceLabel($this->service);
    }

    public function getSitesArray()
    {
        return $this->sites ? json_decode($this->sites, true) : array();
    }

    public function getSettings()
    {
        return $this->settings ? json_decode($this->settings, true) : array();
    }

    public function getSetting($setting)
    {
        $settings = $this->getSettings();

        return isset($settings[$setting]) ? $settings[$setting] : null;
    }
}