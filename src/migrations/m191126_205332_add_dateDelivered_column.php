<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m191126_205332_add_dateDelivered_column migration.
 */
class m191126_205332_add_dateDelivered_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        echo "Adding dateDelivered column in translations_files table...\n";
        $this->addColumn('{{%translations_files}}', 'dateDelivered', $this->dateTime());
        echo "Done adding dateDelivered column in translations_files table...\n";

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191126_205332_add_dateDelivered_column cannot be reverted.\n";
        return false;
    }
}
