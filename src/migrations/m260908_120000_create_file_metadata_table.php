<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;
use acclaro\translations\Constants;

class m260908_120000_create_file_metadata_table extends Migration
{
    public function safeUp(): bool
    {
        if (Craft::$app->db->schema->getTableSchema(Constants::TABLE_FILE_METADATA, true) !== null) {
            return true;
        }

        $this->createTable(Constants::TABLE_FILE_METADATA, [
            'id' => $this->primaryKey(),
            'order_id' => $this->integer()->notNull(),
            'element_id' => $this->integer()->notNull(),
            'metadata_key' => $this->string(500)->notNull(),
            'metadata_value' => $this->text(),
            'dateCreated' => $this->dateTime(),
            'dateUpdated' => $this->dateTime(),
        ]);

        $this->createIndex(
            'uq_translation_file_metadata',
            Constants::TABLE_FILE_METADATA,
            ['order_id', 'element_id', 'metadata_key'],
            true
        );

        return true;
    }

    public function safeDown(): bool
    {
        echo "m260908_120000_create_file_metadata_table cannot be reverted.\n";
        return false;
    }
}
