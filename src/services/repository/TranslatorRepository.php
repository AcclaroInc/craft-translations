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
use acclaro\translations\Constants;
use acclaro\translations\models\TranslatorModel;
use acclaro\translations\records\TranslatorRecord;

class TranslatorRepository
{
    /**
     * @param  int|string $translatorId
     * @return \acclaro\translations\models\TranslatorModel
     */
    public function getTranslatorById($translatorId)
    {
        $record = TranslatorRecord::findOne($translatorId);

        if (! $record) return null;

        $translator = new TranslatorModel($record->toArray([
            'id',
            'label',
            'service',
            'status',
            'settings',
            'dateCreated',
            'dateUpdated',
        ]));

        return $translator;
    }

    /**
     * @param  int|string $service
     * @return \acclaro\translations\models\TranslatorModel
     */
    public function getTranslatorByService($service)
    {
        $records = TranslatorRecord::find()->all();
        $translators = array();

        foreach ($records as $key => $record) {
            $translators[$key] = new TranslatorModel($record->toArray([
                'id',
                'label',
                'service',
                'status'
            ]));
            if ($translators[$key]['service'] !== $service) {
                unset($translators[$key]);
            }
        }

        return $translators;
    }

    /**
     * @return array \acclaro\translations\models\TranslatorModel
     */
    public function getTranslators()
    {
        $records = TranslatorRecord::find()->all();
        $translators = array();

        foreach ($records as $key => $record) {
            $translators[$key] = new TranslatorModel($record->toArray([
                'id',
                'label',
                'service',
                'status',
                'settings',
            ]));
        }

        return $translators;
    }

    public function getTranslatorServices()
    {
        $records = TranslatorRecord::find()->all();
        $translatorServices = array();

        foreach ($records as $key => $record) {
            $translatorServices[$record->service] = new TranslatorModel($record->toArray([
                'id',
                'label',
                'service',
            ]));
        }
        ksort($translatorServices);
        return $translatorServices;
    }

    /**
     * @return array \acclaro\translations\models\TranslatorModel
     */
    public function getActiveTranslators()
    {
        $records = TranslatorRecord::findAll(array(
            'status' => 'active',
        ));

        $translators = array();

        foreach ($records as $key => $record) {
            $translators[$key] = new TranslatorModel($record->toArray([
                'id',
                'label',
                'service',
                'status',
                'settings',
            ]));
        }

        return $translators;
    }

    /**
     * @param  string $service
     * @return string
     */
    public function getTranslatorServiceLabel($service)
    {
        $services = $this->getTranslationServices();

        return isset($services[$service]) ? $services[$service] : '';
    }

    /**
     * @return array id => label
     */
    public function getTranslatorOptions()
    {
        $options = array();

        foreach ($this->getActiveTranslators() as $translator) {
            $options[$translator->id] = $translator->label ? $translator->label : $this->getTranslatorServiceLabel($translator->service);
        }

        return $options;
    }

    /**
     * @return slug => label
     */
    public function getTranslationServices()
    {
        return Constants::TRANSLATOR_SERVICES;
    }

    /**
     * @return \acclaro\translations\models\TranslatorModel
     */
    public function makeNewTranslator()
    {
        return new TranslatorModel();
    }

    /**
     * @param  \acclaro\translations\models\TranslatorModel $translator
     * @throws \Exception
     * @return bool
     */
    public function saveTranslator(TranslatorModel $translator)
    {
        $isNew = !$translator->id;

        if (!$isNew) {
            $record = TranslatorRecord::findOne($translator->id);

            if (!$record) {
                throw new Exception('No translator exists with that ID.');
            }
            $record->setAttributes($translator->getAttributes(), false);
        } else {
            $record = new TranslatorRecord();
            $new_translator = $translator->getAttributes();
            unset($new_translator['id']);
            $record->setAttributes($new_translator, false);
        }

        if (!$record->validate()) {
            $translator->addErrors($record->getErrors());

            return false;
        }

        if ($translator->hasErrors()) {
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

    /**
     * @param  \acclaro\translations\models\TranslatorModel $translator
     * @return bool
     */
    public function deleteTranslator(TranslatorModel $translator)
    {
        $record = TranslatorRecord::findOne($translator->id);

        $transaction = Craft::$app->db->getTransaction() === null ? Craft::$app->db->beginTransaction() : null;

        try {
            if ($record->delete(false)) {
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
    }

    /**
     * @return array id => label
     */
    public function getAcclaroApiTranslators()
    {
        $translators = array();

        foreach ($this->getActiveTranslators() as $translator) {
            if ($translator->service === Constants::TRANSLATOR_ACCLARO) {
                $translators[] = $translator->id;
            }
        }

        return $translators;
    }
}