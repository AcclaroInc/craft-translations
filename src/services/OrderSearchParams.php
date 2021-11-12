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
use acclaro\translations\Translations;

class OrderSearchParams
{
    public function getParams()
    {
        $sites = Craft::$app->sites->getAllSiteIds();
        $category = 'app';
        $statuses = array_map(function($status) use ($category) {
            return Translations::$plugin->translator->translate($category, $status);
        }, Translations::$plugin->orderRepository->getOrderStatuses());
        
        $query = parse_str(Craft::$app->request->getQueryStringWithoutPath(), $params);
        
        $results = [];

        foreach ($params as $key => $value) {
            switch ($key) {
                case 'status':
                    if (is_array($params[$key])) {
                        foreach ($params[$key] as $status) {
                            $results[$key][] = $status;
                        }
                    } else {
                        $results[$key] = $value;
                    }
                    break;
                case 'elementIds':
                    foreach ($params[$key] as $id) {
                        $results[$key][] = $id;
                    }
                    break;
                
                default:
                    # code...
                    break;
            }
        }

        return json_encode($results);
    }
}