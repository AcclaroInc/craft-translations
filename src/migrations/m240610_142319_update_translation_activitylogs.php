<?php

namespace acclaro\translations\migrations;

use acclaro\translations\Constants;
use craft\db\Migration;

/**
 * Migration to update translations_activitylogs table
 */
class m240610_142319_update_translation_activitylogs extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Check if the table exists
        if ($this->db->tableExists(Constants::TABLE_ACTIVITY_LOG)) {
            if ($this->db->getTableSchema(Constants::TABLE_ACTIVITY_LOG)->getColumn('targetclass') !== null) {
                $this->renameColumn(Constants::TABLE_ACTIVITY_LOG, 'targetclass', 'targetClass');
            }
        }
    
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240610_142319_update_translation_activitylogs cannot be reverted.\n";
        return false;
    }
}
