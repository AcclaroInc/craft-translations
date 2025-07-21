<?php

/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services;

use Craft;
use DOMDocument;
use craft\base\Element;
use acclaro\translations\Constants;
use acclaro\translations\Translations;

class ElementToFileConverter
{
    public function toXml(Element $element, $draftId, $sourceSite, $targetSite, $previewUrl, $orderId, $wordCount)
    {
        $dom = new DOMDocument('1.0', 'utf-8');

        $dom->formatOutput = true;

        $xml = $dom->appendChild($dom->createElement('xml'));

        $head = $xml->appendChild($dom->createElement('head'));
        $original = $head->appendChild($dom->createElement('original'));
        $preview = $head->appendChild($dom->createElement('preview'));
        $sites = $head->appendChild($dom->createElement('sites'));
        $langs = $head->appendChild($dom->createElement('langs'));
        $sites->setAttribute('source-site', $sourceSite);
        $sites->setAttribute('target-site', $targetSite);
        $langs->setAttribute('source-language', Craft::$app->sites->getSiteById($sourceSite)->language);
        $langs->setAttribute('target-language', (Craft::$app->sites->getSiteById($targetSite)) ? Craft::$app->sites->getSiteById($targetSite)->language : 'deleted');
        $original->setAttribute('url', Translations::$plugin->urlGenerator->generateElementPreviewUrl($element));
        $preview->setAttribute('url', $previewUrl);

        $elementIdMeta = $head->appendChild($dom->createElement('meta'));
        $elementIdMeta->setAttribute('elementId', $element->id);
        $elementIdMeta->setAttribute('orderId', $orderId);
        $elementIdMeta->setAttribute('wordCount', $wordCount);

        $body = $xml->appendChild($dom->createElement('body'));

        foreach (Translations::$plugin->elementTranslator->toTranslationSource($element, $sourceSite, $orderId) as $key => $value) {
            $translation = $dom->createElement('content');

            $translation->setAttribute('resname', $key);

            // Does the value contain characters requiring a CDATA section?
            $text = 1 === preg_match('/[&<>]/', $value) ? $dom->createCDATASection($value) : $dom->createTextNode($value);

            $translation->appendChild($text);

            $body->appendChild($translation);
        }

        return $dom->saveXML();
    }

    public function toJson(Element $element, $sourceSite, $targetSite, $wordCount, $orderId)
    {
        $sourceLanguage = Craft::$app->sites->getSiteById($sourceSite)->language;
        $targetLanguage = Craft::$app->sites->getSiteById($targetSite) ?
                            Craft::$app->sites->getSiteById($targetSite)->language : 'deleted';

        $file = [
            "source-site"       => $sourceSite,
            "target-site"       => $targetSite,
            "source-language"   => $sourceLanguage,
            "target-language"   => $targetLanguage,
            "elementId"         => $element->id,
            "orderId"           => $orderId,
            "wordCount"         => $wordCount
        ];

        foreach (
            Translations::$plugin->elementTranslator->toTranslationSource(
                $element,
                $sourceSite,
                $orderId
            ) as $key => $value
        ) {
            $file['content'][$key] = $value;
        }

        return json_encode($file);
    }

    /**
     * Convert Element to CSV file
     *
     * @param \craft\base\Element $element
     * @param [string] $sourceSite
     * @param [string] $targetSite
     * @param [string] $wordCount
     * @return file
     */
    public function toCsv(Element $element, $sourceSite, $targetSite, $wordCount, $orderId) {
        $sourceLanguage = Craft::$app->sites->getSiteById($sourceSite)->language;
        $targetLanguage = Craft::$app->sites->getSiteById($targetSite) ?
                            Craft::$app->sites->getSiteById($targetSite)->language : 'deleted';

        $headers = '"orderId","elementId","source-site","target-site","source-language","target-language","wordCount"';
        $content = "\"$orderId\",\"$element->id\",\"$sourceSite\",\"$targetSite\",\"$sourceLanguage\",\"$targetLanguage\",\"$wordCount\"";

        foreach (
            Translations::$plugin->elementTranslator->toTranslationSource(
                $element,
                $sourceSite,
                $orderId
            ) as $key => $value
        ) {
            $headers .= ",\"$key\"";
            $content .= ",\"$value\"";
        }
        $file = $headers . "\n" . $content;

        return $file;
    }

