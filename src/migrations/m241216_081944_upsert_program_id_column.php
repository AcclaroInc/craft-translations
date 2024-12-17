<?php

namespace acclaro\translations\migrations;

use acclaro\translations\Constants;
use Craft;
use craft\db\Migration;

/**
 * m241216_081944_upsert_program_id_column migration.
 */
class m241216_081944_upsert_program_id_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $tableName = Constants::TABLE_ORDERS;
        $columnName = 'programId';

        // Check if the column exists
        if (!$this->db->columnExists($tableName, $columnName)) {

            $this->addColumn($tableName, $columnName, $this->integer()->null()->after('ownerId'));

            Craft::info("Added column '{$columnName}' to table '{$tableName}'", __METHOD__);
        } else {
            Craft::info("Column '{$columnName}' already exists in table '{$tableName}'", __METHOD__);
        }

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
