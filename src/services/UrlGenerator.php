<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\services;

use Craft;
use craft\base\Element;
use craft\models\EntryDraft;
use craft\elements\GlobalSet;
use craft\elements\Entry;
use acclaro\translationsforcraft\TranslationsForCraft;
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\elements\Order;
use acclaro\translationsforcraft\models\FileModel;
use acclaro\translationsforcraft\models\GlobalSetDraftModel;

use DOMDocument;
use DateTime;


class UrlGenerator
{
    public function generateFileCallbackUrl(FileModel $file)
    {
        $key = sha1_file(Craft::$app->path->getConfigPath().'/license.key');

        $cpTrigger = '/'.Craft::$app->getConfig()->getGeneral()->cpTrigger;
        
        $url = TranslationsForCraft::$plugin->urlHelper->actionUrl('translations-for-craft/base/file-callback', array(
            'key' => $key,
            'fileId' => $file->id,
        ));

        return preg_replace(preg_quote($cpTrigger).'/', '', $url, 1);
    }

    public function generateOrderCallbackUrl(Order $order)
    {
        $key = sha1_file(Craft::$app->path->getConfigPath().'/license.key');

        $cpTrigger = '/'.Craft::$app->getConfig()->getGeneral()->cpTrigger;

        $url = TranslationsForCraft::$plugin->urlHelper->actionUrl('translations-for-craft/base/order-callback', array(
            'key' => $key,
            'orderId' => $order->id,
        ));

        return preg_replace(preg_quote($cpTrigger).'/', '', $url, 1);
    }

    public function generateFileUrl(Element $element, FileModel $file)
    {
        if ($file->status === 'published') {
            if ($element instanceof GlobalSet) {
                return preg_replace(
                    '/(\/'.Craft::$app->sites->getSiteById($element->siteId)->handle.')/',
                    '/'.Craft::$app->sites->getSiteById($file->targetSite)->handle,
                    $element->getCpEditUrl($element)
                );
            }
    
            return TranslationsForCraft::$plugin->urlHelper->cpUrl('entries/'.$element->section->handle.'/'.$element->id.'/'.Craft::$app->sites->getSiteById($file->targetSite)->handle);
        }

        if ($element instanceof GlobalSet) {
            return TranslationsForCraft::$plugin->urlHelper->cpUrl('translations-for-craft/globals/'.$element->handle.'/drafts/'.$file->draftId);
        }

        return TranslationsForCraft::$plugin->urlHelper->cpUrl('entries/'.$element->section->handle.'/'.$element->id.'/drafts/'.$file->draftId);
    }

    public function generateFileWebUrl(Element $element, FileModel $file)
    {
        if ($file->status === 'published') {
            if ($element instanceof GlobalSet) {
                return '';
            }

            return $element->url;
        }

        return $this->generateElementPreviewUrl($element, $file);
    }

    public function generateCpUrl($path)
    {
        return TranslationsForCraft::$plugin->urlHelper->cpUrl($path);
    }
    
    public function generateElementPreviewUrl(Element $element, $targetSite)
    {
        $params = array();
        
        if ($element instanceof GlobalSet) {
            return '';
        }
        
        $className = get_class($element);
        // If we're looking at the live version of an entry, just use
        // the entry's main URL as its share URL
        if ($className === Entry::class && $element->getStatus() === Entry::STATUS_LIVE) {
            $variables['shareUrl'] = $element->getUrl();
        } else {
            switch ($className) {
                case EntryDraft::class:
                    /** @var EntryDraft $element */
                    $params = ['draftId' => $element->draftId];
                    break;
                case EntryVersion::class:
                    /** @var EntryVersion $element */
                    $params = ['versionId' => $element->versionId];
                    break;
                default:
                    $params = [
                        'entryId' => $element->id,
                        'siteId' => $element->siteId
                    ];
                    break;
            }
        }
        
        $previewUrl = TranslationsForCraft::$plugin->urlHelper->actionUrl('entries/share-entry', $params);

        return $previewUrl;
    }
}