<?php

namespace acclaro\translations;

class Constants
{
    const CRAFT_MIN_VERSION = '3.7.9';
    const WORD_COUNT_LIMIT  = 2000;
    const PLUGIN_HANDLE     = 'translations';

    // Licenses
    const LICENSE_STATUS_VALID  = 'valid';

    // Translators
    const TRANSLATOR_DEFAULT            = 'export_import';
    const TRANSLATOR_ACCLARO            = 'acclaro';
    const TRANSLATOR_STATUS_ACTIVE      = 'active';
    const TRANSLATOR_STATUS_INACTIVE    = 'inactive';

    // Orders
    const ORDER_STATUS_NEW              = 'new';
    const ORDER_STATUS_NEEDS_APPROVAL   = 'needs approval';
    const ORDER_STATUS_GETTING_QUOTE    = 'getting quote';
    const ORDER_STATUS_IN_REVIEW        = 'in review';
    const ORDER_STATUS_REVIEW_READY     = 'ready for review';
    const ORDER_STATUS_IN_PREPARATION   = 'in preparation';
    const ORDER_STATUS_IN_PROGRESS      = 'in progress';
    const ORDER_STATUS_FAILED           = 'failed';
    const ORDER_STATUS_COMPLETE         = 'complete';
    const ORDER_STATUS_PUBLISHED        = 'published';
    const ORDER_STATUS_CANCELED         = 'canceled';

    // Classes
    const CLASS_GLOBAL_SET  = 'craft\\elements\\GlobalSet';
    const CLASS_CATEGORY    = 'craft\\elements\\Category';
    const CLASS_ASSET       = 'craft\\elements\\Asset';

    // Urls
    const URL_ORDER_DETAIL          = 'translations/orders/detail/';
    const URL_ORDERS                = 'translations/orders/';
    const URL_ORDER_CREATE          = 'translations/orders/create';
    const URL_TRANSLATIONS          = 'translations/';
    const URL_TRANSLATOR            = 'translations/translators';
    const URL_STATIC_TRANSLATIONS   = 'translations/static-translations';
    const URL_SETTINGS              = 'translations/settings';
    const URL_ENTRIES               = 'entries/';
    const URL_BASE_ASSETS           = '@acclaro/translations/assetbundles/src';

    // Files format
    const FILE_FORMAT_JSON  = 'json';
    const FILE_FORMAT_CSV   = 'csv';
    const FILE_FORMAT_XML   = 'xml';
    const FILE_FORMAT_ZIP   = 'zip';
    const FILE_FORMAT_TXT   = 'txt';

    const FILE_FORMAT_ALLOWED   = [
        self::FILE_FORMAT_CSV,
        self::FILE_FORMAT_JSON,
        self::FILE_FORMAT_ZIP,
        self::FILE_FORMAT_XML
    ];

    // Files status
    const FILE_STATUS_REVIEW_READY  = 'ready for review';
    const FILE_STATUS_NEW           = 'new';
    const FILE_STATUS_PREVIEW       = 'preview';
    const FILE_STATUS_COMPLETE      = 'complete';
    const FILE_STATUS_CANCELED      = 'canceled';
    const FILE_STATUS_FAILED        = 'failed';
    const FILE_STATUS_PUBLISHED     = 'published';
    const FILE_STATUS_IN_PROGRESS   = 'in progress';

    // Acclaro Constants
    const PRODUCTION_URL    = 'https://my.acclaro.com/api2/ ';
    const SANDBOX_URL       = 'https://apisandbox.acclaro.com/api2/';

    const DELIVERY      = 'craftcms';
    const DEFAULT_TAG   = 'CraftCMS';

    const ORDER_TAG_GROUP_HANDLE = "craftTranslations";

    // Acclaro Order Comments
    const ACCLARO_FILE_NEW      = 'NEW FILE';
    const ACCLARO_FILE_CANCEL   = 'CANCEL FILE';
    const ACCLARO_ORDER_CANCEL  = 'CANCEL ORDER';

    const FILE_STATUSES = [
        self::FILE_STATUS_NEW,
        self::FILE_STATUS_IN_PROGRESS,
        self::FILE_STATUS_PREVIEW,
        self::FILE_STATUS_REVIEW_READY,
        self::FILE_STATUS_COMPLETE,
        self::FILE_STATUS_CANCELED,
        self::FILE_STATUS_PUBLISHED,
        self::FILE_STATUS_FAILED
    ];

    const ORDER_STATUSES = [
        self::ORDER_STATUS_NEW,
        self::ORDER_STATUS_GETTING_QUOTE,
        self::ORDER_STATUS_NEEDS_APPROVAL,
        self::ORDER_STATUS_IN_PREPARATION,
        self::ORDER_STATUS_IN_REVIEW,
        self::ORDER_STATUS_IN_PROGRESS,
        self::ORDER_STATUS_REVIEW_READY,
        self::ORDER_STATUS_COMPLETE,
        self::ORDER_STATUS_CANCELED,
        self::ORDER_STATUS_PUBLISHED,
        self::ORDER_STATUS_FAILED
    ];

    const TRANSLATOR_STATUSES = [
        self::TRANSLATOR_STATUS_ACTIVE,
        self::TRANSLATOR_STATUS_INACTIVE
    ];

    // Static Translation
    const STATIC_TRANSLATIONS_SUPPORTED_MIME_TYPES = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/txt',
        'application/excel',
        'application/vnd.msexcel',
        'application/vnd.ms-excel',
        'text/comma-separated-values'
    ];

    const STATUS_STATIC_TRANSLATION_TRANSLATED      = 'translated';
    const STATUS_STATIC_TRANSLATION_UNTRANSLATED    = 'untranslated';

    // Translations Tables
    const TABLE_WIDGET              = '{{%translations_widgets}}';
    const TABLE_FILES               = '{{%translations_files}}';
    const TABLE_ORDERS              = '{{%translations_orders}}';
    const TABLE_TRANSLATORS         = '{{%translations_translators}}';
    const TABLE_TRANSLATIONS        = '{{%translations_translations}}';
    const TABLE_GLOBAL_SET_DRAFT    = '{{%translations_globalsetdrafts}}';
    const TABLE_CATEGORY_DRAFT      = '{{%translations_categorydrafts}}';
    const TABLE_ASSET_DRAFT         = '{{%translations_assetdrafts}}';

    // Job Descriptions
    const JOB_ACCLARO_UPDATING_REVIEW_URL   = 'Updating Acclaro review urls';
    const JOB_ACCLARO_SENDING_ORDER         = 'Sending order to Acclaro';
    const JOB_APPLYING_DRAFT                = 'Applying translation drafts';
    const JOB_CREATING_DRAFT                = 'Creating translation drafts';
    const JOB_DELETING_DRAFT                = 'Deleting translation drafts';
    const JOB_IMPORTING_FILES               = 'Importing translation files';
    const JOB_REGENERATING_PREVIEW_URL      = 'Regenerating preview urls';

    // Nested Field Types
    const NESTED_FIELD_TYPES = [
        'craft\fields\Matrix',
        'craft\fields\Assets',
        'verbb\supertable\fields\SuperTableField',
        'benf\neo\Field'
    ];
}
