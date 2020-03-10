<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m200303_221033_add_review_status_in_orders_table migration.
 */
class m200303_221033_add_review_status_in_orders_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        echo "Altering translations_orders status column to include failed order status...\n";
        // 'new','in progress','preview','complete','canceled','published'
        $values = ['new','getting quote','needs approval','in preparation','in review','in progress','complete','canceled','published','failed'];
        $this->alterColumn('{{%translations_orders}}', 'status', $this->enum('values', $values)->notNull()->defaultValue('new'));
        echo "Done altering translations_orders status column...\n";

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200303_221033_add_review_status_in_orders_table cannot be reverted.\n";
        return false;
    }
}
