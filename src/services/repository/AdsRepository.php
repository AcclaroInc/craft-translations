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

class AdsRepository
{
	public function dashboardWidget()
	{
		return [
			"heading" => "Dashboard ad heading",
			"content" => "Ad content for dashboard",
		];
	}

	/**
	 * Return ad content based on content weather its sidebar fo order, translator etc.
	 */
	public function OrderSidebar($context = "order")
	{
		return [
			"heading" => "Sidebar ad heading",
			"content" => "Ad content for order sidebar",
		];
	}
}