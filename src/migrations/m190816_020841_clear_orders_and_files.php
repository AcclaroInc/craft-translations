<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

/**
 * m190816_020841_clear_orders_and_files migration.
 */
class m190816_020841_clear_orders_and_files extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        echo "Clearing orders, files, and globalsetdrafts tables...\n";
        if (Craft::$app->getDb()->getDriverName() === DbConfig::DRIVER_PGSQL) {
            Craft::$app->getDb()->createCommand('truncate {{%translations_files}}')->execute();
            Craft::$app->getDb()->createCommand('truncate {{%translations_globalsetdrafts}}')->execute();
            Craft::$app->getDb()->createCommand('truncate {{%translations_orders}} cascade')->execute();
        } else {
            Craft::$app->getDb()->createCommand()->checkIntegrity(false)->execute();
            Craft::$app->getDb()->createCommand()->truncateTable('{{%translations_files}}')->execute();
            Craft::$app->getDb()->createCommand()->truncateTable('{{%translations_globalsetdrafts}}')->execute();
            Craft::$app->getDb()->createCommand()->truncateTable('{{%translations_orders}}')->execute();
            Craft::$app->getDb()->createCommand()->checkIntegrity(true)->execute();

        }
        echo "Done clearing tables...\n";
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190816_020841_clear_orders_and_files cannot be reverted.\n";
        return false;
    }
}
