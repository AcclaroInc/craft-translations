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
use acclaro\translations\assetbundles\LanguageCoverageAssets as JS;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.2
 */
class LanguageCoverage extends Widget
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Translation Coverage by Site');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return Craft::getAlias('@app/icons/world.svg');
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
        return Craft::$app->getView()->renderTemplate('translations/_components/widgets/LanguageCoverage/settings',
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
        $view->registerAssetBundle(JS::class);
        $view->registerJs(
            'new Craft.Translations.LanguageCoverage(' . $this->id . ', ' . Json::encode($params) . ');'
        );
        return Craft::$app->getView()->renderTemplate('translations/_components/widgets/LanguageCoverage/body', [
            'limit' => $this->limit,
            // 'colspan' => $this->limit
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