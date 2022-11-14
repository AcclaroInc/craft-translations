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
use yii\web\Response;
use craft\helpers\Json;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use craft\helpers\ArrayHelper;
use craft\base\WidgetInterface;
use craft\models\Updates as UpdatesModel;

use acclaro\translations\records\FileRecord;
use acclaro\translations\Translations;
use acclaro\translations\assetbundles\DashboardAssets;
use acclaro\translations\Constants;
use acclaro\translations\services\repository\WidgetRepository;

use yii\web\BadRequestHttpException;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class WidgetController extends BaseController
{
    protected $service;

    public function __construct($id, $module = null)
    {
        parent::__construct($id, $module);
        $this->service = new WidgetRepository();
    }

    // Action Methods
    // =========================================================================
    /**
     * @return mixed
     */
    public function actionIndex()
    {
        $this->requireLogin();

        /** @var \Craft\elements\User $currentUser */
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

        $view = $this->getView();
        $namespace = $view->getNamespace();

        $widgetTypes = $this->service->getAllWidgetTypes();
        $widgetTypeInfo = [];
        $view->setNamespace('__NAMESPACE__');

        $isSelectableWidget = false;
        foreach ($widgetTypes as $widgetType) {
            /** @var WidgetInterface $widgetType */
            if (!$widgetType::isSelectable()) {
                continue;
            }
            $view->startJsBuffer();
            $widget = $this->service->createWidget($widgetType);
            $settingsHtml = $view->namespaceInputs((string)$widget->getSettingsHtml());
            $settingsJs = (string)$view->clearJsBuffer(false);
            $class = get_class($widget);
            $widgetTypeInfo[$class] = [
                'iconSvg' => $this->service->getWidgetIconSvg($widget),
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

        /** @var WidgetInterface[] $widgets */
        $widgets = $this->service->getAllWidgets();
        $allWidgetJs = '';

        foreach ($widgets as $widget) {
            $view->startJsBuffer();
            $info = $this->service->getWidgetInfo($widget);
            $widgetJs = $view->clearJsBuffer(false);
            if ($info === false) {
                continue;
            }
            // If this widget type didn't come back in our getAllWidgetTypes() call, add it now
            if (!isset($widgetTypeInfo[$info['type']])) {
                $widgetTypeInfo[$info['type']] = [
                    'iconSvg' => $this->service->getWidgetIconSvg($widget),
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

        $widget = $this->service->createWidget($config);

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
        $widget = $this->service->getWidgetById($widgetId);
        if (!$widget) {
            throw new BadRequestHttpException();
        }

        // Create a new widget model with the new settings
        $settings = $request->getBodyParam('widget' . $widget->id . '-settings');
        $widget = $this->service->createWidget([
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
        $this->service->deleteWidgetById($widgetId);

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
            $this->service->changeWidgetColspan($id, $colspan);
        }

        return $this->asSuccess(null, []);
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
        $this->service->reorderWidgets($widgetIds);

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
                $data[$i]['color'] = $colorArr[$this->service->getClosest((int) $data[$i]['percentage'], $colorArr)];

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

        return $this->asSuccess(null, $data);
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

        return $this->asSuccess(null, $data);
    }

    public function actionGetRecentEntries($limit = 0)
    {
        // Get the post request
        $this->requirePostRequest();

        // Set variables
        $limit = Craft::$app->getRequest()->getParam('limit');
        $days = Craft::$app->getRequest()->getParam('days', '7');
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

        return $this->asSuccess(null, $data);
    }

    // PRIVATE METHODS

    /**
     * Attempts to save a widget and responds with JSON.
     *
     * @param \Craft\base\Widget $widget
     * @return Response
     */
    private function _saveAndReturnWidget(WidgetInterface $widget): Response
    {
        if (! $this->service->saveWidget($widget)) {
            return $this->asFailure(data: [
                'errors' => $this->getErrorMessage($widget->getFirstErrors()),
            ]);
        }

        $info = $this->service->getWidgetInfo($widget);
        $view = $this->getView();
        $additionalInfo = [
            'iconSvg' => $this->service->getWidgetIconSvg($widget),
            'name' => $widget::displayName(),
            'maxColspan' => $widget::maxColspan(),
            'selectable' => false,
        ];

        return $this->asSuccess(data: [
            'info' => $info,
            'additionalInfo' => $additionalInfo,
            'headHtml' => $view->getHeadHtml(),
            'footHtml' => $view->getBodyHtml(),
        ]);
    }
}
