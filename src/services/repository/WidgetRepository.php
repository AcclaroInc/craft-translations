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

use acclaro\translations\Constants;
use Craft;
use craft\db\Query;
use craft\services\Users;
use craft\helpers\Component;
use craft\helpers\FileHelper;
use craft\events\WidgetEvent;
use craft\base\WidgetInterface;
use craft\widgets\MissingWidget;
use craft\errors\WidgetNotFoundException;
use craft\errors\MissingComponentException;
use acclaro\translations\records\WidgetRecord;
use acclaro\translations\Translations;
// Widget Classes
use acclaro\translations\widgets\News;
use acclaro\translations\widgets\Translators;
use acclaro\translations\widgets\RecentOrders;
use acclaro\translations\widgets\LanguageCoverage;
use acclaro\translations\widgets\NewAndModifiedEntries;
use yii\base\Component as BaseComponent;

class WidgetRepository extends BaseComponent
{
    /**
     * @event WidgetEvent The event that is triggered before a widget is saved.
     */
    const EVENT_BEFORE_SAVE_TRANSLATIONS_WIDGET = 'beforeSaveTranslationsWidget';
    /**
     * @event WidgetEvent The event that is triggered after a widget is saved.
     */
    const EVENT_AFTER_SAVE_TRANSLATIONS_WIDGET = 'afterSaveTranslationsWidget';
    /**
     * @event WidgetEvent The event that is triggered before a widget is deleted.
     */
    const EVENT_BEFORE_DELETE_TRANSLATIONS_WIDGET = 'beforeDeleteTranslationsWidget';
    /**
     * @event WidgetEvent The event that is triggered after a widget is deleted.
     */
    const EVENT_AFTER_DELETE_TRANSLATIONS_WIDGET = 'afterDeleteTranslationsWidget';

    /**
     * Returns all available widget type classes.
     *
     * @return string[]
     */
    public function getAllWidgetTypes(): array
    {
        $widgetTypes = [
            News::class,
            Translators::class,
            RecentOrders::class,
            NewAndModifiedEntries::class,
            LanguageCoverage::class,
        ];

        return $widgetTypes;
    }

    /**
     * Creates a widget with a given config.
     *
     * @param mixed $config The widget’s class name, or its config, with a `type` value and optionally a `settings` value.
     * @return WidgetInterface
     */
    public function createWidget($config): WidgetInterface
    {
        if (is_string($config)) {
            $config = ['type' => $config];
        }

        try {
            /** @var Widget $widget */
            $widget = Component::createComponent($config, WidgetInterface::class);
        } catch (MissingComponentException $e) {
            $config['errorMessage'] = $e->getMessage();
            $config['expectedType'] = $config['type'];
            unset($config['type']);
            $widget = new MissingWidget($config);
        }
        return $widget;
    }

    /**
     * Returns the dashboard widgets for the current user.
     *
     * @return WidgetInterface[] The widgets
     */
    public function getAllWidgets(): array
    {
        $widgets = $this->_getUserWidgets();
        // If there are no widgets, this is the first time they've hit the dashboard.
        if (!$widgets) {
            // Add the defaults and try again
            $this->_addDefaultUserWidgets();
            $widgets = $this->_getUserWidgets();
        }
        return $widgets;
    }

    /**
     * Returns whether the current user has a widget of the given type.
     *
     * @param string $type The widget type
     * @return bool Whether the current user has a widget of the given type
     */
    public function doesUserHaveWidget(string $type): bool
    {
        return WidgetRecord::find()
            ->where([
                'userId' => Craft::$app->getUser()->getIdentity()->id,
                'type' => $type,
            ])
            ->exists();
    }

    /**
     * Returns a widget by its ID.
     *
     * @param int $id The widget’s ID
     * @return WidgetInterface|null The widget, or null if it doesn’t exist
     */
    public function getWidgetById(int $id)
    {
        $result = $this->_createWidgetsQuery()
            ->where(['id' => $id, 'userId' => Craft::$app->getUser()->getIdentity()->id])
            ->one();
        return $result ? $this->createWidget($result) : null;
    }

