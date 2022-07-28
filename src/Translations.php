<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations;

use Craft;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use yii\base\Event;
use craft\base\Plugin;
use craft\web\UrlManager;
use craft\elements\Entry;
use craft\services\Plugins;
use craft\events\ModelEvent;
use craft\helpers\UrlHelper;
use craft\events\DraftEvent;
use craft\services\Elements;
use craft\events\PluginEvent;
use craft\services\Drafts;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\DeleteElementEvent;
use acclaro\translations\Constants;
use acclaro\translations\services\App;
use acclaro\translations\elements\Order;
use acclaro\translations\base\PluginTrait;
use craft\console\Application as ConsoleApplication;
use acclaro\translations\assetbundles\EntryAssets;
use acclaro\translations\assetbundles\CategoryAssets;
use acclaro\translations\assetbundles\Assets;
use acclaro\translations\assetbundles\UniversalAssets;
use acclaro\translations\assetbundles\EditDraftAssets;
use acclaro\translations\assetbundles\GlobalSetAssets;
use acclaro\translations\services\job\DeleteDrafts;
use craft\errors\MigrationException;
use craft\events\DeleteSiteEvent;
use craft\services\Sites;
use yii\web\User;

class Translations extends Plugin
{
    // Traits
    // =========================================================================

    use PluginTrait;

    /**
     * Enable use of self::$plugin
     *
     * @var \acclaro\translations\services\App
     */
    public static $plugin;

    /**
     * @var View
     */
    public static $view;

    /**
     * @var bool
     */
    public $hasCpSection = true;

    /**
     * @var bool
     */
    public $hasCpSettings = true;

