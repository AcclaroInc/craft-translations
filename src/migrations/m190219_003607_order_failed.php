<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190219_003607_order_failed migration.
 */
class m190219_003607_order_failed extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        echo "Altering translations_orders status column to include failed order status...\n";
        $values = ['new','getting quote','needs approval','in preparation','in progress','complete','canceled','published','failed'];
        $this->alterColumn('{{%translations_orders}}', 'status', $this->enum('values', $values)->notNull()->defaultValue('new'));
        echo "Done altering translations_orders status column...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190219_003607_order_failed cannot be reverted.\n";
        return false;
    }
}
