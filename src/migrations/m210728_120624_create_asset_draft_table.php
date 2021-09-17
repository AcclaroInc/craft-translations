<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m210728_120624_create_asset_draft_table migration.
 */
class m210728_120624_create_asset_draft_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        echo "Creating translations_assetdrafts table...\n";
        $this->dropTableIfExists('{{%translations_assetdrafts}}');

        $this->createTable(
            '{{%translations_assetdrafts}}',
            [
                'id'            => $this->primaryKey(),
                'name'          => $this->string()->notNull(),
                'title'         => $this->string()->notNull(),
                'assetId'       => $this->integer()->notNull(),
                'site'          => $this->integer()->notNull(),
                'data'          => $this->mediumText()->notNull(),
                'dateCreated'   => $this->dateTime()->notNull(),
                'dateUpdated'   => $this->dateTime()->notNull(),
                'uid'           => $this->uid()
            ]
        );

        echo "Done creating translations_assetdrafts column...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210728_120624_create_asset_draft_table cannot be reverted.\n";
        return false;
    }
}
