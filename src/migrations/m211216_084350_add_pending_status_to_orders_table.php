<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Connection;
use acclaro\translations\Constants;

/**
 * m211216_084350_add_pending_status_to_orders_table migration.
 */
class m211216_084350_add_pending_status_to_orders_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (Craft::$app->getDb()->getDriverName() === Connection::DRIVER_PGSQL) {
            echo "Dropping existing constraints...\n";
            $this->dropConstraint('translations_files_status_check', 'translations_files');
            $this->dropConstraint('translations_orders_status_check', 'translations_orders');
        }

        echo "Altering translations_orders status column to introduce pending, modified status...\n";
        $this->alterColumn(
            Constants::TABLE_ORDERS,
            'status',
            $this->enum('status', Constants::ORDER_STATUSES)
                ->notNull()
                ->defaultValue(Constants::ORDER_STATUS_PENDING)
        );
        echo "Done altering translations_orders status column...\n";

        // Convert existing orders new status to Pending
        $this->updateStatusNewToPending();

        echo "Altering translations_files status column to introduce modified status...\n";
        $this->alterColumn(
            Constants::TABLE_FILES,
            'status',
            $this->enum('status', Constants::FILE_STATUSES)
                ->notNull()
                ->defaultValue(Constants::FILE_STATUS_NEW)
        );
        echo "Done altering translations_files status column...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m211216_084350_add_pending_status_to_orders_table cannot be reverted.\n";
        return false;
    }

    /**
     * Change all orders status from new to pending
     *
     * @return void
     */
    private function updateStatusNewToPending()
    {
        $this->update(
            Constants::TABLE_ORDERS,
            ['status' => Constants::ORDER_STATUS_PENDING],
            'status = :oldStatus',
            [':oldStatus' => Constants::ORDER_STATUS_NEW]
        );
    }

    /**
     * Drop constraints in case of postgres
     *
     * @param string $constraint
     * @param string $table
     * @return void
     */
    private function dropConstraint($constraint, $table)
    {
        Craft::$app->getDb()->createCommand("ALTER TABLE {{%$table}} DROP CONSTRAINT IF EXISTS {{%$constraint}}")->execute();
    }
}
