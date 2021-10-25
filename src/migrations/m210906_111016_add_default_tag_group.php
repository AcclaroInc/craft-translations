<?php

namespace acclaro\translations\migrations;

use acclaro\translations\Constants;
use Craft;
use craft\db\Migration;

/**
 * m210906_111016_add_default_tag_group migration.
 */
class m210906_111016_add_default_tag_group extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $data = [
            'name'      => 'Craft Translations',
            'handle'    => Constants::ORDER_TAG_GROUP_HANDLE,
        ];

        $this->insert('{{%taggroups}}', $data);

        $this->addColumn('{{%translations_orders}}', 'tags',
            $this->string(8160)->notNull()->defaultValue('')->after('elementIds')
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210906_111016_add_default_tag_group cannot be reverted.\n";
        return false;
    }
}
