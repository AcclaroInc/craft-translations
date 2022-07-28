<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\controllers;

use Craft;
use craft\db\Query;
use yii\web\Response;
use craft\helpers\Json;
use craft\web\Controller;
use craft\services\Users;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use craft\helpers\FileHelper;
use craft\events\WidgetEvent;
use craft\helpers\ArrayHelper;
use craft\base\WidgetInterface;
use craft\widgets\MissingWidget;
use craft\models\Updates as UpdatesModel;
use craft\errors\WidgetNotFoundException;
use craft\errors\MissingComponentException;
use craft\helpers\Component as ComponentHelper;

use acclaro\translations\records\FileRecord;
use acclaro\translations\records\WidgetRecord;
use acclaro\translations\Translations;
use acclaro\translations\assetbundles\DashboardAssets;
use acclaro\translations\Constants;

// Widget Classes
use acclaro\translations\widgets\News;
use acclaro\translations\widgets\Translators;
use acclaro\translations\widgets\RecentOrders;
use acclaro\translations\widgets\LanguageCoverage;
use acclaro\translations\widgets\NewAndModifiedEntries;
use yii\web\BadRequestHttpException;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class WidgetController extends Controller
{
    /**
     * @var int
     */
    private $_progress;

    const EVENT_REGISTER_TRANSLATIONS_WIDGET_TYPES = 'registerTranslationsWidgetTypes';
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

    // Action Methods
    // =========================================================================
    /**
     * @return mixed
     */
    public function actionIndex()
    {
        $this->requireLogin();
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser->can('translations:dashboard')) {
            switch (true) {
                case $currentUser->can('translations:orders'):
                    return $this->redirect(Constants::URL_ORDERS, 302, true);
                    break;

                case $currentUser->can('translations:translator'):
                    return $this->redirect(Constants::URL_TRANSLATOR, 302, true);
                    break;

                case $currentUser->can('translations:settings'):
                    return $this->redirect(Constants::URL_SETTINGS, 302, true);
                    break;

                default:
                    return $this->redirect(Constants::URL_ENTRIES, 302, true);
                    break;
            }
        }

        $pluginHandle = Constants::PLUGIN_HANDLE;

        $view = $this->getView();
        $namespace = $view->getNamespace();

        $widgetTypes = $this->getAllWidgetTypes();
        $widgetTypeInfo = [];
        $view->setNamespace('__NAMESPACE__');

        $isSelectableWidget = false;
        foreach ($widgetTypes as $widgetType) {
            /** @var WidgetInterface $widgetType */
            if (!$widgetType::isSelectable()) {
                continue;
            }
            $view->startJsBuffer();
            $widget = $this->createWidget($widgetType);
            $settingsHtml = $view->namespaceInputs((string)$widget->getSettingsHtml());
            $settingsJs = (string)$view->clearJsBuffer(false);
            $class = get_class($widget);
            $widgetTypeInfo[$class] = [
                'iconSvg' => $this->_getWidgetIconSvg($widget),
                'name' => $widget::displayName(),
                'maxColspan' => $widget::maxColspan(),
                'settingsHtml' => $settingsHtml,
                'settingsJs' => $settingsJs,
                'selectable' => true,
            ];
            $isSelectableWidget = true;
        }

        // Sort them by name
        ArrayHelper::multisort($widgetTypeInfo, 'name');

        $view->setNamespace($namespace);
        $variables = [];
        // Assemble the list of existing widgets
        $variables['widgets'] = [];

        /** @var Widget[] $widgets */
        $widgets = $this->getAllWidgets();
        $allWidgetJs = '';

        foreach ($widgets as $widget) {
            $view->startJsBuffer();
            $info = $this->_getWidgetInfo($widget);
            $widgetJs = $view->clearJsBuffer(false);
            if ($info === false) {
                continue;
            }
            // If this widget type didn't come back in our getAllWidgetTypes() call, add it now
            if (!isset($widgetTypeInfo[$info['type']])) {
                $widgetTypeInfo[$info['type']] = [
                    'iconSvg' => $this->_getWidgetIconSvg($widget),
                    'name' => $widget::displayName(),
                    'maxColspan' => $widget::maxColspan(),
                    'selectable' => false,
                ];
            }

            $variables['widgets'][] = $info;

            $allWidgetJs .= 'new Craft.Translations.Widget("#widget' . $widget->id . '", ' .
                Json::encode($info['settingsHtml']) . ', ' .
                'function(){' . $info['settingsJs'] . '}' .
                ");\n";
            if (!empty($widgetJs)) {
                // Allow any widget JS to execute *after* we've created the Craft.Translations.Widget instance
                $allWidgetJs .= $widgetJs . "\n";
            }
        }

        // Check For Plugin Updates
        $variables['updates'] = $this->checkForUpdate(Constants::PLUGIN_HANDLE);

        // Register Dashboard Assets
        $view->registerAssetBundle(DashboardAssets::class);
        $view->registerJs('window.translationsdashboard = new Craft.Translations.Dashboard(' . Json::encode($widgetTypeInfo) . ');');

        $view->registerJs($allWidgetJs);
        $variables['licenseStatus'] = Craft::$app->plugins->getPluginLicenseKeyStatus(Constants::PLUGIN_HANDLE);
        $variables['baseAssetsUrl'] = Craft::$app->assetManager->getPublishedUrl(
            Constants::URL_BASE_ASSETS,
            true
        );

        $variables['widgetTypes'] = $widgetTypeInfo;
        $variables['selectedSubnavItem'] = 'dashboard';
        $variables['isSelectableWidget'] = $isSelectableWidget;

        return $this->renderTemplate('translations/_index', $variables);
    }

    /**
     * Check for Latest Updates
     *
     * @return bool
     */
    public function checkForUpdate($pluginHandle): bool
    {
        $hasUpdate = false;

        $updateData = Craft::$app->getApi()->getUpdates([]);

        $updates = new UpdatesModel($updateData);

        foreach ($updates->plugins as $key => $pluginUpdate) {
            if ($key === $pluginHandle && $pluginUpdate->getHasReleases()) {
                $hasUpdate = true;
            }
        }

        return $hasUpdate;
    }

    /**
     * Creates a new widget.
     *
     * @return Response
     */
    public function actionCreateWidget(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $request = Craft::$app->getRequest();

        $type = $request->getRequiredBodyParam('type');
        $settingsNamespace = $request->getBodyParam('settingsNamespace');
        if ($settingsNamespace) {
            $settings = $request->getBodyParam($settingsNamespace);
        } else {
            $settings = null;
        }

        $config = [
            'type' => $type,
            'settings' => $settings,
        ];

        $widget = $this->createWidget($config);
        return $this->_saveAndReturnWidget($widget);
    }

    /**
     * Saves a widget’s settings.
     *
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionSaveWidgetSettings(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $request = Craft::$app->getRequest();
        $widgetId = $request->getRequiredBodyParam('widgetId');
        // Get the existing widget
        /** @var Widget $widget */
        $widget = $this->getWidgetById($widgetId);
        if (!$widget) {
            throw new BadRequestHttpException();
        }
        // Create a new widget model with the new settings
        $settings = $request->getBodyParam('widget' . $widget->id . '-settings');
        $widget = $this->createWidget([
            'id' => $widget->id,
            'dateCreated' => $widget->dateCreated,
            'dateUpdated' => $widget->dateUpdated,
            'colspan' => $widget->colspan,
            'type' => get_class($widget),
            'settings' => $settings,
        ]);
        return $this->_saveAndReturnWidget($widget);
    }

    /**
     * Deletes a widget.
     *
     * @return Response
     */
    public function actionDeleteUserWidget(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $widgetId = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('id'));
        $this->deleteWidgetById($widgetId);
        return $this->asJson(['success' => true]);
    }

    /**
     * Changes the colspan of a widget.
     *
     * @return Response
     */
    public function actionChangeWidgetColspan(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $request = Craft::$app->getRequest();
        $widgetId = $request->getRequiredBodyParam('id');
        $colspan = $request->getRequiredBodyParam('colspan');
        foreach (explode(',', $widgetId) as $id) {
            $this->changeWidgetColspan($id, $colspan);
        }
        return $this->asJson(['success' => true]);
    }

    /**
     * Reorders widgets.
     *
     * @return Response
     */
    public function actionReorderUserWidgets(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $widgetIds = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));
        $this->reorderWidgets($widgetIds);
        return $this->asJson(['success' => true]);
    }

    public function actionGetLanguageCoverage($limit = 0)
    {
        // Get post request
        $this->requirePostRequest();

        // Set variables
        $limit = Craft::$app->getRequest()->getParam('limit');
        $data = [];
        $i = 0;

        // Get array of site ids
        $siteIds = Craft::$app->getSites()->getAllSiteIds();

        // Grade based color options
        $colorArr = array(
            '85' => '#27AE60',
            '75' => '#F1C40E',
            '50' => '#F2842D',
            '0' => '#D0021B'
        );

        // Loop through site ids
        foreach ($siteIds as $key => $id) {
            $site = Craft::$app->getSites()->getSiteById($id);

            // Only show for target sites
            if (!$site->primary) {
                // Get entries count
                $enabledEntries = Entry::find()
                ->site($site)
                // ->enabledForSite()
                ->count();

                // Get in progress entry translation count
                $inQueue = FileRecord::find()
                    ->select(['elementId'])
                    ->distinct(true)
                    ->where(['status' => [
                        Constants::FILE_STATUS_NEW,
                        Constants::FILE_STATUS_PREVIEW,
                        Constants::FILE_STATUS_IN_PROGRESS,
                        Constants::FILE_STATUS_COMPLETE,
                        ]])
                    ->andWhere(['targetSite' => $id])
                    ->count();

                $translated = FileRecord::find()
                    ->select(['elementId'])
                    ->distinct(true)
                    ->where(['status' => Constants::FILE_STATUS_PUBLISHED])
                    ->andWhere(['targetSite' => $id])
                    ->count();

                // Set data
                $data[$i]['siteId'] = $id;
                $data[$i]['name'] = $site->name;
                $data[$i]['url'] = UrlHelper::cpUrl('settings/sites/'.$id);
                $data[$i]['enabledEntries'] = $enabledEntries;
                $data[$i]['inQueue'] = $inQueue;
                $data[$i]['translated'] = $translated;
                $data[$i]['percentage'] = $enabledEntries ? number_format((($translated / $enabledEntries)*100), 0) : 0;
                $data[$i]['color'] = $colorArr[$this->getClosest((int) $data[$i]['percentage'], $colorArr)];

                // Sort the data by highest percentage
                usort($data, function($a, $b) {
                    return $b['percentage'] <=> $a['percentage'];
                });

                if ($i + 1 < $limit) {
                    $i++;
                } else {
                    break;
                }
            }
        }

        return $this->asJson([
            'success' => true,
            'data' => $data,
            'error' => null
        ]);
    }

    public function actionGetRecentlyModified($limit = 0)
    {
        // Get the post request
        $this->requirePostRequest();

        // Set variables
        $limit = Craft::$app->getRequest()->getParam('limit');
        $files = [];
        $data = [];
        $i = 0;

        // Get array of entry IDs sorted by most recently updated
        $entries = Entry::find()
            ->orderBy(['dateUpdated' => SORT_DESC])
            ->ids();

        // Get Files and order by most recently updated
        $records = FileRecord::find()
            ->where(['status' => Constants::FILE_STATUS_PUBLISHED])
            ->orderBy(['dateUpdated' => SORT_DESC])
            ->all();
        // Get in progress Files and order
        $inProgressRecords = FileRecord::find()
            ->where(['status' => Constants::FILE_STATUS_IN_PROGRESS])
            ->orderBy(['dateUpdated' => SORT_DESC])
            ->all();

        // Build file array
        foreach ($records as $key => $record) {
            if (! array_key_exists($record->elementId, $files)) {
                $files[$record->elementId] = $record;
            }
        }

        // Filter out published records which are also present in $inProgressRecords and are created after than published ones.
        foreach ($inProgressRecords as $key => $record) {
            if (array_key_exists($record->elementId, $files)) {
                if ($record->dateCreated > $files[$record->elementId]->dateCreated) {
                    unset($files[$record->elementId]);
                }
            }
        }

        // Loop through entry IDs
        foreach ($entries as $id) {
            // Check to see if we have a file that meets the conditions
            $fileRecord = $files[$id] ?? null;

            if ($fileRecord) {
                $fileId = $fileRecord->id;
                // Now we can get the element
                $element = Craft::$app->getElements()->getElementById($id, null, Craft::$app->getSites()->getPrimarySite()->id);
                // Get the elements translated file
                $file = Translations::$plugin->fileRepository->getFileById($fileId);

                // Is the element more recent than the file?
                if ($element->dateUpdated->format('Y-m-d H:i:s') > $file->dateUpdated->format('Y-m-d H:i:s')) {
                    // Translated file XML
                    $translatedXML = $file->source;

                    // Current entries XML
                    $currentXML = Translations::$plugin->elementToFileConverter->convert(
                        $element,
                        Constants::FILE_FORMAT_XML,
                        [
                            'sourceSite'    =>  Craft::$app->getSites()->getPrimarySite()->id,
                            'targetSite'    => $file->targetSite,
                            'orderId'       => $file->orderId,
                        ]
                    );

                    $diffData = Translations::$plugin->fileRepository->getSourceTargetDifferences($currentXML, $translatedXML);
                    $diffHtml = Translations::$plugin->fileRepository->getFileDiffHtml($diffData, true);

                    $sourceString = '';
                    $targetString = '';
                    foreach ($diffData as $key => $field) {
                        $sourceString .= $field['source'];
                        $targetString .= $field['target'];
                    }

                    $wordCount = (Translations::$plugin->elementTranslator->getWordCount($element) - $file->wordCount);

                    // Check to see if there is a difference between translated XML and current entries XML
                    if (! empty($diffHtml) && base64_encode($sourceString) != base64_encode($targetString)) {
                        // Create data array
                        $data[$i]['entryName'] = Craft::$app->getEntries()->getEntryById($element->id)->title;
                        $data[$i]['entryId'] = $element->id;
                        $data[$i]['entryDate'] = $element->dateUpdated->format('M j, Y g:i a');
                        $data[$i]['entryDateTimestamp'] = $element->dateUpdated->format('Y-m-d H:i:s');
                        $data[$i]['siteId'] = $element->siteId;
                        $data[$i]['siteLabel'] = Craft::$app->sites->getSiteById($element->siteId)->name. '<span class="light"> ('. Craft::$app->sites->getSiteById($element->siteId)->language. ')</span>';
                        $data[$i]['entryUrl'] = $element->getCpEditUrl();
                        $data[$i]['fileDate'] = $file->dateUpdated->format('M j, Y g:i a');
                        $data[$i]['wordDifference'] = (int)$wordCount == $wordCount && (int)$wordCount > 0 ? '+'.$wordCount : $wordCount;
                        $data[$i]['diff'] = $diffHtml;

                        // Sort data array by most recent
                        usort($data, function($a, $b) {
                            return $b['entryDateTimestamp'] <=> $a['entryDateTimestamp'];
                        });

                        // Only return set limit
                        if ($i + 1 < $limit) {
                            $i++;
                        } else {
                            break;
                        }
                    }
                }
            }
        }

        return $this->asJson([
            'success' => true,
            'data' => $data,
            'error' => null
        ]);
    }

    public function actionGetRecentEntries($limit = 0)
    {
        // Get the post request
        $this->requirePostRequest();

        // Set variables
        $limit = Craft::$app->getRequest()->getParam('limit');
        $days = Craft::$app->getRequest()->getParam('days');
        $data = [];
        $i = 0;

        // Get array of entry IDs sorted by most recently updated
        $fromDate = (new \DateTime("-{$days} days"))->format(\DateTime::ATOM);
        $entries = Entry::find()
            ->dateCreated(">= {$fromDate}")
            ->orderBy(['dateCreated' => SORT_DESC])
            ->ids();

        $entriesInOrders = [];
        $records = FileRecord::find()
            ->select(['elementId'])
            ->groupBy('elementId')
            ->all();

        foreach ($records as $key => $record) {
            array_push($entriesInOrders, $record->elementId);
        }

        // Loop through entry IDs
        foreach ($entries as $id) {
            // exclude entries for which order has been created
            if (in_array($id, $entriesInOrders)) continue;
            // Now we can get the element
            $element = Craft::$app->getElements()->getElementById($id, null, Craft::$app->getSites()->getPrimarySite()->id);

            // Current entries XML
            $currentXML = Translations::$plugin->elementToFileConverter->convert(
                $element,
                Constants::FILE_FORMAT_XML,
                [
                    'sourceSite'    => Craft::$app->getSites()->getPrimarySite()->id,
                    'targetSite'    => Craft::$app->getSites()->getPrimarySite()->id,
                ]
            );

            $diffHtml = Translations::$plugin->fileRepository->getFileDiffHtml($currentXML);

            // Create data array
            $data[$i]['entryName'] = Craft::$app->getEntries()->getEntryById($element->id)->title;
            $data[$i]['entryId'] = $element->id;
            $data[$i]['entryDate'] = $element->dateUpdated->format('M j, Y g:i a');
            $data[$i]['entryDateTimestamp'] = $element->dateUpdated->format('Y-m-d H:i:s');
            $data[$i]['siteId'] = $element->siteId;
            $data[$i]['siteLabel'] = Craft::$app->sites->getSiteById($element->siteId)->name. '<span class="light"> ('. Craft::$app->sites->getSiteById($element->siteId)->language. ')</span>';
            $data[$i]['entryUrl'] = $element->getCpEditUrl();
            $wordCount = (Translations::$plugin->elementTranslator->getWordCount($element));
            $data[$i]['wordDifference'] = (int)$wordCount == $wordCount && (int)$wordCount > 0 ? '+'.$wordCount : $wordCount;
            $data[$i]['diff'] = $diffHtml;

            // Sort data array by most recent
            usort($data, function($a, $b) {
                return $b['entryDateTimestamp'] <=> $a['entryDateTimestamp'];
            });

            // Only return set limit
            if ($i + 1 < $limit) {
                $i++;
            } else {
                break;
            }
        }

        return $this->asJson([
            'success' => true,
            'data' => $data,
            'error' => null
        ]);
    }

    // Public Methods
    // =========================================================================

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
            $widget = ComponentHelper::createComponent($config, WidgetInterface::class);
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
            Translations::$plugin->logHelper->log( '['. __METHOD__ .'] Widget not saved due to validation error.', Constants::LOG_LEVEL_ERROR );
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
     * Gets a widget's record.
     *
     * @param int|null $widgetId
     * @return WidgetRecord
     */
    private function _getUserWidgetRecordByType(string $widgetType = null): WidgetRecord
    {
        $userId = Craft::$app->getUser()->getIdentity()->id;

        if ($widgetType !== null) {
            $widgetRecord = WidgetRecord::findOne([
                'type' => $widgetType,
                'userId' => $userId
            ]);
            if (!$widgetRecord) {
                throw new WidgetNotFoundException("No widget exists of Type '{$widgetType}'");
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
     * Returns the info about a widget required to display its body and settings in the Dashboard.
     *
     * @param WidgetInterface $widget
     * @return array|false
     */
    private function _getWidgetInfo(WidgetInterface $widget)
    {
        /** @var Widget $widget */
        $view = $this->getView();
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
    private function _getWidgetIconSvg(WidgetInterface $widget): string
    {
        $iconPath = $widget::iconPath();
        if ($iconPath === null) {
            return $this->_getDefaultWidgetIconSvg($widget);
        }
        if (!is_file($iconPath)) {
            Translations::$plugin->logHelper->log( '['. __METHOD__ .'] Widget icon file doesn\'t exist: {$iconPath}', Constants::LOG_LEVEL_WARNING );
            return $this->_getDefaultWidgetIconSvg($widget);
        }
        if (!FileHelper::isSvg($iconPath)) {
            Translations::$plugin->logHelper->log( '['. __METHOD__ .'] Widget icon file is not an SVG: {$iconPath}', Constants::LOG_LEVEL_WARNING );
            return $this->_getDefaultWidgetIconSvg($widget);
        }
        return file_get_contents($iconPath);
    }

    /**
     * Returns the default icon SVG for a given widget type.
     *
     * @param WidgetInterface $widget
     * @return string
     */
    private function _getDefaultWidgetIconSvg(WidgetInterface $widget): string
    {
        return $this->getView()->renderTemplate('_includes/defaulticon.svg', [
            'label' => $widget::displayName()
        ]);
    }

    /**
     * Attempts to save a widget and responds with JSON.
     *
     * @param WidgetInterface $widget
     * @return Response
     */
    private function _saveAndReturnWidget(WidgetInterface $widget): Response
    {
        /** @var Widget $widget */
        if ($this->saveWidget($widget)) {
            $info = $this->_getWidgetInfo($widget);
            $view = $this->getView();
            $additionalInfo = [
                'iconSvg' => $this->_getWidgetIconSvg($widget),
                'name' => $widget::displayName(),
                'maxColspan' => $widget::maxColspan(),
                'selectable' => false,
            ];
            return $this->asJson([
                'success' => true,
                'info' => $info,
                'additionalInfo' => $additionalInfo,
                'headHtml' => $view->getHeadHtml(),
                'footHtml' => $view->getBodyHtml(),
            ]);
        }
        $allErrors = [];
        foreach ($widget->getErrors() as $attribute => $errors) {
            foreach ($errors as $error) {
                $allErrors[] = $error;
            }
        }
        return $this->asJson([
            'errors' => $allErrors
        ]);
    }

    private function getClosest($search, $arr) {
        $closest = null;
        foreach ($arr as $key => $item) {
           if ($closest === null || abs($search - $closest) > abs($key - $search)) {
              $closest = $key;
           }
        }
        return $closest;
     }

     private function setProgress(float $progress)
    {
        if ($progress !== $this->_progress) {
            $this->_progress = round(100 * $progress);
        }
    }
}
