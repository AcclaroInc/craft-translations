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
use craft\db\Query;
use craft\elements\Entry;
use craft\services\Users;
use craft\helpers\Component;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\events\WidgetEvent;
use craft\base\WidgetInterface;
use craft\widgets\MissingWidget;
use yii\base\Component as BaseComponent;
use craft\errors\WidgetNotFoundException;
use craft\errors\MissingComponentException;
use acclaro\translations\records\WidgetRecord;
use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\records\FileRecord;
use craft\models\Updates as UpdatesModel;

// Widget Classes
use acclaro\translations\widgets\Ads;
use acclaro\translations\widgets\BaseWidget;
use acclaro\translations\widgets\News;
use acclaro\translations\widgets\Translators;
use acclaro\translations\widgets\RecentOrders;
use acclaro\translations\widgets\LanguageCoverage;
use acclaro\translations\widgets\NewAndModifiedEntries;

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
            Ads::class,
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
     * @return BaseWidget|null The widget, or null if it doesn’t exist
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
        if (!$widget || !$widget::isDeletable()) {
            throw new \Exception("operation not allowed");
        }
        return $this->deleteWidget($widget);
    }

    /**
     * Soft-deletes a widget.
     *
     * @param BaseWidget $widget The widget to be deleted
     * @return bool Whether the widget was deleted successfully
     * @throws \Throwable if reasons
     */
    public function deleteWidget(BaseWidget $widget): bool
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
            'subTitle' => $widget->getSubtitle(),
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

    public function getNewsArticles($limit): array
    {
        return $this->_getArticles($limit);
    }

    public function getRecentEntries($limit, $days): array
    {
        $data = [];
        $i = 0;

        // Get array of entry IDs sorted by most recently updated
        $fromDate = (new \DateTime("-{$days} days"))->format(\DateTime::ATOM);
        $entries = Entry::find()
            ->drafts(null)
            ->draftOf(false)
            ->provisionalDrafts(null)
            ->revisions(null)
            ->sectionId(['not', null])
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
            $element = Translations::$plugin->elementRepository->getElementById($id, Craft::$app->getSites()->getPrimarySite()->id);

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

        return $data;
    }

    public function getRecentlyModifiedEntries($limit, $days): array
    {
        $files = [];
        $data = [];
        $i = 0;

        $fromDate = (new \DateTime("-{$days} days"))->format(\DateTime::ATOM);
        // Get array of entry IDs sorted by most recently updated
        $entries = Entry::find()
            ->sectionId(['not', null])
            ->drafts(null)
            ->draftOf(false)
            ->provisionalDrafts(null)
            ->revisions(null)
            ->dateUpdated(">= {$fromDate}")
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
                $element = Translations::$plugin->elementRepository->getElementById($id, Craft::$app->getSites()->getPrimarySite()->id);
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

        return $data;
    }

    public function getLanguageCoverage($limit): array
    {
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
                ->sectionId(['not', null])
                ->drafts(null)
                ->draftOf(false)
                ->revisions(null)
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

        return $data;
    }

    /**
     * Check for new releases
     *
     * @return bool
     */
    public function checkForUpdate(): bool
    {
        return $this->_checkForUpdate(Constants::PLUGIN_HANDLE);
    }

    // Private Methods
    // =========================================================================
    /**
     * Adds the default widgets to the logged-in user.
     */
    private function _addDefaultUserWidgets()
    {
        $user = Craft::$app->getUser()->getIdentity();
        $this->saveWidget($this->createWidget(Ads::class));
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
    private function _getUserWidgetRecordById(?int $widgetId = null): WidgetRecord
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

    /**
     * Returns the recent articles from Acclaro Blog RSS feed
     *
     * @return array
     */
    private function _getArticles($limit): array
    {
        $articles = [];

        $client = Craft::createGuzzleClient(array(
            'base_uri' => 'https://www.acclaro.com/',
            'timeout' => 2.0,
            'verify' => false
        ));

        try {
            $response = $client->get('feed/');
        } catch (\Exception $e) {
            Translations::$plugin->logHelper->log("[" . __METHOD__ . "] . $e", Constants::LOG_LEVEL_ERROR);
            return [];
        }

        $data = $response->getBody()->getContents();
        $feed = simplexml_load_string($data);

        $i = 0;
        foreach ($feed->channel->item as $key => $article) {
            $articles[$i]['title'] = (string) $article->title;
            $articles[$i]['link'] = (string) $article->link;
            $articles[$i]['pubDate'] = date('m/d/Y', strtotime($article->pubDate));

            if ($i + 1 < $limit) {
                $i++;
            } else {
                break;
            }
        }

        return $articles;
    }

    private function _checkForUpdate($pluginHandle): bool
    {
        $hasUpdate = false;

        try {
            $updateData = Craft::$app->getApi()->getUpdates([]);
    
            $updates = new UpdatesModel($updateData);
    
            foreach ($updates->plugins as $key => $pluginUpdate) {
                if ($key === $pluginHandle && $pluginUpdate->getHasReleases()) {
                    $hasUpdate = true;
                }
            }
        }catch(\Exception $e) {
            Translations::$plugin->logHelper->log($e->getMessage(), Constants::LOG_LEVEL_ERROR);
        }

        return $hasUpdate;
    }
}
