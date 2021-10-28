<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Connection;
use acclaro\translations\Constants;

/**
 * m210922_095949_add_ready_for_review_status migration.
 */
class m210922_095949_add_ready_for_review_status extends Migration
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

        echo "Altering translations_files status column...\n";
        $this->alterColumn('{{%translations_files}}', 'status', $this->enum('status', Constants::FILE_STATUSES)->notNull()->defaultValue('new'));
        echo "Done altering translations_files status column...\n";

        echo "Adding translations_orders status column...\n";
        $this->alterColumn('{{%translations_orders}}', 'status', $this->enum('status', Constants::ORDER_STATUSES)->notNull()->defaultValue('new'));
        echo "Done adding translations_orders status column...\n";

        echo "Altering translations_orders activity log column...\n";
        $this->alterColumn('{{%translations_orders}}', 'activityLog', $this->longText());
        echo "Done altering translations_orders activity log column...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210922_095949_add_ready_for_review_status cannot be reverted.\n";
        return false;
    }
    
    private function dropConstraint($constraint, $table)
    {
        Craft::$app->getDb()->createCommand("ALTER TABLE {{%$table}} DROP CONSTRAINT IF EXISTS {{%$constraint}}")->execute();
    }
}
