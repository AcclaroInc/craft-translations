<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190705_140615_add_failed_status_to_files_table migration.
 */
class m190705_140615_add_failed_status_to_files_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        echo "Altering translations_files status column to include failed order status...\n";
        // 'new','in progress','preview','complete','canceled','published'
        $values = ['new','in progress','preview','complete','canceled','published','failed'];
        $this->alterColumn('{{%translations_files}}', 'status', $this->enum('values', $values)->notNull()->defaultValue('new'));
        echo "Done altering translations_files status column...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190705_140615_add_failed_status_to_files_table cannot be reverted.\n";
        return false;
    }
}
