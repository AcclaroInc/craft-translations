<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\migrations;

use acclaro\translationsforcraft\TranslationsForCraft;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

/**
 * @author    Acclaro
 * @package   TranslationsForCraft
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

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%translationsforcraft_files}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%translationsforcraft_files}}',
                [
                    'id'                => $this->primaryKey(),
                    'orderId'           => $this->integer()->notNull(),
                    'elementId'         => $this->integer()->notNull(),
                    'draftId'           => $this->integer()->notNull(),
                    'sourceSite'        => $this->integer()->notNull(),
                    'targetSite'        => $this->integer()->notNull(),
                    'status'            => $this->enum('values', ['new','in progress','preview','complete','canceled','published'])->defaultValue('new'),
                    'wordCount'         => $this->integer(),
                    'source'            => $this->longText(),
                    'target'            => $this->longText(),
                    'previewUrl'        => $this->string()->defaultValue(''),
                    'serviceFileId'     => $this->string()->defaultValue(''),
                    'dateCreated'       => $this->dateTime()->notNull(),
                    'dateUpdated'       => $this->dateTime()->notNull(),
                    'uid'               => $this->uid()
                ]
            );
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%translationsforcraft_globalsetdrafts}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%translationsforcraft_globalsetdrafts}}',
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

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%translationsforcraft_orders}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%translationsforcraft_orders}}',
                [
                    'id'                => $this->integer()->notNull(),
                    'translatorId'      => $this->integer(),
                    'ownerId'           => $this->integer()->notNull(),
                    'sourceSite'        => $this->integer()->notNull(),
                    'targetSites'       => $this->string(1020)->notNull()->defaultValue(''),
                    'status'            => $this->enum('values', ['new','getting quote','needs approval','in preparation','in progress','complete','canceled','published','failed'])->defaultValue('new'),
                    'requestedDueDate'  => $this->dateTime(),
                    'comments'          => $this->text(),
                    'activityLog'       => $this->text(),
                    'dateOrdered'       => $this->dateTime(),
                    'serviceOrderId'    => $this->string()->defaultValue(''),
                    'entriesCount'      => $this->integer()->notNull(),
                    'wordCount'         => $this->integer()->notNull(),
                    'elementIds'        => $this->string(2040)->notNull()->defaultValue(''),
                    'dateCreated'       => $this->dateTime()->notNull(),
                    'dateUpdated'       => $this->dateTime()->notNull(),
                    'uid'               => $this->uid(),
                    'PRIMARY KEY([[id]])'
                ]
            );
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%translationsforcraft_translators}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%translationsforcraft_translators}}',
                [
                    'id'            => $this->primaryKey(),
                    'label'         => $this->string()->notNull()->defaultValue(''),
                    'service'       => $this->string()->notNull()->defaultValue(''),
                    'sites'         => $this->text()->notNull(),
                    'status'        => $this->enum('values', ['active', 'inactive'])->defaultValue('inactive'),
                    'settings'      => $this->text()->notNull(),
                    'dateCreated'   => $this->dateTime()->notNull(),
                    'dateUpdated'   => $this->dateTime()->notNull(),
                    'uid'           => $this->uid()
                ]
            );
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%translationsforcraft_translations}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%translationsforcraft_translations}}',
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
        
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%translationsforcraft_widgets}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
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
        }

        return $tablesCreated;
    }

    /**
     * @return void
     * createIndex($name, $table, $columns, $unique = false)
     */
    protected function createIndexes()
    {
        $this->createIndex(null, '{{%translationsforcraft_files}}', ['orderId'], false);
        $this->createIndex(null, '{{%translationsforcraft_files}}', ['elementId'], false);
        $this->createIndex(null, '{{%translationsforcraft_globalsetdrafts}}', ['globalSetId'], false);
        $this->createIndex(null, '{{%translationsforcraft_widgets}}', ['userId'], false);
    }

    /**
     * @return void
     * addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null)
     */
    protected function addForeignKeys()
    {
        $this->addForeignKey(null,'{{%translationsforcraft_files}}',['orderId'],'{{%translationsforcraft_orders}}',['id'],'CASCADE',null);
        $this->addForeignKey(null,'{{%translationsforcraft_files}}',['elementId'],'{{%elements}}',['id'],'CASCADE',null);
        $this->addForeignKey(null,'{{%translationsforcraft_globalsetdrafts}}',['globalSetId'],'{{%globalsets}}',['id'],'CASCADE',null);
        $this->addForeignKey(null,'{{%translationsforcraft_globalsetdrafts}}',['site'],'{{%sites}}',['id'],'CASCADE',null);
        $this->addForeignKey(null,'{{%translationsforcraft_orders}}',['id'],'{{%elements}}',['id'],'CASCADE',null);
        $this->addForeignKey(null,'{{%translationsforcraft_orders}}',['ownerId'],'{{%users}}',['id'],'CASCADE',null);
        $this->addForeignKey(null,'{{%translationsforcraft_orders}}',['translatorId'],'{{%translationsforcraft_translators}}',['id'],'SET NULL',null);
        $this->addForeignKey(null,'{{%translationsforcraft_widgets}}',['userId'],'{{%users}}',['id'],'CASCADE',null);
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
        $this->dropTableIfExists('{{%translationsforcraft_files}}');

        $this->dropTableIfExists('{{%translationsforcraft_globalsetdrafts}}');

        $this->dropTableIfExists('{{%translationsforcraft_orders}}');

        $this->dropTableIfExists('{{%translationsforcraft_translators}}');

        $this->dropTableIfExists('{{%translationsforcraft_translations}}');
        
        $this->dropTableIfExists('{{%translationsforcraft_widgets}}');
    }

}
