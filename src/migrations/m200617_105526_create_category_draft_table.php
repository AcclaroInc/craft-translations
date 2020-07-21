<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m200617_105526_create_category_draft_table migration.
 */
class m200617_105526_create_category_draft_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        echo "Creating translations_categorydrafts table...\n";
        $this->dropTableIfExists('{{%translations_categorydrafts}}');

        $this->createTable(
            '{{%translations_categorydrafts}}',
            [
                'id'            => $this->primaryKey(),
                'name'          => $this->string()->notNull(),
                'title'         => $this->string()->notNull(),
                'categoryId'    => $this->integer()->notNull(),
                'site'          => $this->integer()->notNull(),
                'data'          => $this->mediumText()->notNull(),
                'dateCreated'   => $this->dateTime()->notNull(),
                'dateUpdated'   => $this->dateTime()->notNull(),
                'uid'           => $this->uid()
            ]
        );

        echo "Done creating translations_categorydrafts column...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200617_105526_create_category_draft_table cannot be reverted.\n";
        return false;
    }
}
