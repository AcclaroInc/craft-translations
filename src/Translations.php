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
use craft\db\Table;
use yii\base\Event;
use craft\base\Plugin;
use craft\web\UrlManager;
use craft\elements\Entry;
use craft\services\Plugins;
use craft\events\ModelEvent;
use craft\events\DraftEvent;
use craft\services\Elements;
use craft\events\PluginEvent;
use craft\events\ElementEvent;
use craft\services\Drafts;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\DeleteElementEvent;
use acclaro\translations\services\App;
use acclaro\translations\base\PluginTrait;
use craft\console\Application as ConsoleApplication;
use acclaro\translations\assetbundles\EntryAssets;
use acclaro\translations\assetbundles\UniversalAssets;
use acclaro\translations\assetbundles\EditDraftAssets;
use acclaro\translations\assetbundles\GlobalSetAssets;
use acclaro\translations\services\job\DeleteDrafts;

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
     * @var string
     */
    public $schemaVersion = '1.2.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->setComponents([
            'app' => App::class
        ]);

        self::$plugin = $this->get('app');
        self::$view = Craft::$app->getView();

        $this->installEventListeners();

        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'acclaro\translations\console\controllers';
        }

        Event::on(
            Drafts::class,
            Drafts::EVENT_BEFORE_APPLY_DRAFT,
            function (DraftEvent $event) {
                // Craft::debug(
                //     'Drafts::EVENT_BEFORE_APPLY_DRAFT',
                //     __METHOD__
                // );
                Craft::info(
                    Craft::t(
                        'translations',
                        '{name} Drafts::EVENT_BEFORE_APPLY_DRAFT',
                        ['name' => $this->name]
                    ),
                    __METHOD__
                );

                $this->_onSaveEntry($event);
            }
        );
        
        Event::on(
            Drafts::class,
            Drafts::EVENT_AFTER_APPLY_DRAFT,
            function (DraftEvent $event) {
                Craft::debug(
                    'Drafts::EVENT_AFTER_APPLY_DRAFT',
                    __METHOD__
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
                Craft::debug(
                    'Elements::EVENT_AFTER_SAVE_ELEMENT',
                    __METHOD__
                );
                
                $this->_onSaveEntry($event);
            }
        );

        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_DELETE_ELEMENT,
            function (DeleteElementEvent $event) {
                Craft::debug(
                    'Elements::EVENT_BEFORE_DELETE_ELEMENT',
                    __METHOD__
                );

                $this->_onDeleteElement($event);
            }
        );

        /**
         * EVENT_AFTER_DELETE_DRAFT gets triggered after EVENT_AFTER_APPLY_DRAFT
         * May need to find another solution to the entry draft deletion
         */
        // Event::on(
        //     Drafts::class,
        //     Drafts::EVENT_AFTER_DELETE_DRAFT,
        //     function (DraftEvent $event) {
        //         Craft::debug(
        //             'Drafts::EVENT_AFTER_DELETE_DRAFT',
        //             __METHOD__
        //         );
        //         if ($event->draft) {
        //             $this->_onDeleteDraft($event);
        //         }
        //     }
        // );

        /**
         * Maybe we can do a plugin walkthrough here?
         */
        // Event::on(
        //     Plugins::class,
        //     Plugins::EVENT_AFTER_INSTALL_PLUGIN,
        //     function (PluginEvent $event) {
        //         Craft::debug(
        //             'Plugins::EVENT_AFTER_INSTALL_PLUGIN',
        //             __METHOD__
        //         );
        //         if ($event->plugin === $this) {
        //         }
        //     }
        // );

        Craft::info(
            Craft::t(
                'translations',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    /**
     * @inheritdoc
     */
    public function uninstall()
    {
        // Let's clean up the drafts table
        $files = self::$plugin->fileRepository->getFiles();
        $drafts = array_column($files, 'draftId');

        if ($drafts) {
            Craft::$app->queue->push(new DeleteDrafts([
                'description' => 'Deleting Translation Drafts',
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
        
        $subNavs['dashboard'] = [
            'label' => 'Dashboard',
            'url' => 'translations',
        ];
        $subNavs['orders'] = [
            'label' => 'Orders',
            'url' => 'translations/orders',
        ];
        $subNavs['translators'] = [
            'label' => 'Translators',
            'url' => 'translations/translators',
        ];
        $subNavs['about'] = [
            'label' => 'About',
            'url' => 'translations/about',
        ];

        $navItem = array_merge($navItem, [
            'subnav' => $subNavs,
        ]);
        return $navItem;
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
                $this->_includeResouces(Craft::$app->getRequest()->getPathInfo());
            }
            
            self::$plugin->translationRepository->loadTranslations();

            Event::on(
                Elements::class,
                Elements::EVENT_REGISTER_ELEMENT_TYPES,
                function (RegisterComponentTypesEvent $event) {
                    $event->types[] = Order::class;
                }
            );
        }
    }

    private function _registerCpRoutes()
    {
        Event::on(
            UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function (RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, [
                    'translations' => 'translations/widget/index',
                    'translations/orders' => 'translations/base/order-index',
                    'translations/orders/new' => 'translations/base/order-detail',
                    'translations/orders/detail/<orderId:\d+>' => 'translations/base/order-detail',
                    // 'translations/orders/reporting' => 'translations/base/index',
                    'translations/translators' => 'translations/base/translator-index',
                    'translations/translators/new' => 'translations/base/translator-detail',
                    'translations/translators/detail/<translatorId:\d+>' => 'translations/base/translator-detail',
                    'translations/about' => 'translations/base/about-index',
                    'translations/globals/<globalSetHandle:{handle}>/drafts/<draftId:\d+>' => 'translations/base/edit-global-set-draft',
                    
                    'translations/orders/exportfile' => 'translations/files/export-file',
                    'translations/orders/importfile' => 'translations/files/import-file',
                ]);
            }
        );
    }

    // Finish adding resources and bundler
    private function _includeResouces($path)
    {
        $this->_includeUniversalResources();

        if (preg_match('#^entries(/|$)#', $path)) {
            $this->_includeEntryResources();

            if (isset(Craft::$app->getRequest()->getQueryParams()['draftId'])) {
                $this->_includeEditDraftResource(Craft::$app->getRequest()->getQueryParams()['draftId']);
            }
        }

        if (preg_match('#^globals/([^/]+)$#', $path, $match)) {
            $this->_includeGlobalSetResources($match[1]);
        }

        if (preg_match('#^globals/([^/]+)/([^/]+)$#', $path, $match)) {
            $this->_includeGlobalSetResources($match[2], $match[1]);
        }
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
        self::$view->registerAssetBundle(EditDraftAssets::class);

        self::$view->registerJs("$(function(){ Craft.Translations.ApplyTranslations.init({$draftId}); });");
    }
    
    private function _includeEntryResources()
    {
        $orders = array();

        foreach (self::$plugin->orderRepository->getDraftOrders() as $order) {
            $orders[] = array(
                'id' => $order->id,
                'title' => $order->title,
            );
        }

        $data = [
            'orders' => $orders,
            'sites' => Craft::$app->sites->getAllSiteIds(),
            'licenseStatus' => Craft::$app->plugins->getPluginLicenseKeyStatus('translations')
        ];
        $data = json_encode($data);
        
        self::$view->registerAssetBundle(EntryAssets::class);
        
        self::$view->registerJs("$(function(){ Craft.Translations.AddEntriesToTranslationOrder.init({$data}); });");
    }
    
    private function _includeGlobalSetResources($globalSetHandle, $site = null)
    {
        $globalSet = self::$plugin->globalSetRepository->getSetByHandle($globalSetHandle, $site);

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

    private function _onApplyDraft(Event $event)
    {
        // update acclaro order and files
        $draft = $event->draft;

        $currentFile = self::$plugin->fileRepository->getFileByDraftId($draft->draftId);

        if (!$currentFile) {
            return;
        }

        $order = self::$plugin->orderRepository->getOrderById($currentFile->orderId);

        $currentFile->status = 'published';

        self::$plugin->fileRepository->saveFile($currentFile);

        $areAllFilesPublished = true;

        foreach ($order->files as $file) {
            if ($file->status !== 'published') {
                $areAllFilesPublished = false;
                break;
            }
        }

        if ($areAllFilesPublished) {
            $order->status = 'published';

            Craft::$app->elements->saveElement($order);
        }
    }

    private function _onDeleteDraft(Event $event)
    {

        $draft = $event->draft;

        return self::$plugin->fileRepository->delete($draft->draftId);
    }

    private function _onDeleteElement(Event $event)
    {

        if (Craft::$app->getRequest()->getParam('hardDelete')) {
            $event->hardDelete = true;
        }
    }
}
