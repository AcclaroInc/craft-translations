<?php

namespace acclaro\translations;

class Constants
{
    const PLUGIN_SCHEMA_VERSION = '1.3.7';
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
    const ORDER_STATUS_PENDING          = 'pending';
    const ORDER_STATUS_MODIFIED         = 'modified';
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
    const CLASS_ENTRY       = 'craft\\elements\\Entry';

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
    const FILE_STATUS_MODIFIED      = 'modified';
    const FILE_STATUS_PREVIEW       = 'preview';
    const FILE_STATUS_COMPLETE      = 'complete';
    const FILE_STATUS_CANCELED      = 'canceled';
    const FILE_STATUS_FAILED        = 'failed';
    const FILE_STATUS_PUBLISHED     = 'published';
    const FILE_STATUS_IN_PROGRESS   = 'in progress';

    // Acclaro Constants
    const PRODUCTION_URL    = 'https://my.acclaro.com/api/v2/';
    const SANDBOX_URL       = 'https://apisandbox.acclaro.com/api/v2/';

    const DELIVERY      = 'craftcms';
    const DEFAULT_TAG   = 'CraftCMS';

    const ORDER_TAG_GROUP_HANDLE = "craftTranslations";

    // Acclaro Order Comments
    const ACCLARO_FILE_NEW      = 'NEW FILE';
    const ACCLARO_FILE_CANCEL   = 'CANCEL FILE';
    const ACCLARO_ORDER_CANCEL  = 'CANCEL ORDER';

    const FILE_STATUSES = [
        self::FILE_STATUS_NEW,
        self::FILE_STATUS_MODIFIED,
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
        self::ORDER_STATUS_PENDING,
        self::ORDER_STATUS_MODIFIED,
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

    // Craft Graphql data
    const GRAPHQL_API_BASE_URI = 'https://acclarocraft3.com/api';
    const GRAPHQL_ISO_MAPPING_TOKEN = 'G3LIVghEQhBwZEqDSaXYgTB6STSHDiqs';
    const GRAPHQL_ISO_MAPPING_QUERY = 'query MyQuery {
      entry(slug: "translations-plugin-iso-mapping") {
        dateUpdated @formatDateTime (format: "Y-m-d H:m:s")
        ... on translationsPluginIsoMapping_translationsPluginIsoMapping_Entry {
          isoMappingTable {
            craftIsoCode
            acclaroIsoCode
          }
        }
      }
    }';

    // Api Constant
    const REQUEST_METHOD_GET = 'GET';
    const REQUEST_METHOD_POST = 'POST';

    // Acclaro Api
    const ACCLARO_API_GET_ACCOUNT           = 'info/account';
    const ACCLARO_API_GET_LANGUAGES         = 'info/languages';
    const ACCLARO_API_GET_LANGUAGE_PAIRS    = 'info/language-pairs';

    const ACCLARO_API_CREATE_ORDER              = 'orders';
    const ACCLARO_API_GET_ORDER                 = 'orders/{orderid}';
    const ACCLARO_API_EDIT_ORDER                = 'orders/{orderid}';
    const ACCLARO_API_SUBMIT_ORDER              = 'orders/{orderid}/submit';
    const ACCLARO_API_ADD_ORDER_TAG             = 'orders/{orderid}/tag';
    const ACCLARO_API_DELETE_ORDER_TAG          = 'orders/{orderid}/tag-delete';
    const ACCLARO_API_ADD_ORDER_COMMENT         = 'orders/{orderid}/comment';
    const ACCLARO_API_REQUEST_ORDER_CALLBACK    = 'orders/{orderid}/callback';

    const ACCLARO_API_SEND_SOURCE_FILE      = 'orders/{orderid}/files';
    const ACCLARO_API_GET_ORDER_FILES_INFO  = 'orders/{orderid}/files-info';
    const ACCLARO_API_GET_FILE              = 'orders/{orderid}/files/{fileid}';
    const ACCLARO_API_GET_FILE_STATUS       = 'orders/{orderid}/files/{fileid}/status';
    const ACCLARO_API_ADD_FILE_COMMENT      = 'orders/{orderid}/files/{fileid}/comment';
    const ACCLARO_API_REQUEST_FILE_CALLBACK = 'orders/{orderid}/files/{fileid}/callback';
    const ACCLARO_API_ADD_FILE_REVIEW_URL   = 'orders/{orderid}/files/{fileid}/review-url';

    // Iso mapping
    const PLUGIN_STORAGE_LOCATION = "@storage/" . self::PLUGIN_HANDLE;
    const ISO_MAPPING_FILE_NAME   = 'iso_mapping.' . self::FILE_FORMAT_JSON;

    const SITE_DEFAULT_ALIASES = [
        'af-NA' => 'af',
        'af-ZA' => 'af',
        'ar-001' => 'ar',
        'ar-AE' => 'ar',
        'da-DK' => 'da',
        'de' => 'de-de',
        'en' => 'en-us',
        'fi-FI' => 'fi',
        'fr' => 'fr-fr',
        'it-IT' => 'it',
        'ja-JP' => 'ja',
        'ko-KR' => 'ko',
        'pl-PL' => 'pl',
        'pt' => 'pt-pt',
        'ru-RU' => 'ru',
        'sv-SE' => 'sv',
        'th-TH' => 'th',
        'vi-VN' => 'vi',
        'zh-Hans' => 'zh-CN',
        'zh-Hans-CN' => 'zh-CN',
        'zh-Hant' => 'zh-TW',
        'es-419' => 'es-la'
    ];

    const SUPPORTED_FIELD_TYPES = [
        'craft\fields\Tags',
        'craft\fields\Table',
        'craft\fields\Assets',
        'craft\fields\Matrix',
        'craft\fields\Number',
        'craft\fields\Entries',
        'craft\fields\Dropdown',
        'craft\fields\PlainText',
        'craft\fields\Categories',
        'craft\fields\Checkboxes',
        'craft\fields\MultiSelect',
        'craft\fields\RadioButtons',
        'benf\neo\Field',
        'typedlinkfield\fields\LinkField',
        'craft\redactor\Field',
        'fruitstudios\linkit\fields\LinkitField',
        'luwes\codemirror\fields\CodeMirrorField',
        'verbb\supertable\fields\SuperTableField',
        'nystudio107\seomatic\fields\SeoSettings',
        'lenz\linkfield\fields\LinkField',
        'newism\fields\fields\Telephone',
        'newism\fields\fields\Address',
        'newism\fields\fields\Email',
        'newism\fields\fields\Embed',
        'newism\fields\fields\PersonName',
        'newism\fields\fields\Gender',
        'ether\seo\fields\SeoField'
    ];

    const UNRELATED_FIELD_TYPES = [
        'craft\fields\Color',
        'craft\fields\Date',
        'craft\fields\Email',
        'craft\fields\Lightswitch',
        'craft\fields\Time',
        'craft\fields\Url',
        'craft\fields\Users'
    ];
}
