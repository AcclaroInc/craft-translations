<?php

namespace acclaro\translations;

class Constants
{
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

    // Craft Graphql data
    const GRAPHQL_API_BASE_URI = 'https://acclarocraft3.com/api';
    const GRAPHQL_ISO_MAPPING_TOKEN = 'G3LIVghEQhBwZEqDSaXYgTB6STSHDiqs';
    const GRAPHQL_ISO_MAPPING_QUERY = 'query MyQuery {
      entry(slug: "translations-plugin-iso-mapping") {
        ... on translationsPluginIsoMapping_translationsPluginIsoMapping_Entry {
          isoMappingTable {
            craftIsoCode
            acclaroIsoCode
          }
        }
      }
    }';

    const SITE_DEFAULT_ALIASES = array(
      /* supported 3 letter codes */
      'afr' => 'af',               //Afrikaans
      'alb' => 'sq',               //Albanian
      'ara' => 'ar',               //Arabic
      'ara-SA' => 'ar-SA',         //Arabic (Saudi Arabia)
      'ars' => 'ar-su',            //Arabic (Sudanese)
      'hye' => 'hy',               //Armenian
      'aze' => 'az',               //Azerbaijani (Latin)
      'bel' => 'be',               //Belarusian
      'ben' => 'bn',               //Bengali
      'bos' => 'bs',               //Bosnian (Latin)
      'bul' => 'bg',               //Bulgarian
      'mya' => 'my',               //Burmese
      'cat' => 'ca',               //Catalan
      'zho-CN' => 'zh-CN',         //Chinese (Simplified)
      'zho-TW' => 'zh-TW',         //Chinese (Traditional)
      'hat' => 'ht',               //Creole
      'hrv' => 'hr',               //Croatian
      'cze' => 'cs',               //Czech
      'dan' => 'da',               //Danish
      'prs' => 'pr',               //Dari
      'dut' => 'nl',               //Dutch
      'dut-BE' => 'nl-be',         //Dutch (Belgium)
      'eng-AU' => 'en-au',         //English (Australia)
      'eng-CA' => 'en-ca',         //English (Canada)
      'gbr' => 'en-gb',            //English (GB)
      'eng-HK' => 'en-hk',         //English (Hong Kong)
      'eng-IN' => 'en-IN',         //English (India)
      'eng-IE' => 'en-ie',         //English (Ireland)
      'eng-NZ' => 'en-nz',         //English (New Zealand)
      'eng-SG' => 'en-sg',         //English (Singapore)
      'eng-ZA' => 'en-za',         //English (South Africa)
      'eng-US' => 'en-us',         //English (US)
      'est' => 'et',               //Estonian
      'fas' => 'fa',               //Farsi
      'fil' => 'fil',              //Filipino
      'fin' => 'fi',               //Finnish
      'vls' => 'vls',              //Flemish
      'fre-BE' => 'fr-be',         //French (Belgium)
      'fre-CA' => 'fr-ca',         //French (Canada)
      'fre-FR' => 'fr-fr',         //French (France)
      'fre-LU' => 'fr-lu',         //French (Luxembourg)
      'fre-CH' => 'fr-ch',         //French (Switzerland)
      'gae-gae' => 'gae',          //Gaelic
      'kat' => 'ka',               //Georgian
      'ger-AT' => 'de-at',         //German (Austria)
      'ger-DE' => 'de-de',         //German (Germany)
      'ger-CH' => 'de-ch',         //German (Switzerland)
      'gre' => 'el',               //Greek
      'guj' => 'gu',               //Gujarati
      'heb' => 'he',               //Hebrew
      'hin' => 'hi',               //Hindi
      'hmn' => 'hmn',              //Hmong
      'hun' => 'hu',               //Hungarian
      'ice' => 'is',               //Icelandic
      'ind' => 'id',               //Indonesian
      'ita' => 'it',               //Italian
      'ita-CH' => 'it-ch',         //Italian (Switzerland)
      'jpn' => 'ja',               //Japanese
      'khm' => 'km',               //Khmer
      'kis' => 'ki',               //Kiswahili
      'kor' => 'ko',               //Korean
      'kur' => 'ku',               //Kurdish
      'lao' => 'lo',               //Lao
      'lat' => 'la',               //Latin
      'lav' => 'lv',               //Latvian
      'lit' => 'lt',               //Lithuanian
      'mac' => 'mk',               //Macedonian
      'msa' => 'ms',               //Malay
      'mal' => 'ml',               //Malayalam
      'mar' => 'mr',               //Marathi
      'nor' => 'no',               //Norwegian
      'pbu' => 'ps',               //Pashto
      'pol' => 'pl',               //Polish
      'por-BR' => 'pt-br',         //Portuguese (Brazil)
      'por-PT' => 'pt-pt',         //Portuguese (Portugal)
      'pan' => 'pa',               //Punjabi
      'rum' => 'ro',               //Romanian
      'rus' => 'ru',               //Russian
      'smo' => 'sm',               //Samoan
      'scr' => 'sh',               //Serbian (Latin)
      'snd' => 'sn',               //Sindhi
      'sin' => 'si',               //Sinhalese
      'slo' => 'sk',               //Slovak
      'slv' => 'sl',               //Slovenian
      'som' => 'so',               //Somali
      'spa-AR' => 'es-ar',         //Spanish (Argentina)
      'spa-CL' => 'es-cl',         //Spanish (Chile)
      'spa-CO' => 'es-co',         //Spanish (Colombia)
      'spa-lat' => 'es-la',        //Spanish (LATAM)
      'spa-MX' => 'es-mx',         //Spanish (Mexico)
      'spa-ES' => 'es-es',         //Spanish (Spain)
      'spa' => 'es',               //Spanish (Universal)
      'spa-us' => 'es-us',         //Spanish (US)
      'swa' => 'sw',               //Swahili
      'swe' => 'sv',               //Swedish
      'tgl' => 'tl',               //Tagalog
      'twn' => 'tw',               //Taiwanese
      'tam' => 'ta',               //Tamil
      'tel' => 'te',               //Telugu
      'tha' => 'th',               //Thai
      'th-TH' => 'th',             //Thai
      'tur' => 'tr',               //Turkish
      'ukr' => 'uk',               //Ukrainian
      'urd' => 'ur',               //Urdu
      'vie' => 'vi',               //Vietnamese
      'wel' => 'cy',               //Welsh
      'wol' => 'wo',               //Wolof
      'yid' => 'yi',               //Yiddish
      'zul' => 'zu',               //Zulu
      /*Aliases*/
      'zh-cn' => 'zh-CN',          //Chinese (Simplified)
      'zh-tw' => 'zh-TW',          //Chinese (Traditional)
      'zh-hans' => 'zh-CN',        //Chinese (Simplified)
      'zh-hant' => 'zh-TW',        //Chinese (Traditional)
      'zh-hans-cn' => 'zh-CN',     //Chinese (Simplified)
      'nl-nl' => 'nl',             //Dutch (Netherlands)
      'da-dk' => 'da',             //Danish (Denmark)
      'en' => 'en-us',             //English
      'eng-all' => 'en-all',       //English (International)
      'en-uk' => 'en-gb',          //English (United Kingdom)
      'fr' => 'fr-fr',             //France
      'fi-fi' => 'fi',             //Finnish (Finland)
      'de' => 'de-de',             //German (Germany)
      'ger' => 'de',               //German
      'it-it' => 'it',             //Italian (Italy)
      'ja-jp' => 'ja',             //Japanese (Japan)
      'ko-kr' => 'ko',             //Korean (Korea)
      'no-no' => 'no',             //Norwegian (Norway)
      'pl-pl' => 'pl',             //Polish (Poland)
      'pt' => 'pt-pt',             //Portuguese
      'ru-ru' => 'ru',             //Russian (Russia)
      'sv-se' => 'sv',             //Swedish (Sweden)
    );
}
