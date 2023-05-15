<?php

namespace acclaro\translations\migrations;

use craft\db\Migration;
use acclaro\translations\Constants;

/**
 * m230503_053114_add_prevent_slug_translation_column_to_orders_table migration.
 */
class m230503_053114_add_prevent_slug_translation_column_to_orders_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Constants::TABLE_ORDERS, 'preventSlugTranslation', $this->integer()->defaultValue(0)->after('trackTargetChanges'));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropColumn(Constants::TABLE_ORDERS, 'preventSlugTranslation');
        return true;
    }
}
