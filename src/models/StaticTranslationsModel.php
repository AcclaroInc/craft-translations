<?php
/**
 * Translations for Craft plugin for Craft CMS 5.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\models;

use Craft;
use craft\base\Model;
use craft\validators\SiteIdValidator;
use craft\validators\DateTimeValidator;
use acclaro\translations\records\StaticTranslationsRecord;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class StaticTranslationsModel extends Model
{
    /**
     * @var int|null
     */
    public $id;
    
    public $siteId;
    
    public $original;
    
    public $translation;

    public function rules(): array
    {
        return [
            ['id', 'number', 'integerOnly' => true],
            [['id', 'siteId', 'original'], 'required'],
            [['siteId'], SiteIdValidator::class],
            [['dateCreated', 'dateUpdated'], DateTimeValidator::class],
        ];
    }

    public function createOrUpdate(): bool
    {
        $record = null;
        $attributes = $this->getAttributes();
        if (empty($attributes['id'])) {
            unset($attributes['id']);
        }

        if ($this->id) {
            $record = StaticTranslationsRecord::findOne($this->id);
        }

        if (!$record) {
            $record = StaticTranslationsRecord::findOne([
                'siteId' => $this->siteId,
                'original' => $this->original
            ]);
        }

        if (!$record) {
            $record = new StaticTranslationsRecord();
        }
        $record->setAttributes($attributes, false);

        if (!$record->validate()) {
            $this->addErrors($record->getErrors());

            return false;
        }

        if ($this->hasErrors()) {
            return false;
        }

        $transaction = Craft::$app->db->getTransaction() === null ? Craft::$app->db->beginTransaction() : null;

        try {
            if ($record->save(false)) {
                if ($transaction !== null) {
                    $transaction->commit();
                }

                return true;
            }
        } catch (\Exception $e) {
            if ($transaction !== null) {
                $transaction->rollback();
            }

            throw $e;
        }

        return false;
    }
}