    /**
     * Convert an element based on given file format among [JSON, XML, CSV]
     *
     * @param \craft\base\Element $element
     * @param [string] $format like [JSON, XML, CSV]
     * @param [array] $data
     * @return string
     */
    public function convert(Element $element, $format, $data, ?Element $sourceElement = null) {
        if ($format == Constants::FILE_FORMAT_XML) {
            $sourceSite = $data['sourceSite'] ?? null;
            $targetSite = $data['targetSite'] ?? null;
            $wordCount  = $data['wordCount'] ?? 0;
            $draftId    = $data['draftId'] ?? 0;
            $previewUrl = $data['previewUrl'] ?? null;
            $orderId = $data['orderId'] ?? 0;

            return $this->toXml($element, $draftId, $sourceSite, $targetSite, $previewUrl, $orderId, $wordCount);
        } elseif ($format == Constants::FILE_FORMAT_CSV) {
            $sourceSite = $data['sourceSite'] ?? null;
            $targetSite = $data['targetSite'] ?? null;
            $wordCount  = $data['wordCount'] ?? 0;
            $orderId    = $data['orderId'] ?? 0;

            return $this->toCsv($element, $sourceSite, $targetSite, $wordCount, $orderId);
        } elseif ($format == Constants::FILE_FORMAT_TMX) {
            $sourceSite = $data['sourceSite'] ?? null;
            $targetSite = $data['targetSite'] ?? null;
            $wordCount  = $data['wordCount'] ?? 0;
            $orderId    = $data['orderId'] ?? 0;

            return $this->toTmx($element, $sourceElement, $sourceSite, $targetSite, $orderId, $wordCount);
        } else {
            $sourceSite = $data['sourceSite'] ?? null;
            $targetSite = $data['targetSite'] ?? null;
            $wordCount  = $data['wordCount'] ?? 0;
            $orderId    = $data['orderId'] ?? 0;

            return $this->toJson($element, $sourceSite, $targetSite, $wordCount, $orderId);
        }
    }

    /**
     * Convert Json to XML
     *
     * @return Xml
     */
    public function jsonToXml($data) {
        $dom = new DOMDocument('1.0', 'utf-8');

        $dom->formatOutput = true;

        $xml = $dom->appendChild($dom->createElement('xml'));

        $head = $xml->appendChild($dom->createElement('head'));
        $original = $head->appendChild($dom->createElement('original'));
        $preview = $head->appendChild($dom->createElement('preview'));
        $sites = $head->appendChild($dom->createElement('sites'));
        $langs = $head->appendChild($dom->createElement('langs'));
        $sites->setAttribute('source-site', $data['source-site']);
        unset($data['source-site']);
        $sites->setAttribute('target-site', $data['target-site']);
        unset($data['target-site']);
        $langs->setAttribute('source-language', $data['source-language']);
        unset($data['source-language']);
        $langs->setAttribute('target-language', $data['target-language'] ?? 'deleted');
        unset($data['target-language']);

        $elementIdMeta = $head->appendChild($dom->createElement('meta'));
        $elementIdMeta->setAttribute('elementId', $data['elementId']);
        unset($data['elementId']);

        $body = $xml->appendChild($dom->createElement('body'));

        foreach ($data['content'] ?? null as $key => $value) {
            $translation = $dom->createElement('content');

            $translation->setAttribute('resname', $key);

            // Does the value contain characters requiring a CDATA section?
            $text = 1 === preg_match('/[&<>]/', $value) ? $dom->createCDATASection($value) : $dom->createTextNode($value);

            $translation->appendChild($text);

            $body->appendChild($translation);
        }

        return $dom->saveXML();
    }

    public function getElementIdFromData($content, $extension) {
        try {
            if ($extension == Constants::FILE_FORMAT_XML) {
                $xml_content = $content;
            } else if ($extension == Constants::FILE_FORMAT_CSV) {
                $xml_content = $this->jsonToXml($this->csvToJson($content));
            } else {
                $xml_content = $this->jsonToXml(json_decode($content, true));
            }

            $dom = new \DOMDocument('1.0', 'utf-8');

            //Turn LibXml Internal Errors Reporting On!
            libxml_use_internal_errors(true);
            if (!$dom->loadXML( $xml_content ))
            {
                $errors = $this->reportXmlErrors();
                if($errors)
                {
                    Translations::$plugin->logHelper->log(Translations::$plugin->translator->translate('app', "We found errors parsing file's xml."  . $errors),Constants::LOG_LEVEL_ERROR);
                    return false;
                }
            }

            // Meta ElementId
            $element = $dom->getElementsByTagName('meta');
            $element = isset($element[0]) ? $element[0] : $element;

            return (string)$element->getAttribute('elementId');
        } catch(\Exception $e) {
            Translations::$plugin->logHelper->log(Translations::$plugin->translator->translate('app', $e->getMessage()), Constants::LOG_LEVEL_ERROR);
        }

    }

