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

use craft\records\Element;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;
use acclaro\translations\Constants;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class OrderRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return Constants::TABLE_ORDERS;
    }

    /**
     * Returns the Order element.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }
}
