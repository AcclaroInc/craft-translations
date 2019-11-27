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
use acclaro\translations\models\FileModel;
use acclaro\translations\records\FileRecord;

class FileRepository
{
    /**
     * @param  int|string $fileId
     * @return \acclaro\translations\models\FileModel
     */
    public function getFileById($fileId)
    {
        $record = FileRecord::findOne($fileId);

        $file = new FileModel($record->toArray([
            'id',
            'orderId',
            'elementId',
            'draftId',
            'sourceSite',
            'targetSite',
            'status',
            'wordCount',
            'source',
            'target',
            'previewUrl',
            'serviceFileId',
            'dateUpdated',
            'dateDelivered',
        ]));

        return $file;
    }
    
    /**
     * @param  int|string $draftId
     * @return \acclaro\translations\models\FileModel
     */
    public function getFileByDraftId($draftId, $elementId = null)
    {
        $attributes = array('draftId' => (int) $draftId);

        if ($elementId) {
            $attributes['elementId'] = $elementId;
        }

        $record = FileRecord::findOne($attributes);

        if (!$record) {
            return false;
        }
        
        $file = new FileModel($record->toArray([
            'id',
            'orderId',
            'elementId',
            'draftId',
            'sourceSite',
            'targetSite',
            'status',
            'wordCount',
            'source',
            'target',
            'previewUrl',
            'serviceFileId',
            'dateDelivered',
        ]));

        return $file;
    }
    
    /**
     * @param  int|string $orderId
     * @return \acclaro\translations\models\FileModel
     */
    public function getFilesByOrderId(int $orderId, $elementId = null)
    {
        $attributes = array('orderId' => $orderId);

        if ($elementId) {
            $attributes['elementId'] = $elementId;
        }

        $records = FileRecord::find()->where($attributes)->all();

        $files = array();

        foreach ($records as $key => $record) {
            $files[$key] = new FileModel($record->toArray([
                'id',
                'orderId',
                'elementId',
                'draftId',
                'sourceSite',
                'targetSite',
                'status',
                'wordCount',
                'source',
                'target',
                'previewUrl',
                'serviceFileId',
                'dateUpdated',
                'dateDelivered',
            ]));
        }

        return $files ? $files : array();
    }
    
    /**
     * @param  int|string $siteId
     * @return \acclaro\translations\models\FileModel
     */
    public function getFilesByTargetSite(int $siteId, $elementId = null)
    {
        $attributes = array('targetSite' => $siteId);

        if ($elementId) {
            $attributes['elementId'] = $elementId;
        }

        $records = FileRecord::find()->where($attributes)->all();

        $files = array();

        foreach ($records as $key => $record) {
            $files[$key] = new FileModel($record->toArray([
                'id',
                'orderId',
                'elementId',
                'draftId',
                'sourceSite',
                'targetSite',
                'status',
                'wordCount',
                'source',
                'target',
                'previewUrl',
                'serviceFileId',
                'dateDelivered',
            ]));
        }

        return $files ? $files : array();
    }

    /**
     * @param  int|string $orderId
     * @return \acclaro\translations\models\FileModel
     */
    public function getFiles()
    {
        $records = FileRecord::find()->all();

        $files = array();

        foreach ($records as $key => $record) {
            $files[$key] = new FileModel($record->toArray([
                'id',
                'orderId',
                'elementId',
                'draftId',
                'sourceSite',
                'targetSite',
                'status',
                'wordCount',
                'source',
                'target',
                'previewUrl',
                'serviceFileId',
                'dateUpdated',
                'dateDelivered',
            ]));
        }

        return $files ? $files : array();
    }

    /**
     * @return \acclaro\translations\models\FileModel
     */
    public function makeNewFile()
    {
        return new FileModel();
    }

    /**
     * @param  \acclaro\translations\models\FileModel $file
     * @throws \Exception
     * @return bool
     */
    public function saveFile(FileModel $file)
    {
        $isNew = !$file->id;

        if (!$isNew) {
            // echo '<pre>';
            // var_dump($file->id);
            // echo '</pre>';
            $record = FileRecord::findOne($file->id);
            // echo '<pre>';
            // var_dump($record);
            // echo '</pre>';
            // die;

            if (!$record) {
                throw new Exception('No file exists with that ID.');
            }
            $record->setAttributes($file->getAttributes(), false);
        } else {
            $record = new FileRecord();
            $att = $file->getAttributes();
            if (empty($att['id'])) {
                unset($att['id']);
            }
            $record->setAttributes($att, false);
        }
        
        if (!$record->validate()) {
            $file->addErrors($record->getErrors());

            return false;
        }

        if ($file->hasErrors()) {
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
     * @param $draftId
     * @return false|int
     * @throws \Throwable
     */
    public function delete($draftId)
    {
        $attributes = ['draftId' => (int) $draftId];

        return FileRecord::findOne($attributes)->delete();
    }
}