    /**
     * Report and Validate XML imported files
     * @return string
     */
    private function reportXmlErrors() {
        $errors = array();
        $libErros = libxml_get_errors();

        $msg = false;
        if ($libErros && isset($libErros[0]))
        {
            $msg = $libErros[0]->code . ": " .$libErros[0]->message;
        }
        return $msg;
    }

    public function csvToJson($file_content)
    {
        $jsonData = [];
        $contentArray = explode("\n", $file_content, 2);

        if (count($contentArray) != 2) {
            Translations::$plugin->logHelper->log(
                Translations::$plugin->translator->translate(
                    'app', "file you are trying to import has invalid content."),
                    Constants::LOG_LEVEL_ERROR

            );
            return false;
        }

        $keys = explode('","', $contentArray[0]);
        $values = explode('","', $contentArray[1]);

        if (count($keys) != count($values)) {
            Translations::$plugin->logHelper->log(
                Translations::$plugin->translator->translate(
                    'app', "csv file you are trying to import has header and value mismatch."
                ),Constants::LOG_LEVEL_ERROR
            );
            return false;
        }

        $metaKeys = [
            'source-site',
            'target-site',
            'source-language',
            'target-language',
            'elementId',
            'wordCount',
            'orderId',
        ];

        foreach ($keys as $i => $key) {
            $key = trim($key, '"');
            $value = trim($values[$i], '"');

            if (in_array($key, $metaKeys)) {
                $jsonData[$key] = $value;
                continue;
            }
            $jsonData['content'][$key] = $value;
        }
        return $jsonData;
    }

    public function addDataToSourceXML($xml_content, array $data) {
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;

        //Turn LibXml Internal Errors Reporting On!
        libxml_use_internal_errors(true);
        if (!$dom->loadXML( $xml_content ))
        {
            $errors = $this->reportXmlErrors();
            if($errors)
            {
                Translations::$plugin->logHelper->log(Translations::$plugin->translator->translate('app', "We found errors parsing file's xml."  . $errors), Constants::LOG_LEVEL_ERROR);
                return false;
            }
        }

        $xml = $dom->getElementsByTagName('xml')->item(0);
        $head = $xml->getElementsByTagName('head')->item(0);
        $meta = $head->getElementsByTagName('meta')->item(0);
        foreach ($data as $key => $value) {
            if (! $meta->hasAttribute($key)) {
                $meta->setAttribute($key, $value);
            }
        }

        return $dom->saveXML();
    }

    public function xmlToJson($sourceContent) {
        return json_encode($this->csvToJson($this->xmlToCsv($sourceContent)));
    }

    public function jsonToCsv($sourceContent) {
        return $this->xmlToCsv($this->jsonToXml($sourceContent));
    }

    public function xmlToCsv($sourceContent) {
        $objDOM = new \DOMDocument('1.0', 'utf-8');

        $objDOM->loadXML($sourceContent);

        $head = $objDOM->getElementsByTagName("head")[0];
        $body = $objDOM->getElementsByTagName("body");

        $sites  = $head->getElementsByTagName("sites")[0];
        $langs  = $head->getElementsByTagName("langs")[0];
        $meta   = $head->getElementsByTagName("meta")[0];

        $orderId    = $meta->getAttribute('orderId') ?? '';
        $elementId  = $meta->getAttribute('elementId');
        $wordCount  = $meta->getAttribute('wordCount') ?? '';

        $sourceSite      = $sites->getAttribute('source-site');
        $targetSite      = $sites->getAttribute('target-site');
        $sourceLanguage  = $langs->getAttribute('source-language');
        $targetLanguage  = $langs->getAttribute('target-language');

        $headers = '"orderId","elementId","source-site","target-site","source-language","target-language","wordCount"';
        $content = "\"$orderId\",\"$elementId\",\"$sourceSite\",\"$targetSite\",\"$sourceLanguage\",\"$targetLanguage\",\"$wordCount\"";

        //looping in body for content
        foreach ($body as $node) {
            foreach ($node->getElementsByTagName('content') as $child) {
                $key    = $child->getAttribute('resname');
                $value  = $child->childNodes[0] ? $child->childNodes[0]->nodeValue : '';

                $headers .= ",\"$key\"";
                $content .= ",\"$value\"";
            }
        }

        return $headers."\n".$content;
    }

