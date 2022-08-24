<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m220511_051205_remove_translations_categorydrafts_table migration.
 */
class m220511_051205_remove_translations_categorydrafts_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropTableIfExists('{{%translations_categorydrafts}}');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220511_051205_remove_translations_categorydrafts_table cannot be reverted.\n";
        return false;
    }
}
