<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m211019_130248_remove_changed_widgets_from_translations_widgets migration.
 */
class m211019_130248_remove_changed_widgets_from_translations_widgets extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $widgets = [
            'acclaro\translations\widgets\RecentEntries',
            'acclaro\translations\widgets\RecentlyModified'
        ];

        $this->delete('{{%translations_widgets}}', array('IN', 'type', $widgets));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m211019_130248_remove_changed_widgets_from_translations_widgets cannot be reverted.\n";
        return false;
    }
}
