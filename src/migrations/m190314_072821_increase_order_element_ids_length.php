<?php

namespace acclaro\translationsforcraft\migrations;

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
        echo "Altering translationsforcraft_orders elementIds column...\n";
        $this->alterColumn('{{%translationsforcraft_orders}}', 'elementIds', $this->string(2040)->notNull()->defaultValue(''));
        echo "Done altering translationsforcraft_orders elementIds column...\n";
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
