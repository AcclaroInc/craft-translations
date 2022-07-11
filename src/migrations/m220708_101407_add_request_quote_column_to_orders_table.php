<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;
use acclaro\translations\Constants;

/**
 * m220708_101407_add_request_quote_column_to_orders_table migration.
 */
class m220708_101407_add_request_quote_column_to_orders_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        echo "Adding translations_orders requestQuote column...\n";
        $this->addColumn(Constants::TABLE_ORDERS, 'requestQuote', $this->integer()->defaultValue(0)->after('asynchronousPublishing'));
        echo "Done adding translations_orders requestQuote column...\n";

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220708_101407_add_request_quote_column_to_orders_table cannot be reverted.\n";
        return false;
    }
}
