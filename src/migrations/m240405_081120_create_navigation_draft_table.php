<?php

namespace acclaro\translations\migrations;

use acclaro\translations\Constants;
use Craft;
use craft\db\Migration;

/**
 * m240405_081120_create_navigation_draft_table migration.
 */
class m240405_081120_create_navigation_draft_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        echo "Creating translations_navigationdrafts table...\n";
        $this->dropTableIfExists(Constants::TABLE_NAVIGATION_DRAFT);

        $this->createTable(
            Constants::TABLE_NAVIGATION_DRAFT,
            [
                'id'            => $this->primaryKey(),
                'name'          => $this->string()->notNull(),
                'title'         => $this->string()->notNull(),
                'navId'         => $this->integer()->notNull(),
                'site'          => $this->integer()->notNull(),
                'data'          => $this->mediumText()->notNull(),
                'dateCreated'   => $this->dateTime()->notNull(),
                'dateUpdated'   => $this->dateTime()->notNull(),
                'uid'           => $this->uid()
            ]
        );

        echo "Done creating translations_navigationdrafts column...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240405_081120_create_navigation_draft_table cannot be reverted.\n";
        return false;
    }
}
