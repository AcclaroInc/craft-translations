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

use Craft;
use Exception;
use ReflectionClass;
use acclaro\translations\models\TranslationModel;
use acclaro\translations\records\TranslationRecord;
use acclaro\translations\Translations;
use acclaro\translations\Constants;

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

    /**
     * @return \acclaro\translations\models\TranslationModel
     */
    public function makeNewTranslation()
    {
        return new TranslationModel();
    }

    /**
     * @param  \acclaro\translations\models\TranslationModel $translation
     * @throws \Exception
     * @return bool
     */
    public function saveTranslation(TranslationModel $translation)
    {
        $isNew = !$translation->id;

        if (!$isNew) {
            $record = TranslationRecord::model()->findById($translation->id);

            if (!$record) {
                throw new Exception('No translation exists with that ID.');
            }
            $record->setAttributes($translation->getAttributes(), false);
        } else {
            $record = new TranslationRecord();
            $new_translation = $translation->getAttributes();
            unset($new_translation['id']);
            $record->setAttributes($new_translation, false);
        }

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
                'targetSite',
                'source',
                'target'
            ]));
        }

        return $translations ? $translations : array();
    }

    public function loadTranslations()
    {
        // Get stored string translations
        $translations = $this->getTranslations();

        // Create reflection class of i18n and make accessible
        $reflectionClass = new ReflectionClass('yii\i18n\MessageSource');
        $reflectionProperty = $reflectionClass->getProperty('_messages');
        $reflectionProperty->setAccessible(true);

        // Get i18n messages
        $messages = $reflectionProperty->getValue(Craft::$app->getI18n()->getMessageSource('translations'));

        // Set site i18n messages from stored translations
        foreach ($translations as $translation) {
            $sourceSite = Craft::$app->sites->getSiteById($translation->sourceSite);
            $targetSite = Craft::$app->sites->getSiteById($translation->targetSite);

            if (! $targetSite) {
                $this->deleteTranslationForSite($translation->targetSite);
                continue;
            } else if (! $sourceSite) {
                $this->deleteTranslationForSite($translation->sourceSite);
                continue;
            }

            $sourceLanguage = $sourceSite->language;
            $targetLanguage = $targetSite->language;
            $key = sprintf('%s/%s', $targetLanguage, 'translations');
            $messages[$key][$translation->source] = $translation->target;

            if ($sourceLanguage !== $targetLanguage) {
                Craft::$app->getI18n()->translate('translations', $translation->source, [], $targetLanguage);
            }

        }

        $reflectionProperty->setValue(Craft::$app->getI18n()->getMessageSource('translations'), $messages);

        $reflectionProperty->setAccessible(false);
    }

    public function deleteTranslationForSite($siteId)
    {
        try {
            $records = TranslationRecord::find()->where(['targetSite' => $siteId])->orWhere(['sourceSite' => $siteId])->all();
            foreach ($records as $record) {
                $record->delete();
            }
        } catch (\Exception $e) {
            Translations::$plugin->logHelper->log("Error deleting translations for Site Id: $siteId", Constants::LOG_LEVEL_ERROR);
        }
    }
}