    /**
     * Convert XML to TMX format
     *
     * @param string $sourceContent
     * @return string
     */
    public function xmlToTmx($sourceContent) {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->loadXML($sourceContent);

        $head = $dom->getElementsByTagName("head")[0];
        $body = $dom->getElementsByTagName("body")[0];

        $sites = $head->getElementsByTagName("sites")[0];
        $langs = $head->getElementsByTagName("langs")[0];
        $meta = $head->getElementsByTagName("meta")[0];

        $orderId = $meta->getAttribute('orderId') ?? '';
        $elementId = $meta->getAttribute('elementId');
        $wordCount = $meta->getAttribute('wordCount') ?? '';

        $sourceSite = $sites->getAttribute('source-site');
        $targetSite = $sites->getAttribute('target-site');
        $sourceLanguage = $langs->getAttribute('source-language');
        $targetLanguage = $langs->getAttribute('target-language');

        $tmxDom = new DOMDocument('1.0', 'utf-8');
        $tmxDom->formatOutput = true;

        // Create TMX root element
        $tmx = $tmxDom->appendChild($tmxDom->createElement('tmx'));
        $tmx->setAttribute('version', '1.4');

        // Create header
        $header = $tmx->appendChild($tmxDom->createElement('header'));
        $header->setAttribute('creationtool', 'Translations');
        $header->setAttribute('creationtoolversion', '1.0');
        $header->setAttribute('datatype', 'plaintext');
        $header->setAttribute('segtype', 'sentence');
        $header->setAttribute('adminlang', $sourceLanguage);
        $header->setAttribute('srclang', $sourceLanguage);
        $header->setAttribute('o-tmf', 'craft-cms');

        // Add custom properties for Craft CMS metadata
        $prop = $header->appendChild($tmxDom->createElement('prop'));
        $prop->setAttribute('type', 'x-filename');
        $prop->appendChild($tmxDom->createTextNode("element-{$elementId}.tmx"));

        $prop = $header->appendChild($tmxDom->createElement('prop'));
        $prop->setAttribute('type', 'x-elementId');
        $prop->appendChild($tmxDom->createTextNode($elementId));

        $prop = $header->appendChild($tmxDom->createElement('prop'));
        $prop->setAttribute('type', 'x-orderId');
        $prop->appendChild($tmxDom->createTextNode($orderId));

        $prop = $header->appendChild($tmxDom->createElement('prop'));
        $prop->setAttribute('type', 'x-wordCount');
        $prop->appendChild($tmxDom->createTextNode($wordCount));

        $prop = $header->appendChild($tmxDom->createElement('prop'));
        $prop->setAttribute('type', 'x-source-site');
        $prop->appendChild($tmxDom->createTextNode($sourceSite));

        $prop = $header->appendChild($tmxDom->createElement('prop'));
        $prop->setAttribute('type', 'x-target-site');
        $prop->appendChild($tmxDom->createTextNode($targetSite));

        // Create body
        $tmxBody = $tmx->appendChild($tmxDom->createElement('body'));
        $sourceElement = Translations::$plugin->elementRepository->getElementById($elementId, $sourceSite);

        $sourcePairs = Translations::$plugin->elementTranslator->toTranslationSource($sourceElement, $sourceSite, $orderId);

        foreach ($body->getElementsByTagName('content') as $contentElement) {
            $key = $contentElement->getAttribute('resname');
            $targetText = $contentElement->childNodes[0] ? $contentElement->childNodes[0]->nodeValue : '';
            $sourceText = $sourcePairs[$key] ?? '';

            $tu = $tmxBody->appendChild($tmxDom->createElement('tu'));
            $tu->setAttribute('tuid', $key);

            // Source TUV
            $tuvSource = $tu->appendChild($tmxDom->createElement('tuv'));
            $tuvSource->setAttribute('lang', $sourceLanguage);
            $segSource = $tuvSource->appendChild($tmxDom->createElement('seg'));
            $textSource = preg_match('/[&<>]/', $sourceText)
                ? $tmxDom->createCDATASection($sourceText)
                : $tmxDom->createTextNode($sourceText);
            $segSource->appendChild($textSource);

            // Target TUV
            $tuvTarget = $tu->appendChild($tmxDom->createElement('tuv'));
            $tuvTarget->setAttribute('lang', $targetLanguage);
            $segTarget = $tuvTarget->appendChild($tmxDom->createElement('seg'));
            $textTarget = preg_match('/[&<>]/', $targetText)
                ? $tmxDom->createCDATASection($targetText)
                : $tmxDom->createTextNode($targetText);
            $segTarget->appendChild($textTarget);
        }

        return $tmxDom->saveXML();
    }

