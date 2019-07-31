<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190730_182738_add_draft_foreign_key migration.
 */
class m190730_182738_add_draft_foreign_key extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Place migration code here...
        echo "Adding draft foreign key...\n";
        // Disable foreign checks since our draftIds might not match with new drafts table
        $this->db->createCommand('SET FOREIGN_KEY_CHECKS=0;')->execute();
        $this->addForeignKey(null,'{{%translations_files}}',['draftId'],'{{%drafts}}',['id'],'CASCADE',null);
        $this->db->createCommand('SET FOREIGN_KEY_CHECKS=1;')->execute();
        echo "Done adding draft foreign key...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190730_182738_add_draft_foreign_key cannot be reverted.\n";
        return false;
    }
}
