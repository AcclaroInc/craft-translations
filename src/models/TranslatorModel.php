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

use craft\base\Model;
use acclaro\translations\Constants;
use craft\validators\DateTimeValidator;
use acclaro\translations\Translations;

/**
 * @author    Acclaro
 * @package   Translations
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
    
    public $settings;
    
    public $attributes;

    public $dateCreated;
    
    public $dateUpdated;

    public function rules(): array
    {
        return [
            ['id', 'number', 'integerOnly' => true],
            [['id', 'label', 'service', 'settings', 'uid'], 'required'],
            [['label','service'], 'StringValidator'],
            [['dateCreated', 'dateUpdated'], DateTimeValidator::class],
        ];
    }

    public function fields(): array
    {
        return [
            'label' => '',
            'service' => '',
            'status' => Constants::TRANSLATOR_STATUS_INACTIVE
        ];
    }

    public function getName()
    {
        return $this->label ? $this->label : Translations::$plugin->translatorRepository->getTranslatorServiceLabel($this->service);
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