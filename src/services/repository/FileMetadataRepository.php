<?php

namespace acclaro\translations\services\repository;

use Craft;
use craft\db\Query;
use yii\db\Expression;
use acclaro\translations\Constants;

class FileMetadataRepository
{
    public function storeFieldMaps(array $translations, int $orderId, int $elementId): array
    {
        foreach ($translations as $key => $value) {
            if (!$this->isFieldMapKey($key)) {
                continue;
            }

            $now = new Expression('CURRENT_TIMESTAMP');

            Craft::$app->db->createCommand()->upsert(
                Constants::TABLE_FILE_METADATA,
                [
                    'order_id' => $orderId,
                    'element_id' => $elementId,
                    'metadata_key' => $key,
                    'metadata_value' => $value,
                    'dateCreated' => $now,
                    'dateUpdated' => $now,
                ],
                [
                    'metadata_value' => $value,
                    'dateUpdated' => $now,
                ]
            )->execute();

            unset($translations[$key]);
        }

        return $translations;
    }

    public function findFieldMaps(int $orderId, int $elementId): array
    {
        return (new Query())
            ->select(['metadata_key', 'metadata_value'])
            ->from(Constants::TABLE_FILE_METADATA)
            ->where([
                'order_id' => $orderId,
                'element_id' => $elementId,
            ])
            ->pairs();
    }

    public function withoutFieldMaps(array $translations): array
    {
        foreach (array_keys($translations) as $key) {
            if ($this->isFieldMapKey($key)) {
                unset($translations[$key]);
            }
        }

        return $translations;
    }

    private function isFieldMapKey(string $key): bool
    {
        return $key === Constants::FIELD_MAP_METADATA_KEY ||
            str_ends_with($key, '.' . Constants::FIELD_MAP_METADATA_KEY);
    }
}
