<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\records;

use acclaro\translations\Translations;

use Craft;
use craft\db\ActiveRecord;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class FileRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%translations_files}}';
    }
}
