<?php

namespace acclaro\translations\migrations;

use craft\db\Migration;
use acclaro\translations\Constants;

class m250602_143000_add_performance_indexes extends Migration
{
    public function safeUp(): bool
    {
        // Orders table - Indexing
        $this->createIndex(null, Constants::TABLE_ORDERS, ['status']);
        $this->createIndex(null, Constants::TABLE_ORDERS, ['translatorId']);

        // Files table - Indexing
        $this->createIndex(null, Constants::TABLE_FILES, ['status']);
        $this->createIndex(null, Constants::TABLE_FILES, ['dateUpdated']);
        $this->createIndex(null, Constants::TABLE_FILES, ['targetSite']);
        $this->createIndex(null, Constants::TABLE_FILES, ['draftId']);

        // Translators table - Indexing
        $this->createIndex(null, Constants::TABLE_TRANSLATORS, ['service']);

        // Asset Drafts table - Indexing
        $this->createIndex(null, Constants::TABLE_ASSET_DRAFT, ['assetId']);
        $this->createIndex(null, Constants::TABLE_ASSET_DRAFT, ['site']);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m250602_143000_add_performance_indexes cannot be reverted.\n";
        return false;
    }
}