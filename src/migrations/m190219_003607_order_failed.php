<?php

namespace acclaro\translationsforcraft\migrations;

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
        echo "Altering translationsforcraft_orders status column to include failed order status...\n";
        $values = ['new','getting quote','needs approval','in preparation','in progress','complete','canceled','published','failed'];
        $this->alterColumn('{{%translationsforcraft_orders}}', 'status', $this->enum('values', $values)->notNull()->defaultValue('new'));
        echo "Done altering translationsforcraft_orders status column...\n";
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
