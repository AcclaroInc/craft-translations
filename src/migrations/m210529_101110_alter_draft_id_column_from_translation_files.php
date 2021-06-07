<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m210529_101110_alter_draft_id_column_from_translation_files migration.
 */
class m210529_101110_alter_draft_id_column_from_translation_files extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        echo "Altering translations_files draftId column...\n";
        $this->alterColumn('{{%translations_files}}', 'draftId', $this->integer());
        echo "Done altering translations_files draftId column...\n";

        echo "Adding translations_orders trackChanges column...\n";
        $this->addColumn('{{%translations_orders}}', 'trackChanges', $this->integer()->defaultValue(0));
        echo "Done adding translations_orders trackChanges column...\n";

        echo "Adding translations_orders asynchronousPublishing column...\n";
        $this->addColumn('{{%translations_orders}}', 'asynchronousPublishing', $this->integer()->defaultValue(0));
        echo "Done adding translations_orders asynchronousPublishing column...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210529_101110_alter_draft_id_column_from_translation_files cannot be reverted.\n";
        return false;
    }
}
