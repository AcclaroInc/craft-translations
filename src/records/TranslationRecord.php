<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\records;

use acclaro\translationsforcraft\TranslationsForCraft;

use Craft;
use craft\db\ActiveRecord;

/**
 * @author    Acclaro
 * @package   TranslationsForCraft
 * @since     1.0.0
 */
class TranslationRecord extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%translationsforcraft_translations}}';
    }
}
