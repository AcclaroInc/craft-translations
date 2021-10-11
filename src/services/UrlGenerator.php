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
use craft\base\Element;
use craft\elements\Category;
use craft\models\EntryDraft;
use craft\elements\GlobalSet;
use craft\elements\Entry;
use acclaro\translations\Translations;
use acclaro\translations\services\App;
use acclaro\translations\elements\Order;
use acclaro\translations\models\FileModel;
use acclaro\translations\models\GlobalSetDraftModel;
use craft\elements\Asset;
use DOMDocument;
use DateTime;
use yii\log\Target;

class UrlGenerator
{
    public function generateFileCallbackUrl(FileModel $file)
    {
        $key = sha1_file(Craft::$app->path->getConfigPath().'/license.key');

        $cpTrigger = '/'.Craft::$app->getConfig()->getGeneral()->cpTrigger;
        
        $url = Translations::$plugin->urlHelper->actionUrl('translations/base/file-callback', array(
            'key' => $key,
            'fileId' => $file->id,
        ));

        return preg_replace(preg_quote($cpTrigger).'/', '', $url, 1);
    }

    public function generateOrderCallbackUrl(Order $order)
    {
        $key = sha1_file(Craft::$app->path->getConfigPath().'/license.key');

        $cpTrigger = '/'.Craft::$app->getConfig()->getGeneral()->cpTrigger;

        $url = Translations::$plugin->urlHelper->actionUrl('translations/base/order-callback', array(
            'key' => $key,
            'orderId' => $order->id,
        ));

        return preg_replace(preg_quote($cpTrigger).'/', '', $url, 1);
    }

    public function generateFileUrl(Element $element, FileModel $file)
    {
        if ($element instanceof GlobalSet) {
            if ($file->draftId) {
                return Translations::$plugin->urlHelper->cpUrl('translations/globals/'.$element->handle.'/drafts/'.$file->draftId);
            }
            return preg_replace(
                '/(\/'.Craft::$app->sites->getSiteById($element->siteId)->handle.')/',
                '/'.Craft::$app->sites->getSiteById($file->targetSite)->handle,
                $element->getCpEditUrl($element)
            );
        }

        if ($element instanceof Category) {
            $catUri = $element->id.'-'.$element->slug;

            if ($file->draftId) {
                return Translations::$plugin->urlHelper->cpUrl("translations/categories/".$element->getGroup()->handle."/".$catUri."/drafts/".$file->draftId);
            }
            return Translations::$plugin->urlHelper->url(
                $element->getCpEditUrl($element),
                ['site' => Craft::$app->sites->getSiteById($file->targetSite)->handle]
            );
        }

        if ($element instanceof Asset) {
            if ($file->draftId) {
                return Translations::$plugin->urlHelper->cpUrl('translations/assets/'.$element->id.'/drafts/'.$file->draftId);
            }
            return Translations::$plugin->urlHelper->url($element->getCpEditUrl(),
                ['site' => Craft::$app->sites->getSiteById($file->targetSite)->handle]
            );
        }

        $data = [
            'site' => Craft::$app->sites->getSiteById($file->targetSite)->handle,
        ];

        if ($file->draftId) {
            $data['draftId'] = $file->draftId;
        }
        if ($file->status == Constants::FILE_STATUS_PUBLISHED) {
            $element = $element->getIsDraft() ? $element->getCanonical(true) : $element;
        }
        return Translations::$plugin->urlHelper->url($element->getCpEditUrl(), $data);
    }

    public function generateFileWebUrl(Element $element, FileModel $file)
    {
        if ($file->status === 'published') {
            if ($element instanceof GlobalSet OR $element instanceof Category) {
                return '';
            }

            return $element->url;
        }

        return $this->generateElementPreviewUrl($element, $file->targetSite);
    }

    public function generateCpUrl($path)
    {
        return Translations::$plugin->urlHelper->cpUrl($path);
    }

    public function generateElementPreviewUrl(Element $element, $siteId = null)
    {
        $params = array();

        if ($element instanceof GlobalSet || $element instanceof Category ) {
            return '';
        }

        $className = get_class($element);

        if ($className === Entry::class && !$element->getIsDraft()) {
            $previewUrl = $this->getPrimaryPreviewTargetUrl($element);
        } else {
            $route = [
                'preview/preview', [
                    'elementType' => $className,
                    'sourceId' => $element->getCanonicalId(),
                    'siteId' => $siteId ? $siteId : $element->siteId,
                    'draftId' => $element->draftId,
                    'revisionId' => $element->revisionId
                ]
            ];

            $expiryDate = (new \DateTime())->add(new \DateInterval('P3M'));
            $token = Craft::$app->getTokens()->createToken($route, null, $expiryDate);

            if (!$token) {
                throw new ServerErrorHttpException(Craft::t('app', 'Could not create a preview token.'));
            }

            if ($element->getUrl()) {
                $previewUrl = Translations::$plugin->urlHelper->urlWithToken($this->getPrimaryPreviewTargetUrl($element), $token);
            } else {
                $previewUrl = '';
            }
        }

        return $previewUrl;
    }

    private function getPrimaryPreviewTargetUrl($element)
    {
        $targets = $element->getPreviewTargets();
        $url = $element->url;

        foreach ($targets as $target) {
            if ($target['label'] == "Primary entry page") {
                $url = str_replace('@baseUrl/@baseUrl/', '@baseUrl/', $target['url']);
                return $url;
            }
        }

        return $url;
    }
}
