<?php

namespace acclaro\translations\migrations;

use acclaro\translations\Constants;
use craft\db\Migration;

/**
 * Combined migration for dropping activityLog column and creating activity_log table.
 */
class m240117_112821_create_activity_logs_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Create activity_log table
        $this->createTable(Constants::TABLE_ACTIVITY_LOG, [
            'id' => $this->primaryKey(),
            'targetId' => $this->integer()->notNull(),
            'targetClass' => $this->string()->notNull(),
            'created' => $this->dateTime()->notNull(),
            'message' => $this->text(),
            'actions' => $this->text()
        ]);

        $this->createIndex(null, Constants::TABLE_ACTIVITY_LOG, ['targetId']);
        $this->createIndex(null, Constants::TABLE_ACTIVITY_LOG, ['targetClass']);

        // Move data from activityLog column to activity_log table
        $data = $this->db->createCommand('SELECT "id", "activityLog" FROM {{%translations_orders}}')->queryAll();

        foreach ($data as $row) {
            $messages = json_decode($row['activityLog'], true);

            foreach ($messages as $message) {
                // Insert data into activity_log table
                $this->insert(Constants::TABLE_ACTIVITY_LOG, [
                    'created' => \DateTime::createFromFormat('d/m/Y', $message['date'])->format('Y-m-d H:i:s'),
                    'targetId' => $row['id'],
                    'targetClass' => 'acclaro\\translations\\elements\\Order',
                    'message' => $message['message'],
                    'actions' => ''
                ]);
            }
        }

        // Drop activityLog column
        $this->dropColumn(Constants::TABLE_ORDERS, 'activityLog');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240117_054435_create_activity_log cannot be reverted.\n";
        return false;
    }
}
