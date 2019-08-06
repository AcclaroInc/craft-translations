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
use DateTime;
use Exception;
use craft\web\Controller;
use craft\elements\Entry;
use yii\web\HttpException;
use craft\helpers\UrlHelper;
use craft\elements\GlobalSet;
use acclaro\translations\services\App;
use acclaro\translations\Translations;
use acclaro\translations\services\job\SyncOrder;
use acclaro\translations\services\job\SyncOrders;
use acclaro\translations\services\job\CreateDrafts;
use acclaro\translations\services\job\UpdateEntries;
use acclaro\translations\services\translator\AcclaroTranslationService;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class BaseController extends Controller
{
    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     * @access protected
     */
    // protected $allowAnonymous = ['actionOrderCallback', 'actionFileCallback'];
    protected $allowAnonymous = true;
    
    /**
     * @var array
     */
    protected $adminTabs;

    /**
     * @var int
     */
    protected $pluginVersion;

    // Public Methods
    // =========================================================================
    
    public function __construct(
        $id,
        $module = null
    ) {
        parent::__construct($id, $module);

        $this->adminTabs = array(
            'dashboard' => array(
                'label' => Translations::$plugin->translator->translate('app', 'Dashboard'),
                'url' => Translations::$plugin->urlGenerator->generateCpUrl('translations'),
            ),
            'orders' => array(
                'label' => Translations::$plugin->translator->translate('app', 'Orders'),
                'url' => Translations::$plugin->urlGenerator->generateCpUrl('translations/orders'),
            ),
            'translators' => array(
                'label' => Translations::$plugin->translator->translate('app', 'Translators'),
                'url' => Translations::$plugin->urlGenerator->generateCpUrl('translations/translators'),
            ),
            'about' => array(
                'label' => Translations::$plugin->translator->translate('app', 'About'),
                'url' => Translations::$plugin->urlGenerator->generateCpUrl('translations/about'),
            ),
        );
        
        $this->pluginVersion = Craft::$app->getPlugins()->getPlugin('translations')->getVersion();
    }

    // Callback & Request Methods
    // =========================================================================
    

    public function actionOrderCallback()
    {
        $this->logIncomingRequest('orderCallback');

        Craft::$app->getResponse()->headers->set('Content-Type', 'text/plain');

        $key = sha1_file(Craft::$app->path->getConfigPath().'/license.key');

        if (Craft::$app->request->getRequiredQueryParam('key') !== $key) {
            echo $key.PHP_EOL;
            Craft::$app->end('Invalid key');
        } else {
            echo $key.PHP_EOL;
            echo 'Valid key'.PHP_EOL;
        }

        $orderId = Craft::$app->request->getRequiredQueryParam('orderId');

        if (!$orderId) {
            Craft::$app->end('Missing order ID');
        }

        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        if (!$order) {
            Craft::$app->end('Invalid orderId');
        } else {
            echo 'Order found'.PHP_EOL;
        }

        // don't process published orders
        if ($order->status === 'published') {
            Craft::$app->end('Order already published');
        }

        $translator = $order->getTranslator();

        $translationService = Translations::$plugin->translatorFactory->makeTranslationService($translator->service, $translator->getSettings());

        if (!$translationService) {
            Craft::$app->end('Couldn’t find the translation service');
        } else {
            echo 'Translation service found'.PHP_EOL;
        }

        $translationService->updateOrder($order);

        echo 'Updating order'.PHP_EOL;

        $success = Craft::$app->getElements()->saveElement($order);

        if (!$success) {
            Craft::$app->end('Couldn’t save the order');
        } else {
            echo 'Saving order'.PHP_EOL;
        }

        Craft::$app->end('OK');
    }

    public function actionFileCallback()
    {
        $this->logIncomingRequest('fileCallback');

        Craft::$app->getResponse()->headers->set('Content-Type', 'text/plain');

        $key = sha1_file(Craft::$app->path->getConfigPath().'/license.key');

        if (Craft::$app->request->getQueryParam('key') !== $key) {
            Craft::$app->end('Invalid key');
        } else {
            echo 'Valid key'.PHP_EOL;
        }

        $fileId = Craft::$app->request->getRequiredQueryParam('fileId');

        $file = Translations::$plugin->fileRepository->getFileById($fileId);

        if (!$file) {
            Craft::$app->end('Couldn’t find the file');
        } else {
            echo 'Found file'.PHP_EOL;
        }


        // don't process published files
        if ($file->status === 'published') {
            Craft::$app->end('File already published');
        }

        $order = Translations::$plugin->orderRepository->getOrderById($file->orderId);

        if (!$order) {
            Craft::$app->end('Couldn’t find the order');
        } else {
            echo 'Found order'.PHP_EOL;
        }

        $translator = $order->getTranslator();

        $translationService = Translations::$plugin->translatorFactory->makeTranslationService($translator->service, $translator->getSettings());

        if (!$translationService) {
            Craft::$app->end('Couldn’t find the translation service');
        } else {
            echo 'Translation service found'.PHP_EOL;
        }

        $translationService->updateFile($order, $file);

        echo 'Updating file'.PHP_EOL;

        
        $success = Translations::$plugin->fileRepository->saveFile($file);

        if (!$success) {
            Craft::$app->end('Couldn’t save the file');
        } else {
            echo 'Saving file'.PHP_EOL;
        }

        Craft::$app->end('OK');
    }

    public function actionAuthenticateTranslationService()
    {
        $this->requireLogin();
        $this->requireAdmin();
        $this->requirePostRequest();

        $service = Craft::$app->getRequest()->getRequiredParam('service');
        $settings = Craft::$app->getRequest()->getRequiredParam('settings');

        $translator = Translations::$plugin->translatorRepository->makeNewTranslator();
        $translator->service = $service;
        $translator->settings = json_encode($settings);
        
        $translationService = Translations::$plugin->translatorFactory->makeTranslationService($service, $settings);

        return $this->asJson(array(
            'success' => $translationService->authenticate($settings),
        ));
    }

    public function logIncomingRequest($endpoint)
    {
        $headers = Craft::$app->response->getHeaders();

        $request = sprintf(
            "%s %s %s\n",
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            $_SERVER['SERVER_PROTOCOL']
        );

        foreach ($headers as $key => $value) {
            $request .= "{$key}: {$value[0]}\n";
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $request .= "\n".http_build_query($_POST);
        }

        $tempPath = Craft::$app->getPath()->getTempPath().'/translations';

        if (!is_dir($tempPath)) {
            mkdir($tempPath);
        }

        $filename = 'request-'.$endpoint.'-'.date('YmdHis').'.txt';

        $filePath = $tempPath.'/'.$filename;

        $handle = fopen($filePath, 'w+');

        fwrite($handle, $request);

        fclose($handle);
    }

    public function actionAddElementsToOrder()
    {
        $this->requireLogin();
        $this->requireAdmin();
        $this->requirePostRequest();

        $orderId = Craft::$app->getRequest()->getParam('id');
        
        $sourceSite = Craft::$app->getRequest()->getParam('sourceSite');

        
        $order = Translations::$plugin->orderRepository->getOrderById($orderId);
        
        if (!$order) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Invalid Order'));
            return;
        }
        
        if (!Translations::$plugin->siteRepository->isSiteSupported($sourceSite)) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Source site is not supported'));
            return;
        }

        if ((int) $order->sourceSite !== (int) $sourceSite) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'All entries within an order must have the same source site.'));
            return;
        }

        $elements = $order->getElements();

        $elementIds = array();

        foreach ($elements as $element) {
            $elementIds[] = $element->id;
        }

        if (is_array(Craft::$app->getRequest()->getParam('elements'))) {
            foreach (Craft::$app->getRequest()->getParam('elements') as $elementId) {
                if (!in_array($elementId, $elementIds)) {
                    $elementIds[] = $elementId;

                    $element = Craft::$app->getElements()->getElementById($elementId, null, $order->siteId);

                    if ($element instanceof Entry) {
                        $sites = array();

                        $elementSection = Craft::$app->getSections()->getSectionById($element->sectionId);
                        foreach ($elementSection->getSiteIds() as $key => $site) {
                            $sites[] = $site;
                        }

                        $hasTargetSites = !array_diff(json_decode($order->targetSites), $sites);

                        if (!$hasTargetSites) {
                            $message = sprintf(
                                Translations::$plugin->translator->translate('app', "The target site(s) on this order are not available for the entry “%s”. Please check your settings in in Sections > %s."),
                                $element->title,
                                $element->section->name
                            );
        
                            Craft::$app->getSession()->setError($message);
                            return;
                        }
                    }

                    $elements[] = $element;
                }
            }
        }

        $wordCount = 0;

        foreach ($elements as $element) {
            $wordCount += Translations::$plugin->elementTranslator->getWordCount($element);
        }

        $order->entriesCount = count($elements);

        $order->wordCount = $wordCount;

        $order->elementIds = json_encode($elementIds);

        $success = Craft::$app->getElements()->saveElement($order);
        if (!$success) {
            Craft::error('Couldn’t save the order', __METHOD__);
        }

        Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Added to order.'));

        $this->redirect('translations/orders/detail/'. $order->id, 302, true);
    }

    // Index Page Methods
    // =========================================================================

    /**
     * @return mixed
     */
    public function actionOrderIndex()
    {
        $variables = array();

        $variables['adminTabs'] = $this->adminTabs;

        $variables['pluginVersion'] = $this->pluginVersion;
        
        $variables['searchParams'] = Translations::$plugin->orderSearchParams->getParams();

        $variables['translators'] = Translations::$plugin->translatorRepository->getActiveTranslators();

        $variables['selectedSubnavItem'] = 'orders';

        $this->renderTemplate('translations/orders/_index', $variables);
    }
    
    /**
     * @return mixed
     */
    public function actionTranslatorIndex()
    {
        $variables = array();

        $variables['adminTabs'] = $this->adminTabs;

        $variables['pluginVersion'] = $this->pluginVersion;

        $variables['translators'] = Translations::$plugin->translatorRepository->getTranslators();

        $variables['translatorTargetSites'] = array();
        foreach ($variables['translators'] as $key => $translator) {
            foreach (json_decode($translator->sites) as $key => $site) {
                $variables['translatorTargetSites'][$site] = Craft::$app->getSites()->getSiteById($site);
            }
        }

        $variables['selectedSubnavItem'] = 'translators';
        
        $this->renderTemplate('translations/translators/_index', $variables);
    }

    /**
     * @return mixed
     */
    public function actionAboutIndex()
    {
        $variables = array();

        $variables['adminTabs'] = $this->adminTabs;

        $variables['pluginVersion'] = $this->pluginVersion;

        $variables['translators'] = Translations::$plugin->translatorRepository->getTranslators();

        $variables['selectedSubnavItem'] = 'about';
        
        $this->renderTemplate('translations/_about', $variables);
    }

    // Detail Page Methods
    // =========================================================================

    public function actionOrderDetail(array $variables = array())
    {
        $variables = Craft::$app->getRequest()->resolve()[1];

        $variables['adminTabs'] = $this->adminTabs;

        $variables['pluginVersion'] = $this->pluginVersion;

        $variables['orderId'] = isset($variables['orderId']) ? $variables['orderId'] : null;

        $variables['inputSourceSite'] = Craft::$app->getRequest()->getQueryParam('sourceSite');

        if (empty($variables['inputSourceSite'])) {
            $variables['inputSourceSite'] = Craft::$app->getRequest()->getParam('sourceSite');
        }
        
        $variables['translatorId'] = isset($variables['order']) ? $variables['order']['translatorId'] : null;

        $variables['selectedSubnavItem'] = 'orders';
        
        $variables['licenseStatus'] = Craft::$app->plugins->getPluginLicenseKeyStatus('translations');
        
        if ($variables['inputSourceSite'] && ! Translations::$plugin->siteRepository->isSiteSupported($variables['inputSourceSite'])) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Source site is not supported'));
            return;
        }

        if ($variables['orderId']) {
            $variables['order'] = Translations::$plugin->orderRepository->getOrderById($variables['orderId']);

            $variables['inputElements'] = [];

            if (!$variables['order']) {
                throw new HttpException(404);
            }
           
        } else {
            $variables['order'] = Translations::$plugin->orderRepository->makeNewOrder($variables['inputSourceSite']);

            $variables['inputElements'] = Craft::$app->getRequest()->getQueryParam('elements');

            if (empty($variables['inputElements'])) {
                $variables['inputElements'] = Craft::$app->getRequest()->getParam('elements');
            }
        }
        
        $variables['sourceSiteObject'] = Craft::$app->getSites()->getSiteById($variables['order']['sourceSite']);
        
        if ($variables['order']->targetSites) {
            $variables['orderTargetSitesObject'] = array();
            foreach (json_decode($variables['order']->targetSites) as $key => $site) {
                $variables['orderTargetSitesObject'][] = Craft::$app->getSites()->getSiteById($site);
            }
        }

        $variables['orientation'] = Craft::$app->getLocale()->orientation;

        $variables['translatorOptions'] = Translations::$plugin->translatorRepository->getTranslatorOptions();

        $variables['elements'] = $variables['order']->getElements();

        if ($variables['inputElements']) {
            foreach ($variables['inputElements'] as $elementId) {
                $element = Craft::$app->getElements()->getElementById($elementId, null, $variables['order']->sourceSite);

                if ($element) {
                    $variables['elements'][] = $element;
                }
            }
        }

        $variables['orderEntriesCount'] = count($variables['elements']);

        $variables['orderWordCount'] = 0;

        $variables['elementWordCounts'] = array();

        $variables['entriesCountBySection'] = array();

        foreach ($variables['elements'] as $element) {
            $wordCount = Translations::$plugin->elementTranslator->getWordCount($element);

            $variables['elementWordCounts'][$element->id] = $wordCount;

            $variables['orderWordCount'] += $wordCount;

            if ($element instanceof GlobalSet) {
                $sectionName = 'Globals';
            } else {
                $sectionName = $element->section->name;
            }
        

            if (!isset($variables['entriesCountBySection'][$sectionName])) {
                $variables['entriesCountBySection'][$sectionName] = 0;
            }

            $variables['entriesCountBySection'][$sectionName]++;

            //Is an order being created or are we on the detail page?
            if (!isset($variables['inputSourceSite']))
            {
                $variables['entriesCountByElementCompleted'] = 0;
                $variables['entriesCountByElement'] = 0;

                foreach ($variables['elements'] as $element) 
                {
                    $variables['files'][$element->id] = Translations::$plugin->fileRepository->getFilesByOrderId($variables['orderId'], $element->id);

                    $variables['entriesCountByElement'] += count($variables['files'][$element->id]);
 
                    $isElementPublished = true;

                    // TODO: Improve this for performance
                    foreach ($variables['files'][$element->id] as $file) {
                        if ($file->status !== 'published'){
                            $isElementPublished = false;
                        }

                        if ($element instanceof Entry) {
                            if ($file->status === 'published') {
                                $translatedElement = Craft::$app->getElements()->getElementById($element->id, null, $file->targetSite);

                                $variables['webUrls'][$file->id] = $translatedElement ? $translatedElement->url : $element->url;
                            } else {
                                $variables['webUrls'][$file->id] = $file->previewUrl;
                            }

                            if($file->status === 'complete' || $file->status === 'published')
                            {
                                $variables['entriesCountByElementCompleted']++;
                            }
                        } elseif ($element instanceof GlobalSet) {
                            if($file->status === 'complete' || $file->status === 'published')
                            {
                                $variables['entriesCountByElementCompleted']++;
                            }
                        }

                        $variables['fileTargetSites'][$file->targetSite] = Craft::$app->getSites()->getSiteById($file->targetSite);
                        $variables['fileUrls'][$file->id] = Translations::$plugin->urlGenerator->generateFileUrl($element, $file);
                    }
                    $variables['isElementPublished'][$element->id] = $isElementPublished;
                }
                $variables['entriesCountByElement'] -=  $variables['entriesCountByElementCompleted'];
            }
        }

        if (!$variables['translatorOptions']) {
            $variables['translatorOptions'] = array('' => Translations::$plugin->translator->translate('app', 'No Translators'));
        }
        
        $user = Craft::$app->getUser();

        $variables['owners'] = array(
            $user->id => $user->getRememberedUsername(),
        );

        $variables['sites'] = Craft::$app->getSites()->getAllSiteIds();
        
        $targetSites = Craft::$app->getSites()->getAllSiteIds();

        // This removes same source as option
        if (($key = array_search($variables['inputSourceSite'], $targetSites)) !== false) {
            unset($targetSites[$key]);
        }
        if (($key = array_search($variables['order']['sourceSite'], $targetSites)) !== false) {
            unset($targetSites[$key]);
        }
        
        $variables['targetSites'] = array();
        foreach ($targetSites as $key => $site) {
            $variables['targetSites'][] = Craft::$app->getSites()->getSiteById($site);
        }

        $variables['translator'] = null;

        $variables['translatorId'] = !is_null($variables['order']->translator) ? $variables['order']->translator->id : null;
        if ($variables['translatorId'])
        {
            $variables['translator'] = Translations::$plugin->translatorRepository->getTranslatorById($variables['translatorId']);
        }

        $variables['targetSiteCheckboxOptions'] = array();

        foreach ($targetSites as $key => $site) {
            $site = Craft::$app->getSites()->getSiteById($site);
            $variables['targetSiteCheckboxOptions'][] = array(
                'value' => $site->id,
                'label' => $site->name. '<span class="light"> ('. $site->language. ')</span>'
            );
        }

        if (!is_null($variables['translator']) && $variables['translator']->service !== 'export_import')
        {
            $translationService = Translations::$plugin->translatorFactory->makeTranslationService($variables['translator']->service, json_decode($variables['translator']->settings, true));

            $translatorUrl = $translationService->getOrderUrl($variables['order']);
            $variables['translator_url'] = $translatorUrl;
        }

        $variables['isSubmitted'] = ($variables['order']->status !== 'new' && $variables['order']->status !== 'failed');

        $this->renderTemplate('translations/orders/_detail', $variables);
    }

    /**
     * @return mixed
     */
    public function actionTranslatorDetail(array $variables = array())
    {   
        $variables = Craft::$app->getRequest()->resolve()[1];
        
        $variables['adminTabs'] = $this->adminTabs;

        $variables['pluginVersion'] = $this->pluginVersion;

        $variables['translatorId'] = isset($variables['translatorId']) ? $variables['translatorId'] : null;

        $variables['selectedSubnavItem'] = 'translators';

        if ($variables['translatorId']) {
            $variables['translator'] = Translations::$plugin->translatorRepository->getTranslatorById($variables['translatorId']);

            if (!$variables['translator']) {
                throw new HttpException(404);
            }
        } else {
            $variables['translator'] = Translations::$plugin->translatorRepository->makeNewTranslator();
        }

        $variables['orientation'] = Craft::$app->getLocale()->getOrientation();

        $variables['sites'] = Craft::$app->getSites()->getAllSiteIds();
        
        $variables['targetSiteCheckboxOptions'] = array();

        foreach ($variables['sites'] as $key => $site) {
            $site = Craft::$app->getSites()->getSiteById($site);
            $variables['targetSiteCheckboxOptions'][] = array(
                'value' => $site->id,
                'label' => $site->name. '<span class="light"> ('. $site->language. ')</span>'
            );
        }

        $variables['translationServices'] = Translations::$plugin->translatorRepository->getTranslationServices();

        $this->renderTemplate('translations/translators/_detail', $variables);
    }

    public function actionOrderEntries(array $variables = array())
    {
        $variables = Craft::$app->getRequest()->resolve()[1];

        $variables['adminTabs'] = $this->adminTabs;

        $variables['pluginVersion'] = $this->pluginVersion;

        $variables['order'] = Translations::$plugin->orderRepository->getOrderById($variables['orderId']);

        $variables['selectedSubnavItem'] = 'orders';

        if (!$variables['order']) {
            throw new HttpException(404);
        }

        $variables['elements'] = $variables['order']->getElements();

        $variables['files'] = array();

        $variables['fileUrls'] = array();

        $variables['webUrls'] = array();

        $variables['isElementPublished'] = array();

        $variables['sourceSiteObject'] = Craft::$app->getSites()->getSiteById($variables['order']['sourceSite']);

        foreach ($variables['elements'] as $element) {
            $variables['files'][$element->id] = Translations::$plugin->fileRepository->getFilesByOrderId($variables['orderId'], $element->id);

            $isElementPublished = true;

            foreach ($variables['files'][$element->id] as $file) {
                if ($file->status !== 'published') {
                    $isElementPublished = false;
                }

                if ($element instanceof Entry) {
                    if ($file->status === 'published') {
                        $translatedElement = Craft::$app->getElements()->getElementById($element->id, null, $file->targetSite);

                        $variables['webUrls'][$file->id] = $translatedElement ? $translatedElement->url : $element->url;
                    } else {
                        $variables['webUrls'][$file->id] = $file->previewUrl;
                    }
                }
                $variables['fileTargetSites'][$file->targetSite] = Craft::$app->getSites()->getSiteById($file->targetSite);

                $variables['fileUrls'][$file->id] = Translations::$plugin->urlGenerator->generateFileUrl($element, $file);
            }

            $variables['isElementPublished'][$element->id] = $isElementPublished;
        }

        $this->renderTemplate('translations/orders/_entries', $variables);
    }

    public function actionUpdateEntries()
    {
        $orderId = Craft::$app->getRequest()->getParam('orderId');

        $elementIds = Craft::$app->getRequest()->getParam('elements');

        Craft::$app->queue->push(new UpdateEntries([
            'description' => 'Updating translation entries',
            'orderId' => $orderId,
            'elementIds' => $elementIds
        ]));

        // Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Entries published.'));

        $this->redirect('translations/orders/detail/'.$orderId, 302, true);
    }

    // Translator CRUD Methods
    // =========================================================================

    public function actionDeleteTranslator()
    {
        $this->requireLogin();
        $this->requireAdmin();
        $this->requirePostRequest();

        $translatorId = Craft::$app->getRequest()->getBodyParam('translatorId');

        $translator = Translations::$plugin->translatorRepository->getTranslatorById($translatorId);

        if (!$translator) {
            throw new Exception('Invalid Translator');
        }

        // check if translator has any pending orders
        $pendingOrders = Translations::$plugin->orderRepository->getInProgressOrdersByTranslatorId($translatorId);

        $pendingOrdersCount = count($pendingOrders);

        if ($pendingOrdersCount > 0) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'This translator cannot be deleted as orders has been created already.'));

            return;
        }

        Translations::$plugin->translatorRepository->deleteTranslator($translator);

        Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Translator deleted.'));

        return $this->redirect('translations/translators', 302, true);
    }

    public function actionSaveTranslator()
    {
        $this->requireLogin();
        $this->requireAdmin();
        $this->requirePostRequest();

        $translatorId = Craft::$app->getRequest()->getBodyParam('id');

        if ($translatorId) {
            $translator = Translations::$plugin->translatorRepository->getTranslatorById($translatorId);

            if (!$translator) {
                // throw new HttpException(400, 'Invalid Translator');
                Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Invalid Translator'));
                return;
            }
        } else {
            $translator = Translations::$plugin->translatorRepository->makeNewTranslator();
        }

        $sites = Craft::$app->getRequest()->getBodyParam('sites');

        if ($sites === '*') {
            $sites = Craft::$app->getSites()->getAllSiteIds();
        }

        $service = Craft::$app->getRequest()->getBodyParam('service');
        
        $allSettings = Craft::$app->getRequest()->getBodyParam('settings');

        $settings = isset($allSettings[$service]) ? $allSettings[$service] : array();

        $translator->label = Craft::$app->getRequest()->getBodyParam('label');
        $translator->service = $service;
        $translator->sites = $sites ? json_encode($sites) : null;
        $translator->settings = json_encode($settings);
        $translator->status = Craft::$app->getRequest()->getBodyParam('status');

        //Make Export/Import Translator automatically active
        if ($translator->service === 'export_import')
        {
            $translator->status = 'active';
        }

        Translations::$plugin->translatorRepository->saveTranslator($translator);

        Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Translator saved.'));

        $this->redirect('translations/translators', 302, true);
    }

    // Order CRUD Methods
    // =========================================================================

    public function actionSaveOrder()
    {
        $this->requireLogin();
        $this->requireAdmin();
        $this->requirePostRequest();

        $orderId = Craft::$app->getRequest()->getParam('id');

        if ($orderId) {
            $order = Translations::$plugin->orderRepository->getOrderById($orderId);

            if (!$order) {
                throw new HttpException(400, Translations::$plugin->translator->translate('app', 'Invalid Order'));
            }
        } else {
            $sourceSite = Craft::$app->getRequest()->getParam('sourceSite');

            if ($sourceSite && !Translations::$plugin->siteRepository->isSiteSupported($sourceSite)) {
                throw new HttpException(400, Translations::$plugin->translator->translate('app', 'Source site is not supported'));
            }

            $order = Translations::$plugin->orderRepository->makeNewOrder($sourceSite);

            $order->logActivity(Translations::$plugin->translator->translate('app', 'Order Created'));
        }

        try {

            $targetSites = Craft::$app->getRequest()->getParam('targetSites');

            if ($targetSites === '*') {
                $targetSites = Craft::$app->getSites()->getAllSiteIds();
            }

            $requestedDueDate = Craft::$app->getRequest()->getParam('requestedDueDate');

            $translatorId = Craft::$app->getRequest()->getParam('translatorId');

            $title = Craft::$app->getRequest()->getParam('title');

            if (!$title) {
                $title = sprintf(
                    'Translation Order #%s',
                    Translations::$plugin->orderRepository->getOrdersCount() + 1
                );
            }

            $order->ownerId = Craft::$app->getRequest()->getParam('ownerId');
            $order->title = $title;
            $order->targetSites = $targetSites ? json_encode($targetSites) : null;

            if ($requestedDueDate) {
                if (!is_array($requestedDueDate)) {
                    $requestedDueDate = DateTime::createFromFormat('n/j/Y', $requestedDueDate);
                } else {
                    $requestedDueDate = DateTime::createFromFormat('n/j/Y', $requestedDueDate['date']);
                }
            }
            $order->requestedDueDate = $requestedDueDate ? $requestedDueDate : null;

            $order->comments = Craft::$app->getRequest()->getParam('comments');
            $order->translatorId = $translatorId;

            $elementIds = Craft::$app->getRequest()->getParam('elements') ? Craft::$app->getRequest()->getParam('elements') : array();

            $order->elementIds = json_encode($elementIds);

            $entriesCount = 0;
            $wordCounts = array();

            foreach ($order->getElements() as $element) {
                $entriesCount++;

                $wordCounts[$element->id] = Translations::$plugin->elementTranslator->getWordCount($element);

                if ($element instanceof Entry) {
                    $supportedSites = array();

                    foreach ($element->getSupportedSites() as $supportedSite) {
                        $supportedSites[] = $supportedSite['siteId'];
                    }

                    $hasTargetSites = !array_diff($targetSites, $supportedSites);

                    if (!$hasTargetSites) {
                        $message = sprintf(
                            Translations::$plugin->translator->translate('app', "The target site(s) selected are not available for the entry “%s”. Please check your settings in Settings > Sections > %s to change this entry's target sites."),
                            $element->title,
                            $element->section->name
                        );

                        Craft::$app->getSession()->setError($message);
                        return;
                    }
                }
            }

            $order->entriesCount = $entriesCount;
            $order->wordCount = array_sum($wordCounts);

            // Manual Translation will make orders 'in progress' status after creation

            $success = Craft::$app->getElements()->saveElement($order);
            if (!$success) {
                Craft::error('Couldn’t save the order', __METHOD__);
            } else {
                if (Craft::$app->getRequest()->getParam('submit')) {

                    // Check supported languages
                    if ($order->getTranslator()->service !== 'export_import') {
                        $translationService = Translations::$plugin->translatorFactory->makeTranslationService($order->getTranslator()->service, $order->getTranslator()->getSettings());

                        if ($translationService->getLanguages()) {
                            $sourceLanguage = Translations::$plugin->siteRepository->normalizeLanguage(Craft::$app->getSites()->getSiteById($order->sourceSite)->language);
                            $unsupported = false;
                            $unsupportedLangs = [];
                            $supportedLanguagePairs = [];

                            foreach ($translationService->getLanguagePairs($sourceLanguage) as $key => $languagePair) {
                                $supportedLanguagePairs[] = $languagePair->target['code'];
                            }

                            foreach (json_decode($order->targetSites) as $key => $siteId) {
                                $site = Craft::$app->getSites()->getSiteById($siteId);
                                $language = Translations::$plugin->siteRepository->normalizeLanguage($site->language);

                                if (!in_array($language, array_column($translationService->getLanguages(), 'code'))) {
                                    $unsupported = true;
                                    $unsupportedLangs[] = array(
                                        'language' => $site->name .' ('.$site->language.')'
                                    );
                                }

                                if (!in_array($language, $supportedLanguagePairs)) {
                                    $unsupported = true;
                                    $unsupportedLangs[] = array(
                                        'language' => $site->name .' ('.$site->language.')'
                                    );
                                }
                            }

                            if ($unsupported || !empty($unsupportedLangs)) {
                                $order->status = 'failed';
                                $success = Craft::$app->getElements()->saveElement($order);
                                if (!$success) {
                                    Craft::error('Couldn’t save the order', __METHOD__);
                                }
                                Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'The following language pair(s) are not supported: '.implode(', ', array_column($unsupportedLangs, 'language')).' Contact Acclaro for assistance.'));
                                // return; // @todo This might be a better idea than failing the order
                                return $this->redirect('translations/orders', 302, true);
                            }

                        } else {
                            // var_dump('Could not fetch languages');
                        }
                    }

                    $order->logActivity(sprintf(Translations::$plugin->translator->translate('app', 'Order Submitted to %s'), $order->translator->getName()));
                    
                    $order->wordCount = array_sum($wordCounts);
                    Craft::$app->queue->push(new CreateDrafts([
                        'description' => 'Creating translation drafts',
                        'orderId' => $order->id,
                        'wordCounts' => $wordCounts,
                    ]));

                    Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Order Submitted.'));
                } else {
                    Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Order Saved.'));
                }
            }

        } catch (Exception $e) {
            Craft::error('Couldn’t save the order. Error: '.$e->getMessage(), __METHOD__);
            $order->status = 'failed';
            Craft::$app->getElements()->saveElement($order);
        }

        return $this->redirect('translations/orders', 302, true);
    }

    public function actionDeleteOrder()
    {
        $this->requireLogin();
        $this->requireAdmin();
        $this->requirePostRequest();

        $orderId = Craft::$app->getRequest()->getParam('orderId');
        $hardDelete = Craft::$app->getRequest()->getParam('hardDelete');
        $restore = Craft::$app->getRequest()->getParam('restore');

        if ($hardDelete || $restore) {

            $order = Translations::$plugin->orderRepository->getOrderByIdWithTrashed($orderId);
            $order->dateDeleted = NULL;
            $order->save();

            if ($restore) {
                return $this->asJson([
                    'success' => true,
                    'error' => null
                ]);
            }
        }

        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        if (!$order) {
            return $this->asJson([
                'success' => false,
                'error' => Translations::$plugin->translator->translate('app', 'No order exists with the ID “{id}”.', array('id' => $orderId))
            ]);
        }

        $translator = $order->translatorId ? Translations::$plugin->translatorRepository->getTranslatorById($order->translatorId) : null;
        if (($translator->service == 'export_import' && $order->status === 'published') || ($translator->service == 'acclaro' && $order->status !== 'new' && $order->status !== 'failed')) {
            return $this->asJson([
                'success' => false,
                'error' => Translations::$plugin->translator->translate('app', 'You cannot delete a submitted order.')
            ]);
        }

        if ($orderId) {
            Craft::$app->getElements()->deleteElementById($orderId);

            return $this->asJson([
                'success' => true,'error' => null
            ]);
        }
    }

    public function actionSyncOrder($params = array())
    {
        $this->requirePostRequest();

        $params = Craft::$app->getRequest()->getRequiredBodyParam('params');

        $order = Translations::$plugin->orderRepository->getOrderById($params['orderId']);

        if ($order) {
            Craft::$app->queue->push(new SyncOrder([
                'description' => 'Syncing order '. $order->title,
                'order' => $order
            ]));
            
            return $this->asJson([
                'success' => true,
                'error' => null
            ]);
        }
    }
    
    public function actionSyncOrders()
    {
        Craft::$app->queue->push(new SyncOrders([
            'description' => 'Syncing translation orders'
        ]));

        return $this->asJson([
            'success' => true,
            'error' => null
        ]);
    }

    public function actionEditOrderName()
    {
        $this->requireLogin();
        $this->requireAdmin();
        $this->requirePostRequest();

        $orderId = Craft::$app->getRequest()->getParam('orderId');
        $name = Craft::$app->getRequest()->getParam('order_name');

        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        if (!$orderId) {
            return $this->asJson([
                'success' => false,
                'error' => Translations::$plugin->translator->translate('app', 'No order exists with the ID “{id}”.', array('id' => $orderId))
            ]);
        }

        /*if ($order->getTranslator()->service == 'acclaro') {
            $translationService = Translations::$plugin->translatorFactory->makeTranslationService($order->getTranslator()->service, $order->getTranslator()->getSettings());
            $res = $translationService->editOrderName($order->serviceOrderId, $name);
        }*/

        $translationService = Translations::$plugin->translatorFactory->makeTranslationService('export_import', $order->getTranslator()->getSettings());
        $res = $translationService->editOrderName($order, $name);
        Craft::$app->getElements()->saveElement($order);

        if ($res) {

            return $this->asJson([
                'success' => true,'error' => null
            ]);
        }
    }

    // Global Set CRUD Methods
    // =========================================================================

    public function actionEditGlobalSetDraft(array $variables = array())
    {
        $variables = Craft::$app->getRequest()->resolve()[1];

        if (empty($variables['globalSetHandle'])) {
            // throw new HttpException(400, Translations::$plugin->translator->translate('app', 'Param “{name}” doesn’t exist.', array('name' => 'globalSetHandle')));
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Param “{name}” doesn’t exist.', array('name' => 'globalSetHandle')));
            return;
        }

        $variables['globalSets'] = array();

        $globalSets = Translations::$plugin->globalSetRepository->getAllSets();

        foreach ($globalSets as $globalSet) {
            if (Craft::$app->getUser()->checkPermission('editGlobalSet:'.$globalSet->id)) {
                $variables['globalSets'][$globalSet->handle] = $globalSet;
            }
        }

        if (!isset($variables['globalSets'][$variables['globalSetHandle']])) {
            // throw new HttpException(400, Translations::$plugin->translator->translate('app', 'Invalid global set handle'));
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Invalid global set handle'));
            return;
        }

        $globalSet = $variables['globalSets'][$variables['globalSetHandle']];

        $variables['globalSetId'] = $globalSet->id;

        $variables['orders'] = array();

        foreach (Translations::$plugin->orderRepository->getDraftOrders() as $order) {
            if ($order->sourceSite === $globalSet->site) {
                $variables['orders'][] = $order;
            }
        }

        $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($variables['draftId']);
        
        $variables['drafts'] = Translations::$plugin->globalSetDraftRepository->getDraftsByGlobalSetId($globalSet->id, $draft->site);

        $variables['draft'] = $draft;

        $variables['file'] = Translations::$plugin->fileRepository->getFileByDraftId($draft->draftId, $globalSet->id);

        $this->renderTemplate('translations/globals/_editDraft', $variables);
    }

    public function actionSaveGlobalSetDraft()
    {
        $this->requirePostRequest();

        $site = Craft::$app->getRequest()->getParam('site', Craft::$app->sites->getPrimarySite()->id);

        $globalSetId = Craft::$app->getRequest()->getParam('globalSetId');

        $globalSet = Translations::$plugin->globalSetRepository->getSetById($globalSetId, $site);

        if (!$globalSet) {
            // throw new HttpException(400, Translations::$plugin->translator->translate('app', 'No global set exists with the ID “{id}”.', array('id' => $globalSetId)));
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'No global set exists with the ID “{id}”.', array('id' => $globalSetId)));
            return;
        }

        $draftId = Craft::$app->getRequest()->getParam('draftId');

        if ($draftId) {
            $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($draftId);

            if (!$draft) {
                // throw new HttpException(400, Translations::$plugin->translator->translate('app', 'No draft exists with the ID “{id}”.', array('id' => $draftId)));
                Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'No draft exists with the ID “{id}”.', array('id' => $draftId)));
                return;
            }
        } else {
            $draft = Translations::$plugin->globalSetDraftRepository->makeNewDraft();
            $draft->id = $globalSetId;
            $draft->site = $site;
        }

        // @TODO Make sure they have permission to be editing this
        $fieldsLocation = Craft::$app->getRequest()->getParam('fieldsLocation', 'fields');
        
        $draft->setFieldValuesFromRequest($fieldsLocation);
        
        if (Translations::$plugin->globalSetDraftRepository->saveDraft($draft)) {
            Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Draft saved.'));

            $this->redirect($draft->getCpEditUrl(), 302, true);
        } else {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Couldn’t save draft.'));

            Craft::$app->urlManager->setRouteParams(array(
                'globalSet' => $draft
            ));
        }
    }

    public function actionPublishGlobalSetDraft()
    {
        $this->requirePostRequest();

        $draftId = Craft::$app->getRequest()->getParam('draftId');

        $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($draftId);

        if (!$draft) {
            // throw new HttpException(400, Translations::$plugin->translator->translate('app', 'No draft exists with the ID “{id}”.', array('id' => $draftId)));
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'No draft exists with the ID “{id}”.', array('id' => $draftId)));
            return;
        }

        $globalSet = Translations::$plugin->globalSetRepository->getSetById($draft->globalSetId, $draft->site);

        if (!$globalSet) {
            // throw new HttpException(400, Translations::$plugin->translator->translate('app', 'No global set exists with the ID “{id}”.', array('id' => $draft->id)));
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'No global set exists with the ID “{id}”.', array('id' => $draft->id)));
            return;
        }

        //@TODO $this->enforceEditEntryPermissions($entry);

        $fieldsLocation = Craft::$app->getRequest()->getParam('fieldsLocation', 'fields');

        $draft->setFieldValuesFromRequest($fieldsLocation);

        // restore the original name
        $draft->name = $globalSet->name;

        $file = Translations::$plugin->fileRepository->getFileByDraftId($draftId, $globalSet->id);

        if ($file) {
            $order = Translations::$plugin->orderRepository->getOrderById($file->orderId);

            $file->status = 'published';

            Translations::$plugin->fileRepository->saveFile($file);

            $areAllFilesPublished = true;

            foreach ($order->files as $file) {
                if ($file->status !== 'published') {
                    $areAllFilesPublished = false;
                    break;
                }
            }

            if ($areAllFilesPublished) {
                $order->status = 'published';

                Translations::$plugin->orderRepository->saveOrder($order);
            }
        }

        if (Translations::$plugin->globalSetDraftRepository->publishDraft($draft)) {
            $this->redirect($globalSet->getCpEditUrl(), 302, true);
            
            Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Draft published.'));
            
            return Translations::$plugin->globalSetDraftRepository->deleteDraft($draft);
        } else {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Couldn’t publish draft.'));

            // Send the draft back to the template
            Craft::$app->urlManager->setRouteParams(array(
                'draft' => $draft
            ));
        }
    }

    public function actionDeleteGlobalSetDraft()
    {
        $this->requirePostRequest();

        $draftId = Craft::$app->getRequest()->getParam('draftId');

        $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($draftId);

        if (!$draft) {
            // throw new HttpException(400, Translations::$plugin->translator->translate('app', 'No draft exists with the ID “{id}”.', array('id' => $draftId)));
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'No draft exists with the ID “{id}”.', array('id' => $draftId)));
            return;
        }

        $globalSet = $draft->getGlobalSet();

        Translations::$plugin->globalSetDraftRepository->deleteDraft($draft);

        Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Draft deleted.'));

        return $this->redirect($globalSet->getCpEditUrl(), 302, true);
    }
}
