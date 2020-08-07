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
use Exception;
use DOMDocument;
use craft\base\Element;
use acclaro\translations\services\App;
use acclaro\translations\Translations;

class ElementToXmlConverter
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
        $langs->setAttribute('target-language', Craft::$app->sites->getSiteById($targetSite)->language);
        $original->setAttribute('url', Translations::$plugin->urlGenerator->generateElementPreviewUrl($element, $targetSite));
        $preview->setAttribute('url', $previewUrl);

        $elementIdMeta = $head->appendChild($dom->createElement('meta'));
        $elementIdMeta->setAttribute('name', 'elementId');
        $elementIdMeta->setAttribute('content', $element->id);

        $draftIdMeta = $head->appendChild($dom->createElement('meta'));
        $draftIdMeta->setAttribute('name', 'draftId');
        $draftIdMeta->setAttribute('content', $draftId);

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
}
