<?php

namespace acclaro\translations\migrations;

use acclaro\translations\Constants;
use craft\db\Migration;

/**
 * m231214_125123_reduce_tags_size_in_orders_table migration.
 */
class m231214_125123_reduce_tags_size_in_orders_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        echo "Altering translations_orders tags column...\n";
        $this->alterColumn(Constants::TABLE_ORDERS, 'tags', $this->string(1020)->notNull()->defaultValue(''));
        echo "Done altering translations_orders tags column...\n";
        
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m231214_125123_reduce_tags_size_in_orders_table cannot be reverted.\n";
        return false;
    }
}
