<?php
/**
 * Translations for Craft plugin for Craft CMS 4.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\repository;

use Craft;
use Exception;
use craft\commerce\elements\Product;
use craft\commerce\Plugin as Commerce;
use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\models\CommerceDraftModel;
use acclaro\translations\records\CommerceDraftRecord;
use craft\base\Element;
use craft\elements\User;

class CommerceRepository
{
    public function getProductById($id, $site = null)
    {
        return Commerce::getInstance()->getProducts()->getProductById((int) $id, $site);
    }

    public function getDraftById($draftId, $siteId)
    {
        return Product::find()
            ->draftId($draftId)
            ->siteId($siteId)
            ->status(null)
            ->one();
    }

    public function createDraft(Product $product, $site, $orderName)
    {
        $allSitesHandle = Translations::$plugin->siteRepository->getAllSitesHandle();
        try {
            $handle = isset($allSitesHandle[$site]) ? $allSitesHandle[$site] : "";
            $name = sprintf('%s [%s]', $orderName, $handle);
            $notes = '';
            $creator = User::find()
                ->admin()
                ->orderBy(['elements.id' => SORT_ASC])
                ->one();
            $elementURI = Craft::$app->getElements()->getElementUriForSite($product->id, $site);

            $newAttributes = [
                'siteId' => $site,
                'uri' => $elementURI,
            ];

            $draft = Craft::$app->getDrafts()->createDraft($product, $creator->id, $name, $notes, $newAttributes);

            return $draft;
        } catch (Exception $e) {
            Translations::$plugin->logHelper->log('[' . __METHOD__ . '] CreateDraft exception:: ' . $e->getMessage(), Constants::LOG_LEVEL_ERROR);
            return [];
        }
    }
}