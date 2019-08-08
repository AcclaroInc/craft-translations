<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190314_072821_increase_order_element_ids_length migration.
 */
class m190314_072821_increase_order_element_ids_length extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        echo "Altering translations_orders elementIds column...\n";
        $this->alterColumn('{{%translations_orders}}', 'elementIds', $this->string(2040)->notNull());
        echo "Done altering translations_orders elementIds column...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190314_072821_increase_order_element_ids_length cannot be reverted.\n";
        return false;
    }
}
