<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m210520_225356_add_order_due_date_column migration.
 */
class m210520_225356_add_order_due_date_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Place migration code here...
        $this->addColumn('{{%translations_orders}}', 'orderDueDate',
                         $this->dateTime()->null()->after('requestedDueDate')
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210520_225356_add_order_due_date_column cannot be reverted.\n";
        return false;
    }
}
