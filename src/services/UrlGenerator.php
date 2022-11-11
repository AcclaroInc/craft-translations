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
use craft\base\Element;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\Category;
use craft\elements\GlobalSet;
use acclaro\translations\Constants;
use craft\commerce\elements\Product;

use acclaro\translations\Translations;
use acclaro\translations\elements\Order;
use acclaro\translations\models\FileModel;
use yii\web\ServerErrorHttpException;

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
        /** @var \craft\services\Sites $sitesService */
        $sitesService = Craft::$app->getSites();
        $targetSite = $sitesService->getSiteById($file->targetSite) ?? $sitesService->getSiteById($file->sourceSite) ?? $sitesService->getPrimarySite();

        $data = [
            'site' => $targetSite->handle,
        ];

        if ($element instanceof GlobalSet) {
            if ($file->draftId && $file->isComplete()) {
                $url = sprintf('translations/globals/%s/drafts/%s', $element->handle, $file->draftId);
                return Translations::$plugin->urlHelper->cpUrl($url, $data);
            }
            return preg_replace(
                '/(\/'.Craft::$app->sites->getSiteById($element->siteId)->handle.')/',
                '/'.$targetSite->handle,
                $element->getCpEditUrl($element)
            );
        }

        if ($element instanceof Asset) {
            if ($file->draftId && $file->isComplete()) {
                $url = sprintf('translations/assets/%s/drafts/%s', $element->id, $file->draftId);
                return Translations::$plugin->urlHelper->cpUrl($url, $data);
            }
            return Translations::$plugin->urlHelper->url($element->getCpEditUrl(), $data);
        }

        if ($element instanceof (Constants::CLASS_COMMERCE_PRODUCT) && $file->hasDraft() && $file->isComplete()) {
            $data['draftId'] = $file->draftId;
            $url = sprintf('commerce/product/%s/%s-%s', $element->type, $element->id ,$element->slug);

            return Translations::$plugin->urlHelper->cpUrl($url, $data);
        }

        if ($file->isPublished()) {
            $element = $element->getIsDraft() ? $element->getCanonical(true) : $element;
        } elseif ($file->isComplete() && $file->hasDraft()) {
            $data['draftId'] = $file->draftId;
        }

        return Translations::$plugin->urlHelper->url($element->getCpEditUrl(), $data);
    }

    public function generateFileWebUrl(Element $element, FileModel $file)
    {
        if ($file->isPublished()) {
            if (!$file->hasPreview()) {
                return '';
            }

            return $element->getUrl();
        }

        return $this->generateElementPreviewUrl($element, $file->targetSite);
    }

    public function generateCpUrl($path)
    {
        return Translations::$plugin->urlHelper->cpUrl($path);
    }

    public function generateElementPreviewUrl(Element $element, $siteId = null)
    {
        if ($element instanceof GlobalSet || $element instanceof Asset || $element instanceof Product) {
            return '';
        }

        $className = get_class($element);

        if ($className === Product::class) {
            $previewUrl = $element->url;
        } else if (($className === Entry::class || $className === Category::class) && !$element->getIsDraft()) {
            $previewUrl = $element->url;
        } else {
            $route = [
                'preview/preview', [
                    'elementType' => $className,
                    'canonicalId' => $element->getCanonicalId(),
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

            if ($element->url) {
                $previewUrl = Translations::$plugin->urlHelper->urlWithParams($this->getPrimaryPreviewTargetUrl($element), [
                    Craft::$app->getConfig()->getGeneral()->tokenParam => $token,
                ]);
            } else {
                $previewUrl = '';
            }
        }

        return $previewUrl;
    }

    private function getPrimaryPreviewTargetUrl($element)
    {
        try {
            $targets = $element->getPreviewTargets();

            return $targets[0]['url'];
        } catch(\Exception $e) {
            $targets = $element->getSection()->previewTargets;
            $uri = $targets[0]['urlFormat'] ?? null;

            if ($uri) {
                return $this->normalizeUri($uri, $element);
            }
        }

        return $element->url;
    }

    private function normalizeUri($newUri, $element)
    {
        switch (strpos($newUri, '}') !== false) {
            case stripos($newUri, '{url}'):
                $newUri = str_ireplace('{url}', $element->url, $newUri);
            case stripos($newUri, '{slug}'):
                $newUri = str_ireplace('{slug}', $element->slug, $newUri);
            case stripos($newUri, '{uid}'):
                $newUri = str_ireplace('{uid}', $element->uid, $newUri);
            default:
                $newUri = preg_replace('/{(.*?)}/', '', $newUri);
        }

        $baseUrl = str_replace($element->uri, '', $element->url);

        if (strpos($newUri, '?') !== false) {
            return rtrim($baseUrl, '/') . '/' . ltrim($newUri, '/');
        } else {
            return rtrim($baseUrl, '/') . '?' . ltrim($newUri, '/');
        }
    }
}
