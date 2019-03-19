<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\repository;
use acclaro\translations\Translations;

use Craft;
use Exception;
use yii\i18n\MessageSource;
use ReflectionClass;
use acclaro\translations\models\TranslationModel;
use acclaro\translations\records\TranslationRecord;

class TranslationRepository
{
    public function addTranslation($sourceSite, $targetSite, $source, $target)
    {
        $translations = $this->find(compact('sourceSite', 'targetSite', 'source', 'target'));

        if (!$translations) {
            $translation = $this->makeNewTranslation();
            $translation->sourceSite = $sourceSite;
            $translation->targetSite = $targetSite;
            $translation->source = $source;
            $translation->target = $target;
            $this->saveTranslation($translation);
        }
    }

    public function find($attributes)
    {
        $records = TranslationRecord::find()->where($attributes)->all();
        $translations = array();

        foreach ($records as $key => $record) {
            $translations[$key] = new TranslationModel($record->toArray([
                'id',
                'sourceSite',
                'targetSite',
                'source',
                'target'
            ]));
        }

        return $translations;
    }

    public function makeNewTranslation()
    {
        return new TranslationModel();
    }

    public function saveTranslation(TranslationModel $translation)
    {
        $isNew = !$translation->id;

        if (!$isNew) {
            $record = TranslationRecord::model()->findById($translation->id);

            if (!$record) {
                throw new Exception('No translation exists with that ID.');
            }
        } else {
            $record = new TranslationRecord();
        }

        $record->setAttributes($translation->getAttributes(), false);

        if (!$record->validate()) {
            $translation->addErrors($record->getErrors());

            return false;
        }

        if ($translation->hasErrors()) {
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
        } catch (Exception $e) {
            if ($transaction !== null) {
                $transaction->rollback();
            }

            throw $e;
        }

        return false;
    }

    public function getTranslations()
    {
        $records = TranslationRecord::find()->all();
        $translations = array();

        foreach ($records as $key => $record) {
            $translations[$key] = new TranslationModel($record->toArray([
                'id',
                'sourceSite',
                'targetSites',
                'source',
                'target'
            ]));
        }

        return $translations ? $translations : array();
    }

    public function loadTranslations()
    {
        $translations = $this->getTranslations();

        /**
         * Look into what this does
         */
        // $reflectionClass = new ReflectionClass(MessageSource::class);

        // $reflectionProperty = $reflectionClass->getProperty('_messages');

        // $reflectionProperty->setAccessible(true);


        // $messages = $reflectionProperty->getValue(Craft::$app->systemMessages);

        // foreach ($translations as $translation) {
        //     $key = sprintf('%s.%s', $translation->sourceSite, 'craft');

        //     $messages[$key][$translation->source] = $translation->target;

        //     $key = sprintf('%s.%s', $translation->targetSite, 'craft');

        //     $messages[$key][$translation->source] = $translation->target;
        // }

        // $reflectionProperty->setValue(Craft::$app->systemMessages, $messages);

        // $reflectionProperty->setAccessible(false);
    }
}