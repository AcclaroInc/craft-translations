<?php

namespace acclaro\translationsforcraft\migrations;

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
        echo "Creating translationsforcraft_widgets column...\n";
        $this->dropTableIfExists('{{%translationsforcraft_widgets}}');

        $this->createTable(
            '{{%translationsforcraft_widgets}}',
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

        $this->createIndex(null, '{{%translationsforcraft_widgets}}', ['userId'], false);
        
        $this->addForeignKey(null,'{{%translationsforcraft_widgets}}',['userId'],'{{%users}}',['id'],'CASCADE',null);
        echo "Done creating translationsforcraft_widgets column...\n";
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
