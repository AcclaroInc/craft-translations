<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m191001_215118_remove_translator_site_column migration.
 */
class m191001_215118_remove_translator_site_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        echo "Altering translations_translators status drop column sites...\n";
        $this->dropColumn('{{%translations_translators}}', 'sites');
        echo "Done drop translations_translators sites column...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191001_215118_remove_translator_site_column cannot be reverted.\n";
        return false;
    }
}
