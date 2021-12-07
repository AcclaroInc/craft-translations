<?php

namespace acclaro\translations;

class Constants
{
    const PLUGIN_SCHEMA_VERSION = '1.3.6';
    const PLUGIN_SLUG = 'translations';
    const CRAFT_MIN_VERSION = '3.7.9';
    const WORD_COUNT_LIMIT = 2000;

    // Licenses
    const LICENSE_STATUS_VALID  = 'valid';

    // Translators
    const TRANSLATOR_DEFAULT        = 'export_import';
    const TRANSLATOR_ACCLARO        = 'acclaro';

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
    const CLASS_ASSET    = 'craft\\elements\\Asset';

    // Urls
    const URL_ORDER_DETAIL  = 'translations/orders/detail/';
    const URL_ORDERS        = 'translations/orders/';
    const URL_ORDER_CREATE  = 'translations/orders/create';
    const URL_TRANSLATIONS  = 'translations/';
    const URL_ENTRIES       = 'entries/';

    // Files format
    const FILE_FORMAT_JSON              = 'json';
    const FILE_FORMAT_CSV               = 'csv';
    const FILE_FORMAT_XML               = 'xml';
    const FILE_FORMAT_ZIP               = 'zip';
    const FILE_FORMAT_TXT               = 'txt';

    // Files status
    const FILE_STATUS_REVIEW_READY  = 'ready for review';
    const FILE_STATUS_COMPLETE      = 'complete';
    const FILE_STATUS_CANCELED      = 'canceled';
    const FILE_STATUS_FAILED        = 'failed';
    const FILE_STATUS_PUBLISHED     = 'published';
    const FILE_STATUS_IN_PROGRESS   = 'in progress';

    // Acclaro Constants
    const PRODUCTION_URL = 'https://my.acclaro.com/api2/ ';
    const SANDBOX_URL = 'https://apisandbox.acclaro.com/api2/';

    const DELIVERY = 'craftcms';
    const DEFAULT_TAG = 'CraftCMS';

    const ORDER_TAG_GROUP_HANDLE = "craftTranslations";

    // Acclaro Order Comments
    const ACCLARO_FILE_NEW = 'NEW FILE';
    const ACCLARO_FILE_CANCEL = 'CANCEL FILE';
    const ACCLARO_ORDER_CANCEL = 'CANCEL ORDER';

    const FILE_STATUSES = [
        'new','in progress','preview','ready for review','complete','canceled','published', 'failed'
    ];
    const ORDER_STATUSES = [
        'new','getting quote','needs approval','in preparation','in review','in progress',
        'ready for review','complete','canceled','published','failed'
    ];
}
