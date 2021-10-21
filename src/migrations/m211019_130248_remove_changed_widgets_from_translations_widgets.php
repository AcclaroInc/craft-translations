<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\services\Users;

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
        $table = '{{%translations_widgets}}';

        $widgets = [
            'acclaro\translations\widgets\RecentEntries',
            'acclaro\translations\widgets\RecentlyModified'
        ];

        $this->delete($table, array('IN', 'type', $widgets));

        foreach ($this->getWidgetUsers($table) as $result) {
            $newWidget = [
                'userId'    => $result['userId'],
                'type'      => 'acclaro\translations\widgets\NewAndModifiedEntries',
                'sortOrder' => $result['sortOrder'] + 1,
                'colspan'   => '2',
                'settings'  => json_encode(['limit' => '10', 'days' => '7']),
                'enabled'   => 1
            ];
    
            $this->insert($table, $newWidget);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m211019_130248_remove_changed_widgets_from_translations_widgets cannot be reverted.\n";
        return false;
    }

    private function getWidgetUsers($table) {
        $data = [];

        $results = (new Query())
            ->select(['userId', 'sortOrder'])
            ->from([$table])
            ->orderBy(['sortOrder' => SORT_DESC])
            ->all();

        foreach ($results as $row) {
            if (isset($data[$row['userId']])) {
                if ($data[$row['userId']]['sortOrder'] < $row['sortOrder']) {
                    $data[$row['userId']] = $row;
                }
            } else {
                $data[$row['userId']] = $row;
            }
        }

        return $data;
    }
}
