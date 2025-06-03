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
use craft\helpers\Json;
use acclaro\translations\records\WidgetRecord;
use acclaro\translations\assetbundles\AcclaroNewsAssets as JS;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.2
 */
class News extends BaseWidget
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Acclaro News');
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
    public function minColspan()
    {
        return 1;
    }

    /**
     * @inheritdoc
     */
    public function getSubtitle(): ?string
    {
        return "<div style=\"text-align:right;\">
            <a href=\"https://www.acclaro.com/translation-blog\">Visit Blog &raquo;</a>
            </div>";
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('translations/_components/widgets/News/settings',
            [
                'widget' => $this
            ]);
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        $params = [];

        $params['limit'] = (int)$this->limit;

        $view = Craft::$app->getView();

        // $articles = $this->_getArticles();
        $view->registerAssetBundle(JS::class);
        $view->registerJs(
            'new Craft.Translations.AcclaroNews(' . $this->id . ', ' . Json::encode($params) . ');'
        );

        return $view->renderTemplate('translations/_components/widgets/News/body');
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
