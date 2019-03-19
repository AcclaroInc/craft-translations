<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\widgets;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craft\base\Widget;
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\elements\Order;
use acclaro\translationsforcraft\records\WidgetRecord;

/**
 * @author    Acclaro
 * @package   TranslationsForCraft
 * @since     1.0.2
 */
class RecentOrders extends Widget
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
    public static function iconPath()
    {
        return Craft::getAlias('@app/icons/clock.svg');
    }

    public $limit = 10;

    /**
     * @inheritdoc
     */
    public function rules()
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
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('translations-for-craft/_components/widgets/RecentOrders/settings',
            [
                'widget' => $this
            ]);
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml()
    {
        $view = Craft::$app->getView();
        
        $orders = $this->_getOrders();
        
        return $view->renderTemplate('translations-for-craft/_components/widgets/RecentOrders/body', ['orders' => $orders]);
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
     * Returns the recent orders
     *
     * @return array
     */
    private function _getOrders(): array
    {
        $query = Order::find()->limit($this->limit);
        
        return $query->all();
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