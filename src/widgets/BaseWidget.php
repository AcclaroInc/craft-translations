<?php
/**
 * Translations for Craft plugin for Craft CMS 5.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\widgets;

use craft\base\Widget;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     4.0.0
 */
class BaseWidget extends Widget
{
	/**
	 * Will be used to set if a widget is not allowed to delete
	 */
	public static function isDeletable(): bool
	{
		return true;
	}
	/**
	 * Will be used to prevent showing any widget which is not needed
	 */
	public static function isLive(): bool
	{
		return true;
	}
}