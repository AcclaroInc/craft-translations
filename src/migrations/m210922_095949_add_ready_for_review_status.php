<?php

namespace acclaro\translations\migrations;

use acclaro\translations\Constants;
use Craft;
use craft\db\Migration;

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
        echo "Altering translations_files status column...\n";
        $this->alterColumn('{{%translations_files}}', 'status', $this->enum('status', Constants::FILE_STATUSES)->defaultValue('new'));
        echo "Done altering translations_files status column...\n";

        echo "Adding translations_orders status column...\n";
        $this->alterColumn('{{%translations_orders}}', 'status', $this->enum('status', Constants::ORDER_STATUSES)->defaultValue('new'));
        echo "Done adding translations_orders status column...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210922_095949_add_ready_for_review_status cannot be reverted.\n";
        return false;
    }
}
