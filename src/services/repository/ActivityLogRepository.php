<?php

/**
 * Translations for Craft plugin for Craft CMS 4.x
 *
 * Translations for Craft eliminates error-prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\repository;

use acclaro\translations\models\ActivityLogModel;
use acclaro\translations\records\ActivityLogRecord;
use Craft;
use Exception;

class ActivityLogRepository
{
    protected $defaultColumns = [
        'id',
        'targetId',
        'created',
        'message',
        'targetClass',
        'actions'
    ];
    /**
     * Creates a new ActivityLogModel instance and saves it to the database.
     *
     * @param string $message
     * @param int $orderId
     * @return bool
     */
    public function createActivityLog($message, $target)
    {
        try {
            // Create a new ActivityLog model
            $activityLog = $this->makeNewActivityLogModel();
            $activityLog->targetId = $target->id;
            $activityLog->message = $message;
            $activityLog->created = date('Y-m-d H:i:s');
            $activityLog->targetClass = get_class($target);

            return $this->saveActivityLog($activityLog);
        } catch (Exception $e) {
            // Handle the exception (log or rethrow)
            Craft::error('Error creating ActivityLog: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }


    /**
     * Creates a new ActivityLogModel instance.
     *
     * @param string $message
     * @param int $orderId
     * @return ActivityLogModel
     */
    protected function makeNewActivityLogModel()
    {
        return new ActivityLogModel();
    }

    /**
     * Saves the ActivityLogModel to the database.
     *
     * @param ActivityLogModel $activityLog
     * @return bool
     */
    protected function saveActivityLog(ActivityLogModel $activityLog)
    {
        $record = new ActivityLogRecord();
        $att = $activityLog->getAttributes();
        if (empty($att['id'])) {
            unset($att['id']);
        }
        $record->setAttributes($att, false);

        if (!$record->validate()) {
            $activityLog->addErrors($record->getErrors());

            return false;
        }

        if ($activityLog->hasErrors()) {
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
     * Get activity log data based on targetId
     *
     * @param int $targetId
     * @return array
     */
    public function getActivityLogsByTargetId($target)
    {
        
        $logs = ActivityLogRecord::find()
            ->where(['targetId' => $target->id, 'targetClass' => get_class($target)])
            ->all();

        $activityLogs = [];

        foreach ($logs as $log) {
            $activityLogs[] = new ActivityLogModel($log->toArray($this->defaultColumns));
        }

        return $activityLogs;
    }

    public function getActivityLogs()
    {
        $logs = ActivityLogRecord::find()->all();

        $activityLogs = [];

        foreach ($logs as $log) {
            $activityLogs[] = new ActivityLogModel($log->toArray($this->defaultColumns));
        }

        return $activityLogs;
    }
}
