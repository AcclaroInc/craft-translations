<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190312_021921_create_widgets_table migration.
 */
class m190312_021921_create_widgets_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        echo "Creating translations_widgets column...\n";
        $this->dropTableIfExists('{{%translations_widgets}}');

        $this->createTable(
            '{{%translations_widgets}}',
            [
                'id' => $this->primaryKey(),
                'userId' => $this->integer()->notNull(),
                'type' => $this->string()->notNull(),
                'sortOrder' => $this->smallInteger()->unsigned(),
                'colspan' => $this->tinyInteger(),
                'settings' => $this->text(),
                'enabled' => $this->boolean()->defaultValue(true)->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]
        );

        $this->createIndex(null, '{{%translations_widgets}}', ['userId'], false);
        
        $this->addForeignKey(null,'{{%translations_widgets}}',['userId'],'{{%users}}',['id'],'CASCADE',null);
        echo "Done creating translations_widgets column...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190312_021921_create_widgets_table cannot be reverted.\n";
        return false;
    }
}
