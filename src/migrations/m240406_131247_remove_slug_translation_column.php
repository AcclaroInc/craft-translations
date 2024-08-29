<?php

namespace acclaro\translations\migrations;

use craft\db\Migration;
use acclaro\translations\Constants;

/**
 * m240406_131247_remove_slug_translation_column migration.
 */
class m240406_131247_remove_slug_translation_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropColumn(Constants::TABLE_ORDERS, 'preventSlugTranslation');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240406_131247_remove_slug_translation_column cannot be reverted.\n";
        return false;
    }
}
