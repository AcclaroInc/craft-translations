<?php

error_reporting(E_ALL & ~E_DEPRECATED);

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';
require $root . '/vendor/craftcms/cms/src/Craft.php';

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            "%s\nExpected: %s\nActual: %s",
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

$application = new yii\console\Application([
    'id' => 'translations-regression-tests',
    'basePath' => $root,
    'components' => [
        'db' => [
            'class' => yii\db\Connection::class,
            'dsn' => 'sqlite::memory:',
            'tablePrefix' => 'client_',
        ],
    ],
]);

$migration = new acclaro\translations\migrations\m260908_120000_create_file_metadata_table();
$migration->safeUp();
$migration->safeUp();

$table = $application->db->schema->getTableSchema(
    acclaro\translations\Constants::TABLE_FILE_METADATA,
    true
);

assertSameValue(
    'client_translation_file_metadata',
    $table?->fullName,
    'The migration must use the configured Craft table prefix and be safe to run twice.'
);
assertSameValue(
    ['order_id', 'element_id', 'metadata_key'],
    $application->db->schema->findUniqueIndexes($table)['uq_translation_file_metadata'] ?? null,
    'The migration must enforce one metadata value per order, element, and key.'
);

$repository = new acclaro\translations\services\repository\FileMetadataRepository();
$translator = new acclaro\translations\services\ElementTranslator();
$topLevelMap = '{"field-uid":"content"}';
$nestedMapForFirstOrder = '{"asset-uid":"legacyImage"}';
$nestedMapForSecondOrder = '{"asset-uid":"currentImage"}';

$content = $repository->storeFieldMaps([
    'title' => 'Example title',
    '__meta__fieldmap__' => $topLevelMap,
    'contentBlocks.new1.__meta__fieldmap__' => $nestedMapForFirstOrder,
], 1001, 5001);

assertSameValue(
    ['title' => 'Example title'],
    $content,
    'Internal field-map metadata must be stored without entering the translation file.'
);
assertSameValue(
    [
        '__meta__fieldmap__' => $topLevelMap,
        'contentBlocks.new1.__meta__fieldmap__' => $nestedMapForFirstOrder,
    ],
    $repository->findFieldMaps(1001, 5001),
    'Top-level and nested field-map metadata must be retained.'
);

$mergedData = $translator->mergeFlatTargetData(
    $content,
    $repository->findFieldMaps(1001, 5001)
);
assertSameValue(
    $topLevelMap,
    $mergedData['__meta__fieldmap__'] ?? null,
    'Top-level field maps must be restored during import.'
);
assertSameValue(
    $nestedMapForFirstOrder,
    $mergedData['contentBlocks']['new1']['__meta__fieldmap__'] ?? null,
    'Nested field maps must be restored during import.'
);

$legacyData = $translator->getTargetData(json_encode([
    'content' => [
        '__fieldmap__.field-uid' => 'legacyHandle',
        'legacyHandle' => 'Translated value',
    ],
]));
assertSameValue(
    'legacyHandle',
    $legacyData['__fieldmap__']['field-uid'] ?? null,
    'Field maps exported by version 4.2.3 must remain readable.'
);

$repository->storeFieldMaps([
    'contentBlocks.new1.__meta__fieldmap__' => $nestedMapForSecondOrder,
], 1002, 5001);

assertSameValue(
    $nestedMapForFirstOrder,
    $repository->findFieldMaps(1001, 5001)['contentBlocks.new1.__meta__fieldmap__'] ?? null,
    'Metadata lookup must remain isolated to the first order.'
);
assertSameValue(
    $nestedMapForSecondOrder,
    $repository->findFieldMaps(1002, 5001)['contentBlocks.new1.__meta__fieldmap__'] ?? null,
    'Metadata lookup must select the requested order.'
);

$updatedMap = '{"asset-uid":"latestImage"}';
$repository->storeFieldMaps([
    'contentBlocks.new1.__meta__fieldmap__' => $updatedMap,
], 1001, 5001);

assertSameValue(
    $updatedMap,
    $repository->findFieldMaps(1001, 5001)['contentBlocks.new1.__meta__fieldmap__'] ?? null,
    'Re-exporting the same order must update its existing metadata.'
);
assertSameValue(
    $nestedMapForSecondOrder,
    $repository->findFieldMaps(1002, 5001)['contentBlocks.new1.__meta__fieldmap__'] ?? null,
    'Updating one order must not alter another order.'
);

$migration->safeUp();
assertSameValue(
    $updatedMap,
    $repository->findFieldMaps(1001, 5001)['contentBlocks.new1.__meta__fieldmap__'] ?? null,
    'Running the upgrade against an existing metadata table must preserve its data.'
);

assertSameValue(
    null,
    $application->db->schema->getTableSchema('translation_file_metadata', true),
    'The plugin must not create or use an unprefixed metadata table.'
);

$application->set('db', [
    'class' => yii\db\Connection::class,
    'dsn' => 'sqlite::memory:',
]);

$unprefixedMigration = new acclaro\translations\migrations\m260908_120000_create_file_metadata_table();
$unprefixedMigration->safeUp();
$repository->storeFieldMaps([
    '__meta__fieldmap__' => $topLevelMap,
], 2001, 6001);

assertSameValue(
    $topLevelMap,
    $repository->findFieldMaps(2001, 6001)['__meta__fieldmap__'] ?? null,
    'Metadata storage must also work when no table prefix is configured.'
);

echo "PASS: prefixed metadata migration\n";
echo "PASS: unprefixed metadata migration\n";
echo "PASS: top-level and nested metadata storage\n";
echo "PASS: order-isolated lookup and upsert\n";
