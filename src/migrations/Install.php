<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\migrations;

use acclaro\translations\Translations;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
            $this->insertDefaultData();
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    /**
     * @return bool
     */
    protected function createTables()
    {
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%translations_files}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%translations_files}}',
                [
                    'id'                => $this->primaryKey(),
                    'orderId'           => $this->integer()->notNull(),
                    'elementId'         => $this->integer()->notNull(),
                    'draftId'           => $this->integer()->notNull(),
                    'sourceSite'        => $this->integer()->notNull(),
                    'targetSite'        => $this->integer()->notNull(),
                    'status'            => $this->enum('status', ['new','in progress','preview','complete','canceled','published', 'failed'])->defaultValue('new'),
                    'wordCount'         => $this->integer(),
                    'source'            => $this->longText(),
                    'target'            => $this->longText(),
                    'previewUrl'        => $this->string()->defaultValue(''),
                    'serviceFileId'     => $this->string()->defaultValue(''),
                    'dateCreated'       => $this->dateTime()->notNull(),
                    'dateUpdated'       => $this->dateTime()->notNull(),
                    'dateDelivered'     => $this->dateTime(),
                    'dateDeleted'       => $this->dateTime()->null()->after('dateUpdated'),
                    'uid'               => $this->uid()
                ]
            );
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%translations_globalsetdrafts}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%translations_globalsetdrafts}}',
                [
                    'id'            => $this->primaryKey(),
                    'name'          => $this->string()->notNull(),
                    'globalSetId'   => $this->integer()->notNull(),
                    'site'          => $this->integer()->notNull(),
                    'data'          => $this->mediumText()->notNull(),
                    'dateCreated'   => $this->dateTime()->notNull(),
                    'dateUpdated'   => $this->dateTime()->notNull(),
                    'uid'           => $this->uid()
                ]
            );
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%translations_orders}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%translations_orders}}',
                [
                    'id'                => $this->integer()->notNull(),
                    'translatorId'      => $this->integer(),
                    'ownerId'           => $this->integer()->notNull(),
                    'sourceSite'        => $this->integer()->notNull(),
                    'targetSites'       => $this->string(1020)->notNull()->defaultValue(''),
                    'status'            => $this->enum('status', ['new','getting quote','needs approval','in preparation','in review','in progress','complete','canceled','published','failed'])->defaultValue('new'),
                    'requestedDueDate'  => $this->dateTime(),
                    'comments'          => $this->text(),
                    'activityLog'       => $this->text(),
                    'dateOrdered'       => $this->dateTime(),
                    'serviceOrderId'    => $this->string()->defaultValue(''),
                    'entriesCount'      => $this->integer()->notNull(),
                    'wordCount'         => $this->integer()->notNull(),
                    'elementIds'        => $this->string(8160)->notNull()->defaultValue(''),
                    'dateCreated'       => $this->dateTime()->notNull(),
                    'dateUpdated'       => $this->dateTime()->notNull(),
                    'uid'               => $this->uid(),
                    'PRIMARY KEY([[id]])'
                ]
            );
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%translations_translators}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%translations_translators}}',
                [
                    'id'            => $this->primaryKey(),
                    'label'         => $this->string()->notNull()->defaultValue(''),
                    'service'       => $this->string()->notNull()->defaultValue(''),
                    'status'        => $this->enum('status', ['active', 'inactive'])->defaultValue('inactive'),
                    'settings'      => $this->text()->notNull(),
                    'dateCreated'   => $this->dateTime()->notNull(),
                    'dateUpdated'   => $this->dateTime()->notNull(),
                    'uid'           => $this->uid()
                ]
            );
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%translations_translations}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%translations_translations}}',
                [
                    'id'                => $this->primaryKey(),
                    'sourceSite'        => $this->integer()->notNull(),
                    'targetSite'        => $this->integer()->notNull(),
                    'source'            => $this->text(),
                    'target'            => $this->text(),
                    'dateCreated'       => $this->dateTime()->notNull(),
                    'dateUpdated'       => $this->dateTime()->notNull(),
                    'uid'               => $this->uid()
                ]
            );
        }
        
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%translations_widgets}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
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
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%translations_categorydrafts}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
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
        }

        return $tablesCreated;
    }

    /**
     * @return void
     * createIndex($name, $table, $columns, $unique = false)
     */
    protected function createIndexes()
    {
        $this->createIndex(null, '{{%translations_files}}', ['orderId'], false);
        $this->createIndex(null, '{{%translations_files}}', ['elementId'], false);
        $this->createIndex(null, '{{%translations_globalsetdrafts}}', ['globalSetId'], false);
        $this->createIndex(null, '{{%translations_widgets}}', ['userId'], false);
    }

    /**
     * @return void
     * addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null)
     */
    protected function addForeignKeys()
    {
        $this->addForeignKey(null,'{{%translations_files}}',['orderId'],'{{%translations_orders}}',['id'],'CASCADE',null);
        $this->addForeignKey(null,'{{%translations_files}}',['elementId'],'{{%elements}}',['id'],'CASCADE',null);
        $this->addForeignKey(null,'{{%translations_globalsetdrafts}}',['globalSetId'],'{{%globalsets}}',['id'],'CASCADE',null);
        $this->addForeignKey(null,'{{%translations_globalsetdrafts}}',['site'],'{{%sites}}',['id'],'CASCADE',null);
        $this->addForeignKey(null,'{{%translations_orders}}',['id'],'{{%elements}}',['id'],'CASCADE',null);
        $this->addForeignKey(null,'{{%translations_orders}}',['ownerId'],'{{%users}}',['id'],'CASCADE',null);
        $this->addForeignKey(null,'{{%translations_orders}}',['translatorId'],'{{%translations_translators}}',['id'],'SET NULL',null);
        $this->addForeignKey(null,'{{%translations_widgets}}',['userId'],'{{%users}}',['id'],'CASCADE',null);
    }

    /**
     * @return void
     */
    protected function insertDefaultData()
    {
    }

    /**
     * @return void
     */
    protected function removeTables()
    {
        $this->dropTableIfExists('{{%translations_files}}');

        $this->dropTableIfExists('{{%translations_globalsetdrafts}}');

        $this->dropTableIfExists('{{%translations_orders}}');

        $this->dropTableIfExists('{{%translations_translators}}');

        $this->dropTableIfExists('{{%translations_translations}}');
        
        $this->dropTableIfExists('{{%translations_widgets}}');
    }

}
