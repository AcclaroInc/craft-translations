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

use acclaro\translations\Constants;
use Craft;
use Exception;
use DOMDocument;
use craft\base\Element;
use acclaro\translations\services\App;
use acclaro\translations\Translations;

class ElementToFileConverter
{
    public function toXml(Element $element, $draftId = 0, $sourceSite = null, $targetSite = null, $previewUrl = null)
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
        $elementIdMeta->setAttribute('draftId', $draftId);

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

    public function toJson(Element $element, $sourceSite = null, $targetSite = null, $wordCount = 0)
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
    public function toCsv(Element $element, $sourceSite, $targetSite, $wordCount) {
        $sourceLanguage = Craft::$app->sites->getSiteById($sourceSite)->language;
        $targetLanguage = Craft::$app->sites->getSiteById($targetSite) ?
                            Craft::$app->sites->getSiteById($targetSite)->language : 'deleted';

        $headers = '"elementId","source-site","target-site","source-language","target-language","wordCount"';   
        $content = "\"$element->id\",\"$sourceSite\",\"$targetSite\",\"$sourceLanguage\",\"$targetLanguage\",\"$wordCount\"";
  
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

            return $this->toXml($element, $draftId, $sourceSite, $targetSite, $previewUrl);
        } elseif ($format == Constants::FILE_FORMAT_CSV) {
            $sourceSite = $data['sourceSite'] ?? null;
            $targetSite = $data['targetSite'] ?? null;
            $wordCount  = $data['wordCount'] ?? 0;

            return $this->toCsv($element, $sourceSite, $targetSite, $wordCount);
        } else {
            $sourceSite = $data['sourceSite'] ?? null;
            $targetSite = $data['targetSite'] ?? null;
            $wordCount  = $data['wordCount'] ?? 0;

            return $this->toJson($element, $sourceSite, $targetSite, $wordCount);
        }
    }
}
