<?php

/**
 * Translations for Craft plugin for Craft CMS 5.x
 *
 * Translations for Craft eliminates error-prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\controllers;

use acclaro\translations\services\repository\AdsRepository;
use craft\web\Controller;

class AdsController extends Controller
{
    protected $service;

    public function __construct($id, $module = null)
    {
        parent::__construct($id, $module);

        $this->service = new AdsRepository();
    }

	/**
	 * Gets the ad to be shown in dashboard widget
	 */
	public function actionDashboard()
	{
		$this->requireCpRequest();

		return $this->service->dashboardWidget();
	}

	/**
	 * Gets the ad to be shown in order sidebar
	 */
	public function actionSidebar()
	{
		$this->requireCpRequest();

		return $this->service->dashboardWidget();
	}
}