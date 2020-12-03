<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m201202_234614_add_file_soft_deletes migration.
 */
class m201202_234614_add_file_soft_deletes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Existing table migration
        $this->addColumn('{{%translations_files}}', 'dateDeleted',
        $this->dateTime()->null()->after('dateUpdated'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201202_234614_add_file_soft_deletes cannot be reverted.\n";
        return false;
    }
}