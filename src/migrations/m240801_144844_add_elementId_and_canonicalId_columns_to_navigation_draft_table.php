<?php

namespace acclaro\translations\migrations;

use acclaro\translations\Constants;
use Craft;
use craft\db\Migration;

/**
 * m240801_144844_add_elementId_and_canonicalId_columns_to_navigation_draft_table migration.
 */
class m240801_144844_add_elementId_and_canonicalId_columns_to_navigation_draft_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        echo "Adding elementId and canonicalId columns to translations_navigationdrafts table...\n";

        $this->addColumn(
            Constants::TABLE_NAVIGATION_DRAFT,
            'canonicalId',
            $this->integer()->notNull()
        );

        echo "Done adding columns to translations_navigationdrafts table...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240801_101200_add_elementId_canonicalId_columns_to_navigation_draft_table cannot be reverted.\n";
        return false;
    }
}
