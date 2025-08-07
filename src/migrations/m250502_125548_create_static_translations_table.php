<?php

namespace acclaro\translations\migrations;

use craft\db\Migration;
use acclaro\translations\Constants;
use acclaro\translations\services\repository\StaticTranslationsRepository;

/**
 * m250502_125548_create_static_translations_table migration.
 */
class m250502_125548_create_static_translations_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        echo "Creating translations_statictranslations table...\n";
        $this->dropTableIfExists('{{%translations_statictranslations}}');

        $this->createTable(
            Constants::TABLE_STATIC_TRANSLATIONS,
            [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->notNull(),
                'original' => $this->text(),
                'translation' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
            ]
        );

        $this->createIndex(null, Constants::TABLE_STATIC_TRANSLATIONS, ['siteId']);
        echo "Done creating translations_statictranslations table...\n";

        // echo "Seeding static translation to database...\n";
        // (new StaticTranslationsRepository())->syncWithDB();
        // echo "Completed static translation seeding to database...\n";

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250502_125548_create_static_translations_table cannot be reverted.\n";
        return false;
    }
}
