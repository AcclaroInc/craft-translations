<?php

namespace acclaro\translations\migrations;

use craft\db\Migration;
use acclaro\translations\Constants;

/**
 * m220705_040234_change_preview_url_column_to_text migration.
 */
class m220705_040234_change_preview_url_column_to_text extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        echo "Altering translations_files previewUrl column...\n";
        $this->alterColumn(Constants::TABLE_FILES, 'previewUrl', $this->text());
        echo "Done altering translations_files previewUrl column...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m220705_040234_change_preview_url_column_to_text cannot be reverted.\n";
        return false;
    }
}
