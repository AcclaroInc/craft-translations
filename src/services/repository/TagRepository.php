<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\repository;


use Craft;
use craft\elements\Tag;
use acclaro\translations\elements\Order;
use acclaro\translations\Translations;
use acclaro\translations\Constants;

class TagRepository
{
    public function find($attributes = null)
    {
        return Tag::find()
                ->id($attributes['id'])
                ->groupId($attributes['groupId'])
                ->siteId($attributes['siteId'])
                ->one();
    }

	/**
	 * Returns a tag by its ID.
	 *
	 * @param int $tagId
	 * @param int|null $siteId
	 * @return Tag|null
	 */
	public function getTagById(int $tagId, int $siteId = null)
	{
		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return Craft::$app->getElements()->getElementById($tagId, Tag::class, $siteId);
	}

    public function saveTag(Tag $tag)
    {
        $success = Craft::$app->elements->saveElement($tag, true, false);
        if (!$success) {
            Translations::$plugin->logHelper->log( '['. __METHOD__ .'] Couldn’t save the tag "'.$tag->title.'"', Constants::LOG_LEVEL_ERROR );
        }
    }

	/**
	 * Get Tags Object for an order
	 *
	 * @param Order $order
	 * @return array
	 */
	public function getOrderTags(Order $order)
	{
		$orderTags = [];
		if ($order->tags) {
			foreach (json_decode($order->tags, true) as $tagId) {
				$orderTags[$tagId] = Craft::$app->getTags()->getTagById($tagId);
			}
		}

		return $orderTags;
	}
}
