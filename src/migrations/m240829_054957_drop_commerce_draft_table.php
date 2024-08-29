<?php

namespace acclaro\translations\migrations;

use craft\db\Migration;

/**
 * m240829_054957_drop_commerce_draft_table migration.
 */
class m240829_054957_drop_commerce_draft_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        echo "Dropping translations_commercedrafts table...\n";

        $this->dropTableIfExists('{{%translations_commercedrafts}}');

        echo "Done dropping translations_commercedrafts table...\n";

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "mYYYYMMDD_HHMMSS_drop_commerce_draft_table cannot be reverted.\n";
        return false;
    }
}
