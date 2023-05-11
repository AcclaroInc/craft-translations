<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m230503_053114_add_prevent_slug_translation_field_to_orders_table_v2 migration.
 */
class m230503_053114_add_prevent_slug_translation_field_to_orders_table_v2 extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
       

     
        $this->addColumn('{{%translations_orders}}', 'preventSlugTranslation', $this->integer()->notNull()->defaultValue(0)->after('trackTargetChanges'));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropColumn('{{%translations_orders}}', 'preventSlugTranslation');
        return false;
    }
}
