<?php

namespace acclaro\translations\services\repository;

use Craft;
use acclaro\translations\Translations;

use ReflectionClass;

class SiteRepository
{   
    protected $supportedSites = array();

    protected $aliases = array(
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

    public function __construct()
    {
        foreach (Craft::$app->sites->getAllSiteIds() as $key => $site) {
            $this->supportedSites[] = $site;
        }
    }

    public function getSiteLanguage($siteId)
    {
        $site = Craft::$app->sites->getSiteById($siteId);

        if (!in_array($siteId, $this->supportedSites)) {
            return;
        }

        $language = $site->language;
        
        $language = $this->normalizeLanguage($site);
        
        return $language;
    }

    public function isSiteSupported($id)
    {
        return in_array($id, $this->supportedSites);
    }

    public function getSiteLanguageDisplayName($siteId)
    {
        $language = $this->getSiteLanguage($siteId);

        if ($language) {
            $displayName = Craft::$app->i18n->getLocaleById($language)->getDisplayName();
        } else {
            $displayName = '<s class="light">Deleted</s>';
        }

        return $displayName;
    }

    public function normalizeLanguage($language)
    {
        $language = mb_strtolower($language);

        $language = str_replace('_', '-', $language);

        if (isset($this->aliases[$language])) {
            $language = $this->aliases[$language];
        }

        return $language;
    }

    public function getLanguages($namePrefix = '', $excludeSite = null)
    {
        $languages = array();

        foreach ($this->supportedSites as $site) {
            if ($excludeSite === $site) {
                continue;
            }

            $languages[$site] = $namePrefix.$this->getSiteLanguageDisplayName($site);
        }

        asort($languages);

        return $languages;
    }
}
