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
use craft\helpers\ArrayHelper;
use acclaro\translations\widgets\BaseWidget;
use yii\web\BadRequestHttpException;

use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\assetbundles\DashboardAssets;
use acclaro\translations\services\repository\WidgetRepository;

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
            /** @var BaseWidget $widgetType */
            if (!$widgetType::isSelectable() || !$widgetType::isLive()) {
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

        /** @var BaseWidget[] $widgets */
        $widgets = $this->service->getAllWidgets();
        $allWidgetJs = '';

        foreach ($widgets as $widget) {
            $view->startJsBuffer();
            $info = $this->service->getWidgetInfo($widget);
            $widgetJs = $view->clearJsBuffer(false);
            if ($info === false || !$widget::isLive()) {
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
        $languageCoverageCallback = fn() => $this->service->getLanguageCoverage($limit);

        $data = Translations::$plugin->cacheHelper->getOrSetCache(
            Constants::CACHE_KEY_LANGUAGE_COVERAGE_WIDGET,
            $languageCoverageCallback
        );

        return $this->asSuccess(null, $data);
    }

    public function actionGetRecentlyModified($limit = 0)
    {
        // Get the post request
        $this->requirePostRequest();

        // Set variables
        $limit = Craft::$app->getRequest()->getParam('limit');
        $days = Craft::$app->getRequest()->getParam('days', '7');
        $recentlyModifiedCallback = fn() => $this->service->getRecentlyModifiedEntries($limit, $days);

        $data = Translations::$plugin->cacheHelper->getOrSetCache(
            Constants::CACHE_KEY_RECENTLY_MODIFIED_WIDGET,
            $recentlyModifiedCallback
        );

        return $this->asSuccess(null, $data);
    }

    public function actionGetRecentEntries($limit = 0)
    {
        // Get the post request
        $this->requirePostRequest();

        // Set variables
        $limit = Craft::$app->getRequest()->getParam('limit');
        $days = Craft::$app->getRequest()->getParam('days', '7');
        $recentEntriesCallback = fn() => $this->service->getRecentEntries($limit, $days);

        $data = Translations::$plugin->cacheHelper->getOrSetCache(
            Constants::CACHE_KEY_RECENT_ENTRIES_WIDGET,
            $recentEntriesCallback
        );

        return $this->asSuccess(null, $data);
    }

    public function actionGetAcclaroNews($limit = 0)
    {
        // Get the post request
        $this->requirePostRequest();

        // Set variables
        $limit = Craft::$app->getRequest()->getParam('limit');

        // Get the articles from the RSS feed using cache wrapper
        $articleCallback = fn() => $this->service->getNewsArticles($limit);

        $articles = Translations::$plugin->cacheHelper->getOrSetCache(
            Constants::CACHE_KEY_NEWS_ARTICLES_WIDGET,
            $articleCallback
        );

        return $this->asSuccess(null, $articles);
    }

    public function actionCheckForPluginUpdates()
    {
        $hasUpdate = $this->service->checkForUpdate();

        return $this->asSuccess(null, ['update' => $hasUpdate]);
    }

    // PRIVATE METHODS

    /**
     * Attempts to save a widget and responds with JSON.
     *
     * @param \Craft\base\Widget $widget
     * @return Response
     */
    private function _saveAndReturnWidget(BaseWidget $widget): Response
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
