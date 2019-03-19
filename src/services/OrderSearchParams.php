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
use acclaro\translationsforcraft\TranslationsForCraft;
use acclaro\translationsforcraft\services\App;

class OrderSearchParams
{
    public function getParams()
    {
        $sites = Craft::$app->sites->getAllSiteIds();
        $category = 'app';
        $statuses = array_map(function($status) use ($category) {
            return TranslationsForCraft::$plugin->translator->translate($category, $status);
        }, TranslationsForCraft::$plugin->orderRepository->getOrderStatuses());
        
        $query = Craft::$app->request->getParam('criteria') ? Craft::$app->request->getParam('criteria') : Craft::$app->request->getParam('');


        $sourceSite = isset($query['sourceSite']) ? $query['sourceSite'] : null;
        $targetSite = isset($query['targetSite']) ? $query['targetSite'] : null;
        $targetSite = isset($query['targetSite']) ? $query['targetSite'] : null;
        $startDate = isset($query['startDate']) ? $query['startDate'] : null;
        $endDate = isset($query['endDate']) ? $query['endDate'] : null;
        $status = isset($query['status']) ? $query['status'] : null;

        $params = array();

        if ($sourceSite && array_key_exists($sourceSite, $sites)) {
            $params['sourceSite'] = $sourceSite;
        }

        if ($targetSite && array_key_exists($targetSite, $sites)) {
            $params['targetSite'] = $targetSite;
        }

        if ($startDate && isset($startDate['date']) && preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $startDate['date'])) {
            $params['startDate'] = $startDate['date'];
        }

        if ($endDate && isset($endDate['date']) && preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $endDate['date'])) {
            $params['endDate'] = $endDate['date'];
        }

        if ($status && array_key_exists($status, $statuses)) {
            $params['status'] = $status;
        }

        return $params;
    }
}