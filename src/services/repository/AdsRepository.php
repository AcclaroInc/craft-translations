<?php

/**
 * Translations for Craft plugin for Craft CMS 5.x
 *
 * Translations for Craft eliminates error-prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\repository;

use acclaro\translations\Constants;

class AdsRepository
{
	public function dashboard()
	{
		return $this->getAdsContent("dashboard");
	}

	/**
	 * Return ad content based on content weather its sidebar of order, translator etc.
	 * 
	 * @param $context can be one of "create" or "edit";
	 */
	public function sidebar($context)
	{
		return $this->getAdsContent("sidebar", $context);
	}

	public function footer()
	{
		return $this->getAdsContent("footer");
	}

	/**
	 * Returns ads to be shown based on location of ad and context if applies.
	 * 
	 * @param $location The page on which the ad is to be shown.
	 * @param $context the action taken on a page based on which ad will be shown. 
	 * 
	 * @return array
	 */
	private function getAdsContent($location, $context = null): array {
		$adsData = Constants::ADS_CONTENT[$location];
		return $context ? $adsData[$context] : $adsData;
	}
}