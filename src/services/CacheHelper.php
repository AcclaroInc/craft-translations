<?php

/**
 * Translations for Craft plugin for Craft CMS 5.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services;

use Craft;
use acclaro\translations\Constants;

class CacheHelper
{
	public function getOrSetCache(string $cacheKey, callable $fallback, int $duration = Constants::CACHE_TIMEOUT)
	{
		$cache = Craft::$app->getCache();

		return $cache->getOrSet($cacheKey, $fallback, $duration);
	}

	public function invalidateCache(array $cacheKeys)
	{
		$cache = Craft::$app->getCache();

		foreach ($cacheKeys as $cacheKey) {
			if ($cache->exists($cacheKey)) {
				$cache->delete($cacheKey);
			}
		}
	}
}