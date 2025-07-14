<?php

namespace acclaro\translations\migrations;

use craft\db\Migration;
use acclaro\translations\Constants;

/**
 * m250611_111655_add_draft_reference_column_to_files_table migration.
 */
class m250611_111655_add_draft_reference_column_to_files_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        echo "Adding translations_files draftReference column...\n";
        $this->addColumn(Constants::TABLE_FILES, 'draftReference', $this->longText()->after('reference'));
        echo "Done adding translations_files draftReference column...\n";

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250611_111655_add_draft_reference_column_to_files_table cannot be reverted.\n";
        return false;
    }
}
