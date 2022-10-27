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

use Craft;
use craft\db\Query;
use craft\db\Migration;

use acclaro\translations\Constants;

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
        $this->removePluginData();
        $this->removeTables();

        return true;
    }

    /**
     * @return bool
     */
    protected function createTables()
    {
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema(Constants::TABLE_FILES);
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                Constants::TABLE_FILES,
                [
                    'id'                => $this->primaryKey(),
                    'orderId'           => $this->integer()->notNull(),
                    'elementId'         => $this->integer()->notNull(),
                    'draftId'           => $this->integer(),
                    'sourceSite'        => $this->integer()->notNull(),
                    'targetSite'        => $this->integer()->notNull(),
                    'status'            => $this->enum('status', Constants::FILE_STATUSES)->defaultValue(Constants::FILE_STATUS_NEW),
                    'wordCount'         => $this->integer(),
                    'source'            => $this->longText(),
                    'reference'         => $this->longText(),
                    'target'            => $this->longText(),
                    'previewUrl'        => $this->text(),
                    'serviceFileId'     => $this->string()->defaultValue(''),
                    'dateCreated'       => $this->dateTime()->notNull(),
                    'dateUpdated'       => $this->dateTime()->notNull(),
                    'dateDelivered'     => $this->dateTime(),
                    'dateDeleted'       => $this->dateTime()->null(),
                    'uid'               => $this->uid()
                ]
            );
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema(Constants::TABLE_GLOBAL_SET_DRAFT);
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                Constants::TABLE_GLOBAL_SET_DRAFT,
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

        $tableSchema = Craft::$app->db->schema->getTableSchema(Constants::TABLE_COMMERCE_DRAFT);
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                Constants::TABLE_COMMERCE_DRAFT,
                [
                    'id'            => $this->primaryKey(),
                    'name'          => $this->string()->notNull(),
                    'title'         => $this->string()->notNull(),
                    'productId'     => $this->integer()->notNull(),
                    'typeId'        => $this->integer()->notNull(),
                    'site'          => $this->integer()->notNull(),
                    'data'          => $this->mediumText()->notNull(),
                    'variants'      => $this->mediumText()->notNull(),
                    'dateCreated'   => $this->dateTime()->notNull(),
                    'dateUpdated'   => $this->dateTime()->notNull(),
                    'uid'           => $this->uid()
                ]
            );
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema(Constants::TABLE_ORDERS);
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                Constants::TABLE_ORDERS,
                [
                    'translatorId'              => $this->integer(),
                    'id'                        => $this->integer()->notNull(),
                    'ownerId'                   => $this->integer()->notNull(),
                    'sourceSite'                => $this->integer()->notNull(),
                    'targetSites'               => $this->string(1020)->notNull()->defaultValue(''),
                    'status'                    => $this->enum('status', Constants::ORDER_STATUSES)->defaultValue(Constants::ORDER_STATUS_PENDING),
                    'requestedDueDate'          => $this->dateTime(),
                    'orderDueDate'              => $this->dateTime(),
                    'comments'                  => $this->text(),
                    'activityLog'               => $this->longText(),
                    'dateOrdered'               => $this->dateTime(),
                    'serviceOrderId'            => $this->string()->defaultValue(''),
                    'entriesCount'              => $this->integer()->notNull(),
                    'wordCount'                 => $this->integer()->notNull(),
                    'elementIds'                => $this->string(8160)->notNull()->defaultValue(''),
                    'tags'                      => $this->string(8160)->notNull()->defaultValue(''),
                    'trackChanges'              => $this->integer()->defaultValue(0),
					'includeTmFiles'            => $this->integer()->defaultValue(0),
					'trackTargetChanges'        => $this->integer()->defaultValue(0),
                    'asynchronousPublishing'    => $this->integer()->defaultValue(0),
                    'requestQuote'              => $this->integer()->defaultValue(0),
                    'dateCreated'               => $this->dateTime()->notNull(),
                    'dateUpdated'               => $this->dateTime()->notNull(),
                    'uid'                       => $this->uid(),
                    'PRIMARY KEY([[id]])'
                ]
            );
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema(Constants::TABLE_TRANSLATORS);
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                Constants::TABLE_TRANSLATORS,
                [
                    'id'            => $this->primaryKey(),
                    'label'         => $this->string()->notNull()->defaultValue(''),
                    'service'       => $this->string()->notNull()->defaultValue(''),
                    'status'        => $this->enum('status', Constants::TRANSLATOR_STATUSES)->defaultValue(Constants::TRANSLATOR_STATUS_INACTIVE),
                    'settings'      => $this->text()->notNull(),
                    'dateCreated'   => $this->dateTime()->notNull(),
                    'dateUpdated'   => $this->dateTime()->notNull(),
                    'uid'           => $this->uid()
                ]
            );
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema(Constants::TABLE_TRANSLATIONS);
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                Constants::TABLE_TRANSLATIONS,
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

        $tableSchema = Craft::$app->db->schema->getTableSchema(Constants::TABLE_WIDGET);
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                Constants::TABLE_WIDGET,
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

        $tableSchema = Craft::$app->db->schema->getTableSchema(Constants::TABLE_ASSET_DRAFT);
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                Constants::TABLE_ASSET_DRAFT,
                [
                    'id'            => $this->primaryKey(),
                    'name'          => $this->string()->notNull(),
                    'title'         => $this->string()->notNull(),
                    'assetId'       => $this->integer()->notNull(),
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
        $this->createIndex(null, Constants::TABLE_FILES, ['orderId'], false);
        $this->createIndex(null, Constants::TABLE_FILES, ['elementId'], false);
        $this->createIndex(null, Constants::TABLE_GLOBAL_SET_DRAFT, ['globalSetId'], false);
        $this->createIndex(null, Constants::TABLE_WIDGET, ['userId'], false);
    }

    /**
     * @return void
     * addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null)
     */
    protected function addForeignKeys()
    {
        $this->addForeignKey(null, Constants::TABLE_FILES,['orderId'], Constants::TABLE_ORDERS,['id'],'CASCADE',null);
        $this->addForeignKey(null, Constants::TABLE_FILES,['elementId'],'{{%elements}}',['id'],'CASCADE',null);
        $this->addForeignKey(null, Constants::TABLE_GLOBAL_SET_DRAFT,['globalSetId'],'{{%globalsets}}',['id'],'CASCADE',null);
        $this->addForeignKey(null, Constants::TABLE_GLOBAL_SET_DRAFT,['site'],'{{%sites}}',['id'],'CASCADE',null);
        $this->addForeignKey(null, Constants::TABLE_ORDERS,['id'],'{{%elements}}',['id'],'CASCADE',null);
        $this->addForeignKey(null, Constants::TABLE_ORDERS,['ownerId'],'{{%users}}',['id'],'CASCADE',null);
        $this->addForeignKey(null, Constants::TABLE_ORDERS,['translatorId'], Constants::TABLE_TRANSLATORS,['id'],'SET NULL',null);
        $this->addForeignKey(null, Constants::TABLE_WIDGET,['userId'],'{{%users}}',['id'],'CASCADE',null);
    }

    /**
     * @return void
     */
    protected function insertDefaultData()
    {
        // Default Translator details
        $defaultTranslator = [
            "label" => "Export Import",
            "service" => Constants::TRANSLATOR_DEFAULT,
            "status" => "active",
            "settings" => "[]"
        ];
        $this->upsert(Constants::TABLE_TRANSLATORS, $defaultTranslator);

        // Default Tag group
        $data = [
            'name'      => 'Craft Translations',
            'handle'    => Constants::ORDER_TAG_GROUP_HANDLE,
        ];
        $this->upsert('{{%taggroups}}', $data);
    }

    /**
     * @return void
     */
    protected function removeTables()
    {
        $this->dropTableIfExists(Constants::TABLE_FILES);

        $this->dropTableIfExists(Constants::TABLE_GLOBAL_SET_DRAFT);

        $this->dropTableIfExists(Constants::TABLE_ASSET_DRAFT);

        $this->dropTableIfExists(Constants::TABLE_ORDERS);

        $this->dropTableIfExists(Constants::TABLE_TRANSLATORS);

        $this->dropTableIfExists(Constants::TABLE_TRANSLATIONS);

        $this->dropTableIfExists(Constants::TABLE_WIDGET);
    }

    /**
     * @return void
     */
    protected function removePluginData()
    {
        // Remove translations tags
        $tagGroupIds = (new Query())
            ->select(['id'])
            ->from(['{{%taggroups}}'])
            ->where(['handle' => Constants::ORDER_TAG_GROUP_HANDLE])
            ->column();

        if (! empty($tagGroupIds)) {
            $this->delete('{{%tags}}', array('IN', 'groupId', $tagGroupIds));
        }

        $this->delete('{{%taggroups}}', array('handle' => Constants::ORDER_TAG_GROUP_HANDLE));
    }
}