    /**
     * Convert Element to TMX file
     *
     * @param \craft\base\Element $element
     * @param [string] $sourceSite
     * @param [string] $targetSite
     * @param [string] $orderId
     * @param [string] $wordCount
     * @param \craft\base\Element|null $sourceElement
     * @return string
     */
    public function toTmx(Element $element, Element $sourceElement, $sourceSite, $targetSite, $orderId, $wordCount)
    {
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;

        $tmx = $dom->createElement('tmx');
        $tmx->setAttribute('version', '1.4');

        $sourceLang = Craft::$app->sites->getSiteById($sourceSite)->language;
        $targetLang = Craft::$app->sites->getSiteById($targetSite)->language ?? 'deleted';

        $header = $dom->createElement('header');
        $header->setAttribute('creationtool', 'Translations');
        $header->setAttribute('creationtoolversion', '1.0');
        $header->setAttribute('datatype', 'plaintext');
        $header->setAttribute('segtype', 'sentence');
        $header->setAttribute('adminlang', $sourceLang);
        $header->setAttribute('srclang', $sourceLang);
        $header->setAttribute('o-tmf', 'craft-cms');

        $props = [
            'x-filename'     => "element-{$element->id}.tmx",
            'x-elementId'    => $element->id,
            'x-orderId'      => $orderId,
            'x-wordCount'    => $wordCount,
            'x-source-site'  => $sourceSite,
            'x-target-site'  => $targetSite,
        ];

        foreach ($props as $key => $val) {
            $prop = $dom->createElement('prop', htmlspecialchars((string)$val));
            $prop->setAttribute('type', $key);
            $header->appendChild($prop);
        }

        $tmx->appendChild($header);

        // Body
        $body = $dom->createElement('body');

        $sourcePairs = Translations::$plugin->elementTranslator->toTranslationSource($sourceElement, $sourceSite, $orderId);
        $targetPairs = Translations::$plugin->elementTranslator->toTranslationSource($element, $targetSite, $orderId);

        foreach ($sourcePairs as $key => $sourceText) {
            $targetText = $targetPairs[$key] ?? '';

            $tu = $dom->createElement('tu');
            $tu->setAttribute('tuid', $key);

            // Source TUV
            $tuvSource = $dom->createElement('tuv');
            $tuvSource->setAttribute('lang', $sourceLang);
            $segSource = $dom->createElement('seg');
            $segSource->appendChild(preg_match('/[&<>]/', $sourceText) ? $dom->createCDATASection($sourceText) : $dom->createTextNode($sourceText));
            $tuvSource->appendChild($segSource);
            $tu->appendChild($tuvSource);

            // Target TUV
            $tuvTarget = $dom->createElement('tuv');
            $tuvTarget->setAttribute('lang', $targetLang);
            $segTarget = $dom->createElement('seg');
            $segTarget->appendChild(preg_match('/[&<>]/', $targetText) ? $dom->createCDATASection($targetText) : $dom->createTextNode($targetText));
            $tuvTarget->appendChild($segTarget);
            $tu->appendChild($tuvTarget);

            $body->appendChild($tu);
        }

        $tmx->appendChild($body);
        $dom->appendChild($tmx);

        return $dom->saveXML();
    }


    /**
     * Convert content to the specified format
     *
     * @param string $content in xml format
     * @param string $format in which to convert in
     * @return string
     */
    public function convertTo($content, $format) {
        switch ($format) {
            case Constants::FILE_FORMAT_CSV:
                return $this->xmlToCsv($content);
            case Constants::FILE_FORMAT_JSON:
                return $this->xmlToJson($content);
            case Constants::FILE_FORMAT_TMX:
                return $this->xmlToTmx($content);
            default:
                return $content;
        }
    }

    public function isValidXml($content) {
        $dom = new \DOMDocument('1.0', 'utf-8');

        //Turn LibXml Internal Errors Reporting On!
        libxml_use_internal_errors(true);
        $isValid = $dom->loadXML( $content );
        libxml_clear_errors();

        return $isValid;
    }
}
