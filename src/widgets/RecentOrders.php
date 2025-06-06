<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\widgets;

use Craft;
use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\records\WidgetRecord;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.2
 */
class RecentOrders extends BaseWidget
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Recent Orders');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return Craft::getAlias('@app/icons/clock.svg');
    }

    public $limit = 10;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules[] = ['limit', 'number', 'integerOnly' => true];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function minColspan()
    {
        return 2;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('translations/_components/widgets/RecentOrders/settings',
            [
                'widget' => $this
            ]);
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        $view = Craft::$app->getView();

        $ordersCallback = fn() => Translations::$plugin->orderRepository->getOrders($this->limit);

        $orderCountAcclaroCallback = fn() => Translations::$plugin->orderRepository->getAcclaroOrdersCount();

        $orders = Translations::$plugin->cacheHelper->getOrSetCache(
            Constants::CACHE_KEY_RECENT_ORDERS_WIDGET,
            $ordersCallback,
            Constants::CACHE_TIMEOUT
        );
        $orderCountAcclaro = Translations::$plugin->cacheHelper->getOrSetCache(
            Constants::CACHE_KEY_ACCLARO_ORDERS_COUNT,
            $orderCountAcclaroCallback,
            Constants::CACHE_TIMEOUT
        );

        return $view->renderTemplate('translations/_components/widgets/RecentOrders/body', ['orders' => $orders, 'orderCountAcclaro' => $orderCountAcclaro]);
    }

    public static function doesUserHaveWidget(string $type): bool
    {
        return WidgetRecord::find()
            ->where([
                'userId' => Craft::$app->getUser()->getIdentity()->id,
                'type' => $type,
            ])
            ->exists();
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return (static::allowMultipleInstances() || !static::doesUserHaveWidget(static::class));
    }

    /**
     * Returns whether the widget can be selected more than once.
     *
     * @return bool Whether the widget can be selected more than once
     */
    protected static function allowMultipleInstances(): bool
    {
        return false;
    }
}