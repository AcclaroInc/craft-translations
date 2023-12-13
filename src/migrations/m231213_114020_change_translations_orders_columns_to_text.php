<?php

namespace acclaro\translations\migrations;

use craft\db\Migration;
use acclaro\translations\Constants;

/**
 * m231213_114020_change_translations_orders_columns_to_text migration.
 */
class m231213_114020_change_translations_orders_columns_to_text extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        echo "Altering translations_orders elementIds column...\n";
        $this->alterColumn(Constants::TABLE_ORDERS, 'elementIds', $this->text());
        echo "Done altering translations_orders elementIds column...\n";

        echo "Altering translations_orders tags column...\n";
        $this->alterColumn(Constants::TABLE_ORDERS, 'tags', $this->text());
        echo "Done altering translations_orders tags column...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m231213_114020_change_translations_orders_columns_to_text cannot be reverted.\n";
        return false;
    }
}
