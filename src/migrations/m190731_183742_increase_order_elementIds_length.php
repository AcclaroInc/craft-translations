<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190731_183742_increase_order_elementIds_length migration.
 */
class m190731_183742_increase_order_elementIds_length extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Place migration code here...
        echo "Altering translations_orders elementIds column...\n";
        $this->alterColumn('{{%translations_orders}}', 'elementIds', $this->string(8160)->notNull());
        echo "Done altering translations_orders elementIds column...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190731_183742_increase_order_elementIds_length cannot be reverted.\n";
        return false;
    }
}

