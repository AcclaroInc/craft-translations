<?php

namespace acclaro\translations\migrations;

use acclaro\translations\Constants;
use Craft;
use craft\db\Migration;

/**
 * m240808_084903_add_program_id_to_orders migration.
 */
class m240808_084903_add_program_id_to_orders extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        echo "Adding translations_orders requestQuote column...\n";
        $this->addColumn(Constants::TABLE_ORDERS, 'programId', $this->integer()->null()->after('ownerId'));
        echo "Done adding translations_orders requestQuote column...\n";

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $tableName = Constants::TABLE_ORDERS;
        $columnName = 'programId';

        // Remove the column if it exists
        if ($this->db->columnExists($tableName, $columnName)) {
            $this->dropColumn($tableName, $columnName);
            Craft::info("Dropped column '{$columnName}' from table '{$tableName}'", __METHOD__);
        }

        return true;
    }
}
