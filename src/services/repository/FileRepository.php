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
use acclaro\translations\Translations;
use acclaro\translations\models\FileModel;
use acclaro\translations\records\FileRecord;
use acclaro\translations\services\job\RegeneratePreviewUrls;
use yii\db\Query;

class FileRepository
{
    /**
     * @param  int|string $fileId
     * @return \acclaro\translations\models\FileModel
     */
    public function getFileById($fileId)
    {
        $record = FileRecord::findOne($fileId);

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
            'dateUpdated',
            'dateDelivered',
            'dateDeleted',
        ]));

        return $file;
    }
    
    /**
     * @param  int|string $draftId
     * @param  int|string $elementId
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
            'dateDeleted',
        ]));

        return $file;
    }
    
    /**
     * @param  int|string $orderId
     * @return \acclaro\translations\models\FileModel
     */
    public function getFilesByOrderId(int $orderId, $elementId = null, $site=null)
    {
        $attributes = array(
            'orderId' => $orderId,
            'dateDeleted' => null
        );

        if ($elementId) {
            $attributes['elementId'] = $elementId;
        }
        if ($site) {
            $attributes['targetSite'] = $site;
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
                'dateDeleted',
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
        $attributes = array(
            'targetSite' => $siteId,
            'dateDeleted' => null
        );

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
                'dateDeleted',
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
        $records = FileRecord::find()
            ->all()
            ->where(['dateDeleted' => null]);

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
                'dateDeleted',
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
    public function delete($draftId, $elementId=null)
    {
        $attributes = ['draftId' => (int) $draftId];
        if ($elementId) {
            $attributes['elementId'] = $elementId;
        }

        return FileRecord::findOne($attributes)->delete();
    }

    /**
     * @param $order
     * @param null $queue
     * @return bool
     * @throws \Throwable
     */
    public function regeneratePreviewUrls($order, $queue=null) {
        $totalElements = count($order->files);
        $currentElement = 0;

        $service = new RegeneratePreviewUrls();
        foreach ($order->files as $file) {

            if ($queue) {
                $service->updateProgress($queue, $currentElement++ / $totalElements);
            }
            $transaction = Craft::$app->getDb()->beginTransaction();

            try {
                $draft = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);

                if ($draft) {
                    $element = Craft::$app->getElements()->getElementById($file->elementId, null, $file->sourceSite);
                    $file->previewUrl = Translations::$plugin->urlGenerator->generateElementPreviewUrl($draft, $file->targetSite);
                    $file->source = Translations::$plugin->elementToXmlConverter->toXml(
                        $element,
                        $file->draftId,
                        $file->sourceSite,
                        $file->targetSite,
                        $file->previewUrl
                    );
                }

                Translations::$plugin->fileRepository->saveFile($file);
                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }
        }

        if ($order->translator->service !== 'export_import') {
            $translator = $order->getTranslator();

            $translationService = Translations::$plugin->translatorFactory->makeTranslationService($translator->service, $translator->getSettings());

            $translationService->udpateReviewFileUrls($order);
        }

        return true;
    }

    /**
     * @param  int|string $elementId
     * @return \acclaro\translations\models\FileModel
     */
    public function getOrdersByElement(int $elementId)
    {

        $query = (new Query())
            ->select('files.orderId')
            ->from(['{{%translations_orders}} translations_orders'])
            ->innerJoin('{{%translations_files}} files', '[[files.orderId]] = [[translations_orders.id]]')
            ->where(['files.elementId' => $elementId,])
            ->andWhere(['translations_orders.status' => ['new','getting quote','needs approval','in preparation','in progress']])
            ->andWhere(['dateDeleted' => null])
            ->groupBy('orderId')
            ->all();

        $orderIds = [];

        foreach ($query as $key => $id) {
            $orderIds[] = $id['orderId'];
        }

        return $orderIds;
    }
}
