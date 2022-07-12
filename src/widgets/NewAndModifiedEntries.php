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
use craft\base\Widget;
use craft\helpers\Json;
use acclaro\translations\records\WidgetRecord;
use acclaro\translations\assetbundles\NewAndModifiedAssets;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.2
 */
class NewAndModifiedEntries extends Widget
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'New & Modified Source Entries');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return Craft::getAlias('@app/icons/newspaper.svg');
    }

    public $limit = 10;
    public $days = 7;

    /**
     * @inheritdoc
     */
    public function getTitle(): string
    {
        // Default to the widget's display name
        return static::displayName();
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules[] = ['limit', 'number', 'integerOnly' => true];
        $rules[] = ['days', 'number', 'integerOnly' => true];

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
        $options = [
            ['label' => 'Last 24 hours', 'value' => 1],
            ['label' => 'Last 7 Days', 'value' => 7],
            ['label' => 'Last 30 Days', 'value' => 30],
            ['label' => 'Last 90 Days', 'value' => 90],
        ];

        return Craft::$app->getView()->renderTemplate('translations/_components/widgets/NewAndModifiedEntries/settings',
            [
                'widget' => $this,
                'options' => $options,
                'settings' => $this->getSettings()
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        $params = [];

        $params['limit'] = (int)$this->limit;
        $params['days'] = (int)$this->days;

        $view = Craft::$app->getView();
        $view->registerAssetBundle(NewAndModifiedAssets::class);
        $view->registerJs(
            'new Craft.Translations.RecentlyModified(' . $this->id . ', ' . Json::encode($params) . ');'
        );
        $view->registerJs(
            'new Craft.Translations.RecentEntries(' . $this->id . ', ' . Json::encode($params) . ');'
        );
        return Craft::$app->getView()->renderTemplate('translations/_components/widgets/NewAndModifiedEntries/body', [
            'limit' => $this->limit,
        ]);
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