    /**
     * @var string
     */
    public $schemaVersion = Constants::PLUGIN_SCHEMA_VERSION;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_LOAD_PLUGINS,
            function () {
                if (self::getInstance()->settings->apiLogging) {
                    Craft::debug(
                        '['. __METHOD__ .'] Plugins::EVENT_AFTER_LOAD_PLUGINS',
                        'translations'
                    );
                }
                $this->setComponents([
                    'app' => App::class
                ]);

                self::$plugin = $this->get('app');
                self::$view = Craft::$app->getView();

                $this->installEventListeners();

                if (Craft::$app instanceof ConsoleApplication) {
                    $this->controllerNamespace = 'acclaro\translations\console\controllers';
                }
            }
        );

        // Prune deleted sites translations from `translation_translations` table
        Event::on(Sites::class, Sites::EVENT_BEFORE_DELETE_SITE, function (DeleteSiteEvent $event) {
            self::$plugin->logHelper->log(
                '[' . __METHOD__ . '] Sites::EVENT_BEFORE_DELETE_SITE',
                Constants::LOG_LEVEL_INFO
            );

            $this->_onDeleteSite($event);
        });

        Event::on(
            Drafts::class,
            Drafts::EVENT_BEFORE_APPLY_DRAFT,
            function (DraftEvent $event) {
                self::$plugin->logHelper->log(
                    Craft::t(
                        'translations',
                        '{name} Drafts::EVENT_BEFORE_APPLY_DRAFT',
                        ['name' => $this->name]
                    ),
                    Constants::LOG_LEVEL_INFO
                );

                $this->_onBeforePublishDraft($event);
            }
        );

        Event::on(
            Drafts::class,
            Drafts::EVENT_AFTER_APPLY_DRAFT,
            function (DraftEvent $event) {
                self::$plugin->logHelper->log(
                    '['. __METHOD__ .'] Drafts::EVENT_AFTER_APPLY_DRAFT',
                    Constants::LOG_LEVEL_INFO
                );

                if ($event->draft) {
                    $this->_onApplyDraft($event);
                }
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                self::$plugin->logHelper->log(
                    '['. __METHOD__ .'] Elements::EVENT_AFTER_SAVE_ELEMENT',
                    Constants::LOG_LEVEL_INFO
                );

                $this->_onSaveEntry($event);
            }
        );

        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_DELETE_ELEMENT,
            function (DeleteElementEvent $event) {
                self::$plugin->logHelper->log(
                    '['. __METHOD__ .'] Elements::EVENT_BEFORE_DELETE_ELEMENT',
                    Constants::LOG_LEVEL_INFO
                );

                $this->_onDeleteElement($event);
            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                self::$plugin->logHelper->log(
                    '['. __METHOD__ .'] Plugins::EVENT_AFTER_INSTALL_PLUGIN',
                    Constants::LOG_LEVEL_INFO
                );
                if ($event->plugin === $this) {
                    $request = Craft::$app->getRequest();
                    if ($request->isCpRequest) {
                        Craft::$app->getResponse()->redirect(UrlHelper::cpUrl(
                            'translations/settings'
                        ))->send();
                    }
                }
            }
        );
        if(self::getInstance()->settings->apiLogging) {
            Craft::info(
                Craft::t(
                    'translations',
                    '{name} plugin loaded',
                    ['name' => $this->name]
                ),
                'translations'
            );
        }

    }

    /**
     * @inheritdoc
     */
    public function uninstall()
    {
        // Let's clean up the drafts table
        $drafts = self::$plugin->fileRepository->getAllDraftIds();
        if ($drafts) {
            Craft::$app->queue->push(new DeleteDrafts([
                'description' => Constants::JOB_DELETING_DRAFT,
                'drafts' => $drafts,
            ]));
        }

        if (($migration = $this->createInstallMigration()) !== null) {
            try {
                $this->getMigrator()->migrateDown($migration);
            } catch (MigrationException $e) {
                return false;
            }
        }
        $this->afterUninstall();
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem()
    {
        $subNavs = [];
        $navItem = parent::getCpNavItem();
        /** @var User $currentUser */
        $currentUser = Craft::$app->getUser()->getIdentity();

        if ($currentUser->can('translations:dashboard')) {
            $subNavs['dashboard'] = [
                'label' => 'Dashboard',
                'url' => Constants::URL_TRANSLATIONS,
            ];
        }
        if ($currentUser->can('translations:orders')) {
            $subNavs['orders'] = [
                'label' => 'Orders',
                'url' => Constants::URL_ORDERS,
            ];
        }
        if ($currentUser->can('translations:translator')) {
            $subNavs['translators'] = [
                'label' => 'Translators',
                'url' => Constants::URL_TRANSLATOR,
            ];
        }
        if ($currentUser->can('translations:static-translations')) {
            $subNavs['static-translations'] = [
                'label' => 'Static Translations',
                'url' => Constants::URL_STATIC_TRANSLATIONS,
            ];
        }

        if ($currentUser->can('translations:settings')) {
            $subNavs['settings'] = [
                'label' => 'Settings',
                'url' => Constants::URL_SETTINGS,
            ];
        }

        $navItem = array_merge($navItem, [
            'subnav' => $subNavs,
        ]);
        return $navItem;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse()
    {
        // Just redirect to the plugin settings page
        Craft::$app->getResponse()->redirect(UrlHelper::cpUrl(Constants::URL_SETTINGS));
    }

    protected function createSettingsModel()
    {
        return new \acclaro\translations\models\Settings();
    }

    protected function settingsHtml()
    {
        return \Craft::$app->getView()->renderTemplate('translations/settings/general', [
            'settings' => $this->getSettings()
        ]);
    }

    /**
     * Determine whether our table schema exists or not; this is needed because
     * migrations such as the install migration and base_install migration may
     * not have been run by the time our init() method has been called
     *
     * @return bool
     */
    protected function tableSchemaExists(): bool
    {
        return (Craft::$app->db->schema->getTableSchema('{{%translations_orders}}') !== null);
    }

    /**
     * Install our event listeners.
     */
    protected function installEventListeners()
    {
        if ($this->tableSchemaExists()) {
            $this->_setLogging();
            $this->_registerCpRoutes();

            if (Craft::$app->request->getIsCpRequest()) {
                $this->_includeResources(Craft::$app->getRequest()->getPathInfo());
            }

            self::$plugin->translationRepository->loadTranslations();

            Event::on(
                Elements::class,
                Elements::EVENT_REGISTER_ELEMENT_TYPES,
                function (RegisterComponentTypesEvent $event) {
                    $event->types[] = Order::class;
                }
            );

            $request = Craft::$app->getRequest();
            // Install only for non-console Control Panel requests
            if ($request->getIsCpRequest() && !$request->getIsConsoleRequest()) {
                $this->installCpEventListeners();
            }
        }
    }

    private function _registerCpRoutes()
    {
        Event::on(
            UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function (RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, [
                    // Widget Controller
                    'translations' => 'translations/widget/index',

                    // Translator Controller
                    'translations/translators' => 'translations/translator/index',
                    'translations/translators/new' => 'translations/translator/detail',
                    'translations/translators/detail/<translatorId:\d+>' => 'translations/translator/detail',

                    // Order Controller
                    'translations/orders' => 'translations/order/order-index',
                    'translations/orders/create' => 'translations/order/order-detail',
                    'translations/orders/detail/<orderId:\d+>' => 'translations/order/order-detail',
                    'translations/orders/exportfile' => 'translations/files/export-file',
                    'translations/orders/importfile' => 'translations/files/import-file',

                    // Settings Controller
                    'translations/settings' => 'translations/settings/index',
                    'translations/settings/settings-check' => 'translations/settings/settings-check',
                    'translations/settings/send-logs' => 'translations/settings/send-logs',
                    'translations/settings/configuration-options' => 'translations/settings/configuration-options',

                    // Static Translations Controller
                    'translations/static-translations' => 'translations/static-translations',
                    'translations/static-translations/export-file' => 'translations/static-translations/export-file',
                    'translations/static-translations/import' => 'translations/static-translations/import',

                    // Asset, Category, Global-set Controllers
                    'translations/assets/<elementId:\d+>/drafts/<draftId:\d+>' => 'translations/asset/edit-draft',
                    'translations/categories/<group>/<slug:{slug}>/drafts/<draftId:\d+>' => 'translations/category/edit-draft',
                    'translations/globals/<globalSetHandle:{handle}>/drafts/<draftId:\d+>' => 'translations/global-set/edit-draft',
                ]);
            }
        );
    }

    // Finish adding resources and bundler
    private function _includeResources($path)
    {
        $this->_includeUniversalResources();

        if (preg_match('#^entries(/|$)#', $path)) {
            $this->_includeEntryResources();

            if (isset(Craft::$app->getRequest()->getQueryParams()['draftId'])) {
                $this->_includeEditDraftResource(Craft::$app->getRequest()->getQueryParams()['draftId']);
            }
        }

        if (preg_match('#^categories(/|$)#', $path, $match)) {
            $this->_includeCategoryResources();
        }

        if (preg_match('#^globals/([^/]+)$#', $path, $match)) {
            $this->_includeGlobalSetResources($match[1]);
        }

        if (preg_match('#^globals/([^/]+)/([^/]+)$#', $path, $match)) {
            $this->_includeGlobalSetResources($match[2], $match[1]);
        }

        if (preg_match('#^assets(/|$)#', $path, $match)) {
            $this->_includeAssetResources(Craft::$app->getRequest()->getParam('sourceId'));
        }
    }

    private function _includeAssetResources($assetId)
    {
        $orders = array();

        foreach (self::$plugin->orderRepository->getDraftOrders() as $order) {
            $orders[] = array(
                'id' => $order->id,
                'title' => $order->title,
            );
        }

        $orders = json_encode($orders);

        self::$view->registerAssetBundle(Assets::class);

        self::$view->registerJs("$(function(){ Craft.Translations.AssetsTranslations.init({$orders}, {$assetId}); });");
    }

    private function _includeUniversalResources()
    {
        self::$view->registerAssetBundle(UniversalAssets::class);

        $numberOfCompleteOrders = count(self::$plugin->orderRepository->getCompleteOrders());
        self::$view->registerJs("$(function(){ Craft.Translations });");
        self::$view->registerJs("$(function(){ Craft.Translations.ShowCompleteOrdersIndicator.init({$numberOfCompleteOrders}); });");
    }

    private function _includeEditDraftResource($draftId)
    {
        $response = Translations::$plugin->draftRepository->isTranslationDraft($draftId);

        // If this is a translation draft, load the JS
        if (!empty($response)) {
            self::$view->registerAssetBundle(EditDraftAssets::class);

            $response = json_encode($response);

            self::$view->registerJs("$(function(){ Craft.Translations.ApplyTranslations.init({$draftId}, {$response}); });");
        }
    }

    private function _includeEntryResources()
    {
        $orders = array();
        $openOrders = array();

        foreach (self::$plugin->orderRepository->getDraftOrders() as $order) {
            $orders[] = array(
                'id' => $order->id,
                'title' => $order->title,
            );
        }

        foreach (self::$plugin->orderRepository->getOpenOrders() as $order) {
            $openOrders[] = array(
                'id' => $order->id,
                'sourceSite' => $order->sourceSite,
                'elements' => json_decode($order->elementIds, true),
            );
        }

        $data = [
            'orders' => $orders,
            'openOrders' => $openOrders,
            'sites' => Craft::$app->sites->getAllSiteIds(),
            'licenseStatus' => Craft::$app->plugins->getPluginLicenseKeyStatus(Constants::PLUGIN_HANDLE)
        ];
        $data = json_encode($data);

        self::$view->registerAssetBundle(EntryAssets::class);

        self::$view->registerJs("$(function(){ Craft.Translations.AddEntriesToTranslationOrder.init({$data}); });");
    }

    private function _includeCategoryResources($slug = null)
    {
        $orders = array();
        $categoryId = 0;
        if ($slug) {
            $categoryId = explode('-', $slug);
            $categoryId = (isset($categoryId[0])) ? $categoryId[0] : 0;
        }

        foreach (self::$plugin->orderRepository->getDraftOrders() as $order) {
            $orders[] = array(
                'id' => $order->id,
                'title' => $order->title,
            );
        }

        $orders = json_encode($orders);
        $categoryId = json_encode($categoryId);

        self::$view->registerAssetBundle(CategoryAssets::class);

        self::$view->registerJs("$(function(){ Craft.Translations.CategoryTranslations.init({$orders}, {$categoryId}); });");
    }

    private function _includeGlobalSetResources($globalSetHandle, $site = null)
    {
        $globalSet = self::$plugin->globalSetRepository->getSetByHandle($globalSetHandle, $site);
        $site = ($site && is_string($site)) ? Craft::$app->sites->getSiteByHandle($site)->id : Craft::$app->sites->getPrimarySite()->id;

        if (!$globalSet) {
            return;
        }

        $orders = array();

        foreach (self::$plugin->orderRepository->getDraftOrders() as $order) {
            if ($order->sourceSite === $globalSet->site->id) {
                $orders[] = array(
                    'id' => $order->id,
                    'title' => $order->title,
                );
            }
        }

        $drafts = array();

        foreach (self::$plugin->globalSetDraftRepository->getDraftsByGlobalSetId($globalSet->id, $site) as $draft) {
            $drafts[] = array(
                'url' => $draft->getCpEditUrl(),
                'name' => $draft->name,
            );
        }

        $orders = json_encode($orders);

        $globalSetId = json_encode($globalSet->id);

        $drafts = json_encode($drafts);

        self::$view->registerAssetBundle(GlobalSetAssets::class);

        self::$view->registerJs("$(function(){ Craft.Translations.GlobalSetEdit.init({$orders}, {$globalSetId}, {$drafts}); });");
    }

    private function _onSaveEntry(Event $event)
    {
        // @TODO check if entry is part of an in-progress translation order
        // and send notification to acclaro
    }

    private function _onBeforePublishDraft(Event $event)
    {
        $craft = Craft::$app;
        $request = $craft->getRequest();

        if (!$request->getIsConsoleRequest()) {
            $draft = $event->draft;

            $draftId = isset($draft['draftId']) ? $draft['draftId'] : '';

            $response = Translations::$plugin->draftRepository->isTranslationDraft($draftId);

            $action = $request->getActionSegments();
            $action = end($action);

            $applyDraftActions = [
                'apply-drafts',
                'save-draft-and-publish',
                'publish-draft',
                'run',
            ];

            if(!empty($response) && !in_array($action, $applyDraftActions)) {

                Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Unable to publish translation draft.'));
                $path = $craft->request->getFullPath();
                $params = $craft->request->getQueryParams();
                $craft->response->redirect(UrlHelper::siteUrl($path, $params))->send();
                $craft->end();
            }
        }
    }

    private function _onApplyDraft(Event $event)
    {
        // update acclaro order and files
        $draft = $event->draft;

        $currentFile = self::$plugin->fileRepository->getFileByDraftId($draft->draftId);

        if (!$currentFile) {
            return;
        }

        $order = self::$plugin->orderRepository->getOrderById($currentFile->orderId);

        if (!$order) {
            return;
        }

        $currentFile->status = Constants::FILE_STATUS_PUBLISHED;
        $currentFile->draftId = 0;

        self::$plugin->fileRepository->saveFile($currentFile);

        $order->status = Translations::$plugin->orderRepository->getNewStatus($order);

        Craft::$app->elements->saveElement($order);
    }

    private function _onDeleteElement(Event $event)
    {
		if (!empty($event->element->draftId)) {
			$response = self::$plugin->draftRepository->isTranslationDraft($event->element->draftId);
			if ($response) {

				$currentFile = self::$plugin->fileRepository->getFileByDraftId($event->element->draftId);

				if ($currentFile) {
					$order = self::$plugin->orderRepository->getOrderById($currentFile->orderId);

                    if ($order) {
						$order->logActivity(Translations::$plugin->translator->translate('app', 'Draft ' . $event->element->draftId . ' deleted.'));
						Translations::$plugin->orderRepository->saveOrder($order);
					}

					$currentFile->status = Constants::FILE_STATUS_CANCELED;

					self::$plugin->fileRepository->saveFile($currentFile);
                }
            }
        }

        $request = Craft::$app->getRequest();
        if (!$request->getIsConsoleRequest() && $request->getParam('hardDelete')) {
            $event->hardDelete = true;
        }

        if ($order = Translations::$plugin->orderRepository->isTranslationOrder($event->element->id) && $event->hardDelete) {
            $drafts = [];
            /** @var Order|null $order */
            foreach ($order->getFiles() as $file) {
                $drafts[] = $file->draftId;
            }

            if ($drafts) {
                Craft::$app->queue->push(new DeleteDrafts([
                    'description' => Constants::JOB_DELETING_DRAFT,
                    'drafts' => $drafts,
                ]));
            }
        }
    }

    private function _onDeleteSite(Event $event)
    {
        $siteId = $event->site->id;

        self::$plugin->translationRepository->deleteTranslationForSite($siteId);
    }

    /**
     * Install site event listeners for Control Panel requests only
     */
    protected function installCpEventListeners()
    {
        // Handler: UserPermissions::EVENT_REGISTER_PERMISSIONS
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                self::$plugin->logHelper->log(
                    '['. __METHOD__ .'] UserPermissions::EVENT_REGISTER_PERMISSIONS',
                    Constants::LOG_LEVEL_INFO
                );
                // Register our custom permissions
                $event->permissions[Craft::t('translations', 'Translations')] = $this->customAdminCpPermissions();
            }
        );
    }

    /**
     * Returns the custom Control Panel user permissions.
     *
     * @return array
     */
    protected function customAdminCpPermissions(): array
    {
        // The script meta containers for the global meta bundle

        return [
            'translations:dashboard' => [
                'label' => Craft::t('translations', 'View Dashboard'),
            ],
            'translations:translator' => [
                'label' => Craft::t('translations', 'View Translators'),
                'nested' => [
                    'translations:translator:create' => [
                        'label' => Craft::t('translations', 'Create Translators'),
                    ],
                    'translations:translator:edit' => [
                        'label' => Craft::t('translations', 'Edit Translators'),
                    ],
                    'translations:translator:delete' => [
                        'label' => Craft::t('translations', 'Delete Translators'),
                    ]
                ]
            ],
            'translations:static-translations' => [
                'label' => Craft::t('translations', 'Static Translations'),
                'nested' => [
                    'translations:static-translations:import' => [
                        'label' => Craft::t('translations', 'Import'),
                    ],
                    'translations:static-translations:export' => [
                        'label' => Craft::t('translations', 'Export'),
                    ]
                ]
            ],
            'translations:orders' => [
                'label' => Craft::t('translations', 'View Orders'),
                'nested' => [
                    'translations:orders:create' => [
                        'label' => Craft::t('translations', 'Create Orders'),
                    ],
                    'translations:orders:edit' => [
                        'label' => Craft::t('translations', 'Edit Orders'),
                    ],
                    'translations:orders:delete' => [
                        'label' => Craft::t('translations', 'Delete Orders'),
                    ],
                    'translations:orders:import' => [
                        'label' => Craft::t('translations', 'Import/Sync Orders'),
                    ],
                    'translations:orders:apply-translations' => [
                        'label' => Craft::t('translations', 'Apply Translations'),
                    ],
                    'translations:orders:draft:create' => [
                        'label' => Craft::t('translations', 'Create Order Draft'),
                    ],
                ]
            ],
            'translations:settings' => [
                'label' => Craft::t('translations', 'Access Settings'),
                'nested' => [
                    'translations:settings:clear-orders' => [
                        'label' => Craft::t('translations', 'Clear Orders'),
                    ],
                ]
            ]
        ];
    }
}
