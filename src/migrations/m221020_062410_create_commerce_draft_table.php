<?php

namespace acclaro\translations\migrations;

use acclaro\translations\Constants;
use craft\db\Migration;

/**
 * m221020_062410_create_commerce_draft_table migration.
 */
class m221020_062410_create_commerce_draft_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        echo "Creating translations_commercedrafts table...\n";

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

        echo "Done creating translations_commercedrafts table...\n";

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221020_062410_create_commerce_draft_table cannot be reverted.\n";
        return false;
    }
}