    /**
     * Saves a widget for the current user.
     *
     * @param WidgetInterface $widget The widget to be saved
     * @param bool $runValidation Whether the widget should be validated
     * @return bool Whether the widget was saved successfully
     * @throws \Throwable if reasons
     */
    public function saveWidget(WidgetInterface $widget, bool $runValidation = true): bool
    {
        /** @var Widget $widget */
        $isNewWidget = $widget->getIsNew();
        // Fire a 'beforeSaveWidget' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_TRANSLATIONS_WIDGET)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_TRANSLATIONS_WIDGET, new WidgetEvent([
                'widget' => $widget,
                'isNew' => $isNewWidget,
            ]));
        }
        if (!$widget->beforeSave($isNewWidget)) {
            return false;
        }
        if ($runValidation && !$widget->validate()) {
            Translations::$plugin->logHelper->log('[' . __METHOD__ . '] Widget not saved due to validation error.', Constants::LOG_LEVEL_ERROR);
            return false;
        }
        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $widgetRecord = $this->_getUserWidgetRecordById($widget->id);
            $widgetRecord->type = get_class($widget);
            $widgetRecord->settings = $widget->getSettings();
            if ($isNewWidget) {
                // Set the sortOrder
                $maxSortOrder = (new Query())
                    ->from([Constants::TABLE_WIDGET])
                    ->where(['userId' => Craft::$app->getUser()->getIdentity()->id])
                    ->max('[[sortOrder]]');

                $widgetRecord->sortOrder = $maxSortOrder + 1;
            }
            $widgetRecord->save(false);
            // Now that we have a widget ID, save it on the model
            if ($isNewWidget) {
                $widget->id = $widgetRecord->id;
            }
            $widget->afterSave($isNewWidget);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
        // Fire an 'afterSaveWidget' event
        $this->trigger(self::EVENT_AFTER_SAVE_TRANSLATIONS_WIDGET, new WidgetEvent([
            'widget' => $widget,
            'isNew' => $isNewWidget,
        ]));
        return true;
    }

    /**
     * Soft-deletes a widget by its ID.
     *
     * @param int $widgetId The widget’s ID
     * @return bool Whether the widget was deleted successfully
     */
    public function deleteWidgetById(int $widgetId): bool
    {
        $widget = $this->getWidgetById($widgetId);
        if (!$widget) {
            return false;
        }
        return $this->deleteWidget($widget);
    }

    /**
     * Soft-deletes a widget.
     *
     * @param WidgetInterface $widget The widget to be deleted
     * @return bool Whether the widget was deleted successfully
     * @throws \Throwable if reasons
     */
    public function deleteWidget(WidgetInterface $widget): bool
    {
        $widgets = [$widget];

        foreach ($widgets as $widget) {
            /** @var Widget $widget */
            // Fire a 'beforeDeleteWidget' event
            if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_TRANSLATIONS_WIDGET)) {
                $this->trigger(self::EVENT_BEFORE_DELETE_TRANSLATIONS_WIDGET, new WidgetEvent([
                    'widget' => $widget,
                ]));
            }
            if (!$widget->beforeDelete()) {
                return false;
            }
            $transaction = Craft::$app->getDb()->beginTransaction();
            try {
                $widgetRecord = $this->_getUserWidgetRecordById($widget->id);
                $widgetRecord->delete();
                $widget->afterDelete();
                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }
            // Fire an 'afterDeleteWidget' event
            if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_TRANSLATIONS_WIDGET)) {
                $this->trigger(self::EVENT_AFTER_DELETE_TRANSLATIONS_WIDGET, new WidgetEvent([
                    'widget' => $widget,
                ]));
            }
        }
        return true;
    }

    /**
     * Reorders widgets.
     *
     * @param int[] $widgetIds The widget IDs
     * @return bool Whether the widgets were reordered successfully
     * @throws \Throwable if reasons
     */
    public function reorderWidgets(array $widgetIds): bool
    {
        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            foreach ($widgetIds as $widgetOrder => $widgetId) {
                $widgetRecord = $this->_getUserWidgetRecordById($widgetId);
                $widgetRecord->sortOrder = $widgetOrder + 1;
                $widgetRecord->save();
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
        return true;
    }

    /**
     * Changes the colspan of a widget.
     *
     * @param int $widgetId
     * @param int $colspan
     * @return bool
     */
    public function changeWidgetColspan(int $widgetId, int $colspan): bool
    {
        $widgetRecord = $this->_getUserWidgetRecordById($widgetId);
        $widgetRecord->colspan = $colspan;
        $widgetRecord->save();
        return true;
    }

    /**
     * Returns the info about a widget required to display its body and settings in the Dashboard.
     *
     * @param WidgetInterface $widget
     * @return array|false
     */
    public function getWidgetInfo(WidgetInterface $widget)
    {
        /** @var Widget $widget */
        $view = Craft::$app->getView();
        $namespace = $view->getNamespace();

        // Get the body HTML
        $widgetBodyHtml = $widget->getBodyHtml();
        if ($widgetBodyHtml === false) {
            return false;
        }
        // Get the settings HTML + JS
        $view->setNamespace('widget' . $widget->id . '-settings');
        $view->startJsBuffer();
        $settingsHtml = $view->namespaceInputs((string)$widget->getSettingsHtml());
        $settingsJs = $view->clearJsBuffer(false);
        // Get the colspan (limited to the widget type's max allowed colspan)
        $colspan = ($widget->colspan ?: $widget->minColspan());
        if (($maxColspan = $widget::maxColspan()) && $colspan > $maxColspan) {
            $colspan = $maxColspan;
        }
        $view->setNamespace($namespace);
        return [
            'id' => $widget->id,
            'type' => get_class($widget),
            'colspan' => $colspan,
            'title' => $widget->getTitle(),
            'name' => $widget->displayName(),
            'bodyHtml' => $widgetBodyHtml,
            'settingsHtml' => $settingsHtml,
            'settingsJs' => (string)$settingsJs,
        ];
    }

    /**
     * Returns a widget type’s SVG icon.
     *
     * @param WidgetInterface $widget
     * @return string
     */
    public function getWidgetIconSvg(WidgetInterface $widget): string
    {
        $iconPath = $widget::icon();
        if ($iconPath === null) {
            return $this->_getDefaultWidgetIconSvg($widget);
        }
        if (!is_file($iconPath)) {
            Translations::$plugin->logHelper->log('[' . __METHOD__ . '] Widget icon file doesn\'t exist: {$iconPath}', Constants::LOG_LEVEL_WARNING);
            return $this->_getDefaultWidgetIconSvg($widget);
        }
        if (!FileHelper::isSvg($iconPath)) {
            Translations::$plugin->logHelper->log('[' . __METHOD__ . '] Widget icon file is not an SVG: {$iconPath}', Constants::LOG_LEVEL_WARNING);
            return $this->_getDefaultWidgetIconSvg($widget);
        }
        return file_get_contents($iconPath);
    }

    public function getClosest($search, $arr)
    {
        $closest = null;
        foreach ($arr as $key => $item) {
            if ($closest === null || abs($search - $closest) > abs($key - $search)) {
                $closest = $key;
            }
        }
        return $closest;
    }

    // Private Methods
    // =========================================================================
    /**
     * Adds the default widgets to the logged-in user.
     */
    private function _addDefaultUserWidgets()
    {
        $user = Craft::$app->getUser()->getIdentity();
        $this->saveWidget($this->createWidget(RecentOrders::class));
        $this->saveWidget($this->createWidget(NewAndModifiedEntries::class));
        $this->saveWidget($this->createWidget(LanguageCoverage::class));
        $this->saveWidget($this->createWidget(Translators::class));
        $this->saveWidget($this->createWidget(News::class));

        // Update user preferences
        $preferences = [
            'hasTranslationsDashboard' => true,
        ];

        $usersService = new Users();
        $usersService->saveUserPreferences($user, $preferences);
    }

    /**
     * Gets a widget's record.
     *
     * @param int|null $widgetId
     * @return WidgetRecord
     */
    private function _getUserWidgetRecordById(int $widgetId = null): WidgetRecord
    {
        $userId = Craft::$app->getUser()->getIdentity()->id;

        if ($widgetId !== null) {
            $widgetRecord = WidgetRecord::findOne([
                'id' => $widgetId,
                'userId' => $userId
            ]);
            if (!$widgetRecord) {
                $this->_noWidgetExists($widgetId);
            }
        } else {
            $widgetRecord = new WidgetRecord();
            $widgetRecord->userId = $userId;
        }
        return $widgetRecord;
    }

    /**
     * Throws a "No widget exists" exception.
     *
     * @param int $widgetId
     * @throws WidgetNotFoundException
     */
    private function _noWidgetExists(int $widgetId)
    {
        throw new WidgetNotFoundException("No widget exists with the ID '{$widgetId}'");
    }
    /**
     * Returns the widget records for the current user.
     *
     * @return WidgetInterface[]|false
     * @throws Exception if no user is logged-in
     */
    private function _getUserWidgets()
    {
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user) {
            throw new \Exception('No logged-in user');
        }

        // Get value from user preferences
        $usersService = new Users();
        $hasTranslationsDashboard = $usersService->getUserPreference($user->id, 'hasTranslationsDashboard');

        if (!$hasTranslationsDashboard) {
            return false;
        }

        $results = $this->_createWidgetsQuery()
            ->where(['userId' => $user->id])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();
        $widgets = [];
        foreach ($results as $result) {
            $widgets[] = $this->createWidget($result);
        }

        return $widgets;
    }

    /**
     * @return Query
     */
    private function _createWidgetsQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'dateCreated',
                'dateUpdated',
                'colspan',
                'type',
                'settings',
            ])
            ->from([Constants::TABLE_WIDGET]);
    }

    /**
     * Returns the default icon SVG for a given widget type.
     *
     * @param WidgetInterface $widget
     * @return string
     */
    private function _getDefaultWidgetIconSvg(WidgetInterface $widget): string
    {
        return Craft::$app->getView()->renderTemplate('_includes/defaulticon.svg', [
            'label' => $widget::displayName()
        ]);
    }
}
