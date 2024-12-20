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

use Craft;
use acclaro\translations\Constants;
use acclaro\translations\records\WidgetRecord;
use acclaro\translations\Translations;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.2
 */
class Ads extends BaseWidget
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Acclaro Features');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return Craft::getAlias('@app/icons/feed.svg');
    }

    public $limit = 5;

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
    public static function maxColspan(): ?int
    {
        return 1;
    }

    /**
     * @inheritdoc
     */
    public static function isDeletable(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function isLive(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function minColspan()
    {
        return 1;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate(
            'translations/_components/widgets/AcclaroAds/settings',
            [ 'widget' => $this ]
        );
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        $view = Craft::$app->getView();

        $ads = $this->_getAds();

        return $view->renderTemplate(
            'translations/_components/widgets/AcclaroAds/body',
            ['ads' => $ads]
        );
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
        // Create widget if user visits dashboard for first time.
        if (!static::doesUserHaveWidget(static::class)) {
            $widgetRepository = Translations::$plugin->widgetRepository;
            $adsWidget = $widgetRepository->createWidget(static::class);

            $widgetRepository->saveWidget($adsWidget);
        }
        return (static::allowMultipleInstances() || !static::doesUserHaveWidget(static::class));
    }

    /**
     * Returns the recent ads to be show in dahboard widget from contants file
     *
     * @return array
     */
    private function _getAds(): array
    {
        return Constants::ADS_CONTENT["dashboard"];
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
