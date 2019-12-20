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

use acclaro\translations\Translations;
use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craft\base\Widget;
use acclaro\translations\services\App;
use acclaro\translations\elements\Order;
use acclaro\translations\records\WidgetRecord;
use craft\records\Entry;
use craft\helpers\Json;
use acclaro\translations\assetbundles\RecentEntryAssets as JS;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.2
 */
class RecentEntries extends Widget
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'New Source Entries');
    }

    /**
     * @inheritdoc
     */
    public static function iconPath()
    {
        return Craft::getAlias('@app/icons/mask.svg');
    }

    public $limit = 10;
    public $days = 7;

    /**
     * @inheritdoc
     */
    public function rules()
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
    public function getSettingsHtml()
    {

        $options = [
            ['label' => 'Last 24 hours', 'value' => 1],
            ['label' => 'Last 7 Days', 'value' => 7],
            ['label' => 'Last 30 Days', 'value' => 30],
            ['label' => 'Last 90 Days', 'value' => 90],
        ];

        return Craft::$app->getView()->renderTemplate('translations/_components/widgets/RecentEntries/settings',
            [
                'widget' => $this,
                'options' => $options,
                'settings' => $this->getSettings()
            ]);
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml()
    {

        $params = [];

        $params['limit'] = (int)$this->limit;
        $params['days'] = (int)$this->days;

        $view = Craft::$app->getView();
        $view->registerAssetBundle(JS::class);

        $view->registerJs(
            'new Craft.Translations.RecentEntries(' . $this->id . ', ' . Json::encode($params) . ');'
        );

        //$entries = $this->_getEntries();

        return $view->renderTemplate('translations/_components/widgets/RecentEntries/body', ['limit' => $this->limit]);
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
     * Returns the new entries
     *
     * @return array
     */
    private function _getEntries(): array
    {
        $elements = Entry::find()->limit($this->limit)->orderBy(['dateUpdated' => SORT_DESC])->all();

        $i = 0;
        $entries = [];
        foreach ($elements as $element) {
            $entries[$i]['entryName'] = Craft::$app->getEntries()->getEntryById($element->id)->title;
            $entries[$i]['entryId'] = $element->id;
            $entries[$i]['entryDate'] = $element->dateUpdated;
            $entries[$i]['entryDateTimestamp'] = $element->dateUpdated;
            $entries[$i]['statusLabel'] = $element->dateUpdated;
            $entries[$i]['id'] = $element->id;
            $i++;
        }
        //echo '<pre>'; print_r($entries); die;

        return $entries;
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