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
use craft\validators\SiteIdValidator;
use craft\validators\DateTimeValidator;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class TranslationModel extends Model
{
    /**
     * @var int|null
     */
    public $id;
    
    public $sourceSite;
    
    public $targetSite;
    
    public $source;
    
    public $target;

    public $attributes;

    public function rules(): array
    {
        return [
            ['id', 'number', 'integerOnly' => true],
            [['id', 'sourceSite', 'targetSite', 'source', 'target'], 'required'],
            [['sourceSite', 'targetSite'], SiteIdValidator::class],
            [['dateCreated', 'dateUpdated'], DateTimeValidator::class],
        ];
    }
}
