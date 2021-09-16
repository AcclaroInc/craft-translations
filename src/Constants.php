<?php

namespace acclaro\translations;

class Constants
{
    const WORD_COUNT_LIMIT = 2000;

    // Licenses
    const LICENSE_STATUS_VALID  = 'valid';

    // Translators
    const TRANSLATOR_EXPORT_IMPORT  = 'export_import';
    const TRANSLATOR_ACCLARO        = 'acclaro';

    // Orders
    const ORDER_STATUS_NEW              = 'new';
    const ORDER_STATUS_NEEDS_APPROVAL   = 'needs approval';
    const ORDER_STATUS_GETTING_QUOTE    = 'getting quote';
    const ORDER_STATUS_IN_PREPARATION   = 'in preparation';
    const ORDER_STATUS_IN_PROGRESS      = 'in progress';
    const ORDER_STATUS_FAILED           = 'failed';
    const ORDER_STATUS_COMPLETE         = 'complete';
    const ORDER_STATUS_PUBLISHED        = 'published';

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
    const DEFAULT_FILE_EXPORT_FORMAT    = 'json';
    const FILE_FORMAT_CSV               = 'csv';
    const FILE_FORMAT_XML               = 'xml';
    const FILE_FORMAT_ZIP               = 'zip';
    const FILE_FORMAT_TXT               = 'txt';

    // Files status
    const FILE_STATUS_COMPLETE      = 'complete';
    const FILE_STATUS_FAILED        = 'failed';
    const FILE_STATUS_PUBLISHED     = 'published';
    const FILE_STATUS_IN_PROGRESS   = 'in progress';
}
