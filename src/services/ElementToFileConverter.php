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

        foreach (Translations::$plugin->elementTranslator->toTranslationSource($element, $sourceSite) as $key => $value) {
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
                $sourceSite
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
                $sourceSite
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
     * @return file
     */
    public function convert(Element $element, $format, $data) {
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
}
