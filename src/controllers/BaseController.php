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
use craft\queue\Queue;
use craft\web\Controller;
use craft\elements\Entry;
use yii\web\HttpException;
use craft\elements\Category;
use craft\helpers\UrlHelper;
use craft\elements\GlobalSet;
use SebastianBergmann\Diff\Differ;
use acclaro\translations\services\App;
use acclaro\translations\Translations;
use acclaro\translations\services\job\SyncOrder;
use acclaro\translations\services\job\CreateDrafts;
use acclaro\translations\services\job\ApplyDrafts;
use acclaro\translations\services\job\DeleteDrafts;
use acclaro\translations\services\job\RegeneratePreviewUrls;
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
     * @var int
     */
    protected $pluginVersion;

    const WORDCOUNT_LIMIT = 2000;

    // Public Methods
    // =========================================================================
    
    public function __construct(
        $id,
        $module = null
    ) {
        parent::__construct($id, $module);

        $this->pluginVersion = Craft::$app->getPlugins()->getPlugin('translations')->getVersion();
    }

    public function authenticateService($service, $settings)
    {
        $translator = Translations::$plugin->translatorRepository->makeNewTranslator();
        $translator->service = $service;
        $translator->settings = json_encode($settings);
        
        $translationService = Translations::$plugin->translatorFactory->makeTranslationService($service, $settings);

        $authenticate = $translationService->authenticate($settings);

        return $authenticate;
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
        $this->requirePostRequest();
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser->can('translations:translator:edit')) {
            return $this->asJson([
                'success' => true,
                'error' => Translations::$plugin->translator->translate('app', 'User does not have permission to perform this action')
            ]);
        }

        $service = Craft::$app->getRequest()->getRequiredParam('service');
        $settings = Craft::$app->getRequest()->getRequiredParam('settings');

        $response = self::authenticateService($service, $settings);

        return $this->asJson(array(
            'success' => $response,
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
        $this->requirePostRequest();
        if (!Translations::$plugin->userRepository->userHasAccess('translations:orders:create')) {
            return;
        }

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
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'All entries within an order must have the same source site'));
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

        $variables['pluginVersion'] = $this->pluginVersion;
        
        $variables['searchParams'] = Translations::$plugin->orderSearchParams->getParams();

        $variables['translators'] = Translations::$plugin->translatorRepository->getActiveTranslators();

        $variables['orderCount'] = Translations::$plugin->orderRepository->getOrdersCount();

        $variables['orderCountAcclaro'] = Translations::$plugin->orderRepository->getAcclaroOrdersCount();

        $variables['selectedSubnavItem'] = 'orders';

        $this->renderTemplate('translations/orders/_index', $variables);
    }
    
    /**
     * @return mixed
     */
    public function actionTranslatorIndex()
    {
        $variables = array();

        $variables['pluginVersion'] = $this->pluginVersion;

        $variables['translators'] = Translations::$plugin->translatorRepository->getTranslators();

        $variables['translatorTargetSites'] = array();

        $variables['selectedSubnavItem'] = 'translators';
        
        $this->renderTemplate('translations/translators/_index', $variables);
    }

    // Detail Page Methods
    // =========================================================================

    public function actionOrderDetail(array $variables = array())
    {
        $variables = Craft::$app->getRequest()->resolve()[1];

        $variables['orderSubmitted'] = Craft::$app->getRequest()->getParam('submit') ? Craft::$app->getRequest()->getParam('submit') : null;

        $variables['pluginVersion'] = $this->pluginVersion;

        $variables['orderId'] = isset($variables['orderId']) ? $variables['orderId'] : null;

        $variables['inputSourceSite'] = Craft::$app->getRequest()->getQueryParam('sourceSite');

        if (empty($variables['inputSourceSite'])) {
            $variables['inputSourceSite'] = Craft::$app->getRequest()->getParam('sourceSite');
        }

        if (!empty($variables['inputSourceSite'])) {
            if (!Translations::$plugin->userRepository->userHasAccess('translations:orders:create')) {
                return $this->redirect('entries', 302, true);
            }
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
                $variables['orderTargetSitesObject'][] = (Craft::$app->getSites()->getSiteById($site) ? Craft::$app->getSites()->getSiteById($site) : [ 'language' => 'Deleted']);
            }
        }

        $variables['orientation'] = Craft::$app->getLocale()->orientation;

        $variables['translatorOptions'] = Translations::$plugin->translatorRepository->getTranslatorOptions();

        $variables['elements'] = $variables['order']->getElements();

        if ($variables['inputElements']) {
            foreach ($variables['inputElements'] as $elementId) {
                $element = Craft::$app->getElements()->getElementById((int) $elementId, null, $variables['order']->sourceSite);

                if ($element) {
                    $variables['elements'][] = $element;
                }
            }
        }

        $variables['duplicateEntries'] = $this->checkOrderDuplicates($variables['elements']);

        $variables['chkDuplicateEntries'] = Translations::getInstance()->settings->chkDuplicateEntries;

        $variables['orderEntriesCount'] = count($variables['elements']);

        $variables['orderWordCount'] = 0;

        $variables['elementWordCounts'] = array();

        $variables['entriesCountBySection'] = array();

        $variables['entriesCountByElement'] = 0;
        $variables['entriesCountByElementCompleted'] = 0;

        foreach ($variables['elements'] as $element) {
            $wordCount = Translations::$plugin->elementTranslator->getWordCount($element);

            $variables['elementWordCounts'][$element->id] = $wordCount;

            $variables['orderWordCount'] += $wordCount;

            if ($element instanceof GlobalSet) {
                $sectionName = 'Globals';
            } else if ($element instanceof Category) {
                $sectionName = 'Category';
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
                $variables['files'][$element->id] = Translations::$plugin->fileRepository->getFilesByOrderId($variables['orderId'], $element->id);

                $variables['entriesCountByElement'] += count($variables['files'][$element->id]);

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

                        if($file->status === 'complete' || $file->status === 'published') {
                            $variables['entriesCountByElementCompleted']++;
                        }
                    } elseif ($element instanceof GlobalSet OR $element instanceof Category) {
                        if($file->status === 'complete' || $file->status === 'published') {
                            $variables['entriesCountByElementCompleted']++;
                        }
                    }

                    $variables['fileTargetSites'][$file->targetSite] = (Craft::$app->getSites()->getSiteById($file->targetSite) ? Craft::$app->getSites()->getSiteById($file->targetSite) : [ 'language' => 'Deleted' ]);

                    if (Craft::$app->getSites()->getSiteById($file->targetSite)) {
                        $variables['fileUrls'][$file->id] = Translations::$plugin->urlGenerator->generateFileUrl($element, $file);
                    }
                }
                
                $variables['isElementPublished'][$element->id] = $isElementPublished;
            }
        }

        $variables['entriesCountByElement'] -=  $variables['entriesCountByElementCompleted'];

        if (!$variables['translatorOptions']) {
            $variables['translatorOptions'] = array('' => Translations::$plugin->translator->translate('app', 'No Translators'));
        }
        
        $user = Craft::$app->getUser();

        $variables['owners'] = array(
            $user->id => $user->getRememberedUsername(),
        );

        $variables['sites'] = Craft::$app->getSites()->getAllSiteIds();
        
        $targetSites = Craft::$app->getSites()->getAllSiteIds();

        $variables['sourceSites'] = array();

        foreach ($targetSites as $key => $site) {
            $site = Craft::$app->getSites()->getSiteById($site);
            $variables['sourceSites'][] = array(
                'value' => $site->id,
                'label' => $site->name. '('. $site->language. ')'
            );
        }

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
                'label' => $site->name.' ('. $site->language. ')'
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

    public function actionApplyDrafts()
    {
        if (!Translations::$plugin->userRepository->userHasAccess('translations:orders:apply-translations')) {
            return;
        }

        $orderId = Craft::$app->getRequest()->getParam('orderId');
        $elementIds = Craft::$app->getRequest()->getParam('elements');

        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        $totalWordCount = ($order->wordCount * count($order->getTargetSitesArray()));

        if ($totalWordCount > self::WORDCOUNT_LIMIT ) {

            $job = Craft::$app->queue->push(new ApplyDrafts([
                'description' => 'Applying translation drafts',
                'orderId' => $orderId,
                'elementIds' => $elementIds
            ]));

            if ($job) {
                $params = [
                    'id' => (int) $job,
                    'notice' => 'Done applying translation drafts',
                    'url' => 'translations/orders/detail/'. $orderId,
                ];
                Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
            } else {
                $this->redirect('translations/orders', 302, true);
            }
        } else {

            Translations::$plugin->draftRepository->applyDrafts($orderId, $elementIds);

            Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Done applying translation drafts'));

        }
    }

    // Translator CRUD Methods
    // =========================================================================

    public function actionDeleteTranslator()
    {
        $this->requireLogin();
        $this->requirePostRequest();

        if (!Translations::$plugin->userRepository->userHasAccess('translations:translator:delete')) {
            return;
        }

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
        $this->requirePostRequest();

        $translatorId = Craft::$app->getRequest()->getBodyParam('id');

        if ($translatorId) {

            if (!Translations::$plugin->userRepository->userHasAccess('translations:translator:edit')) {
                return;
            }

            $translator = Translations::$plugin->translatorRepository->getTranslatorById($translatorId);

            if (!$translator) {
                // throw new HttpException(400, 'Invalid Translator');
                Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Invalid Translator'));
                return;
            }
        } else {
            if (!Translations::$plugin->userRepository->userHasAccess('translations:translator:create')) {
                return;
            }
            $translator = Translations::$plugin->translatorRepository->makeNewTranslator();
        }

        $service = Craft::$app->getRequest()->getBodyParam('service');
        
        $allSettings = Craft::$app->getRequest()->getBodyParam('settings');

        $settings = isset($allSettings[$service]) ? $allSettings[$service] : array();

        $translator->label = Craft::$app->getRequest()->getBodyParam('label');
        $translator->service = $service;
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
        $this->requirePostRequest();

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser->can('translations:orders:create')) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'User does not have permission to perform this action.'));
            return;
        }

        $orderId = Craft::$app->getRequest()->getParam('id');

        if ($orderId) {
            $order = Translations::$plugin->orderRepository->getOrderById($orderId);

            // Authenticate service
            $translator = $order->getTranslator();
            $service = $translator->service;
            $settings = $translator->getSettings();
            $authenticate = self::authenticateService($service, $settings);
            
            if (!$authenticate && $service == 'acclaro') {
                $message = Translations::$plugin->translator->translate('app', 'Invalid API key');
                Craft::$app->getSession()->setError($message);
                return $this->redirect('translations/orders/new', 302, true);
            }

            if (!$order) {
                throw new HttpException(400, Translations::$plugin->translator->translate('app', 'Invalid Order'));
            }

            $queueOrders = Craft::$app->getSession()->get('queueOrders');
            if (!empty($queueOrders) && ($key = array_search($orderId, $queueOrders)) !== false) {
                if (Craft::$app->getQueue()->status($key) == Queue::STATUS_WAITING || Craft::$app->getQueue()->status($key) == Queue::STATUS_RESERVED ) {
                    Craft::$app->getSession()->setError('This order is currently being processed.');
                    return $this->redirect('translations/orders', 302, true);
                } else {
                    unset($queueOrders[$key]);
                    Craft::$app->getSession()->set('queueOrders', $queueOrders);
                }
            }

        } else {
            $sourceSite = Craft::$app->getRequest()->getParam('sourceSiteSelect');

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

            // Authenticate service
            $translator = $order->getTranslator();
            $service = $translator->service;
            $settings = $translator->getSettings();
            $authenticate = self::authenticateService($service, $settings);
            
            if (!$authenticate && $service == 'acclaro') {
                $message = Translations::$plugin->translator->translate('app', 'Invalid API key');
                Craft::$app->getSession()->setError($message);
                return $this->redirect('translations/orders/new', 302, true);
            }

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
            $job = '';
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
                                return $this->redirect('translations/orders/detail/'. $order->id);
                            }

                        } else {
                            // var_dump('Could not fetch languages');
                        }
                    }

                    $order->logActivity(sprintf(Translations::$plugin->translator->translate('app', 'Order Submitted to %s'), $order->translator->getName()));
                    
                    $order->wordCount = array_sum($wordCounts);

                    $totalWordCount = ($order->wordCount * count($order->getTargetSitesArray()));

                    if ($totalWordCount > self::WORDCOUNT_LIMIT) {
                        $job = Craft::$app->queue->push(new CreateDrafts([
                            'description' => 'Creating translation drafts',
                            'orderId' => $order->id,
                            'wordCounts' => $wordCounts,
                        ]));

                        $queueOrders = Craft::$app->getSession()->get('queueOrders');
                        $queueOrders[$job] = $order->id;
                        Craft::$app->getSession()->set('queueOrders', $queueOrders);
                    } else {
                        $job =  null;
                        Translations::$plugin->draftRepository->createOrderDrafts($order->id, $wordCounts);
                    }

                } else {
                    Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Order Saved.'));
                }
            }

        } catch (Exception $e) {
            Craft::error('Couldn’t save the order. Error: '.$e->getMessage(), __METHOD__);
            $order->status = 'failed';
            Craft::$app->getElements()->saveElement($order);
        }

        if ($job) {
            if ($order->getTranslator()->service == 'export_import') {
                $params = [
                    'id' => (int) $job,
                    'notice' => 'Done creating translation drafts',
                    'url' => 'translations/orders/detail/'. $order->id
                ];
                Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
            } else {
                Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Sending order to Acclaro, please refresh your Orders once complete'));
            }
        } else if(is_null($job)) {
            Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'New order created: '.$order->title));
            return $this->redirect('translations/orders/detail/'. $order->id);
        } else {
            return $this->redirect('translations/orders', 302, true);
        }
    }

    public function actionDeleteOrder()
    {
        $this->requireLogin();
        $this->requirePostRequest();
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser->can('translations:orders:delete')) {
            return $this->asJson([
                'success' => false,
                'error' => Translations::$plugin->translator->translate('app', 'User does not have permission to perform this action')
            ]);
        }

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

            if ($hardDelete) {
                $drafts = [];
                foreach ($order->getFiles() as $file) {
                    $drafts[] = $file->draftId;
                }
                if ($drafts) {
                    Craft::$app->queue->push(new DeleteDrafts([
                        'description' => 'Deleting Translation Drafts',
                        'drafts' => $drafts,
                    ]));
                }
            }

            Craft::$app->getElements()->deleteElementById($orderId);

            return $this->asJson([
                'success' => true,'error' => null
            ]);
        }
    }

    public function actionSyncOrder()
    {
        $this->requireLogin();
        $this->requirePostRequest();
        if (!Translations::$plugin->userRepository->userHasAccess('translations:orders:import')) {
            return $this->redirect('translations', 302, true);
        }

        $orderId = Craft::$app->getRequest()->getParam('orderId');

        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        // Authenticate service
        $translator = $order->getTranslator();
        $service = $translator->service;
        $settings = $translator->getSettings();
        $authenticate = self::authenticateService($service, $settings);
        
        if (!$authenticate && $service == 'acclaro') {
            $message = Translations::$plugin->translator->translate('app', 'Invalid API key');
            Craft::$app->getSession()->setError($message);
            return;
        }

        if ($order) {
            $totalWordCount = ($order->wordCount * count($order->getTargetSitesArray()));
            if ($totalWordCount > self::WORDCOUNT_LIMIT) {
                $job = Craft::$app->queue->push(new SyncOrder([
                    'description' => 'Syncing order '. $order->title,
                    'order' => $order
                ]));

                if ($job) {
                    $params = [
                        'id' => (int) $job,
                        'notice' => 'Done syncing order '. $order->title,
                        'url' => 'translations/orders/detail/'. $order->id
                    ];
                    Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
                } else {
                    Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app',  'Cannot sync order '. $order->title));
                    return $this->redirect('translations/orders/detail/'. $order->id, 302, true);
                }
            } else {
                Translations::$plugin->orderRepository->syncOrder($order);
                Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app',  'Done syncing order '. $order->title));
                return $this->redirect('translations/orders/detail/'. $order->id, 302, true);
            }
        }
    }
    
    public function actionSyncOrders()
    {

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser->can('translations:orders:import')) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'User does not have permission to perform this action.'));
            return;
        }

        $orders = Translations::$plugin->orderRepository->getInProgressOrders();
        $allFileCounts = $totalWordCount = 0;
        foreach ($orders as $order) {
            if ($order->translator->service === 'export_import') {
                continue;
            }
            $totalWordCount += ($order->wordCount * count($order->getTargetSitesArray()));
            $allFileCounts += count($order->files);
        }

        $job = '';
        $url = ltrim(Craft::$app->getRequest()->getQueryParam('p'), 'admin/');
        foreach ($orders as $order) {
            // Don't update manual orders
            if ($order->translator->service === 'export_import') {
                continue;
            }

            if ($totalWordCount > self::WORDCOUNT_LIMIT) {
                $job = Craft::$app->queue->push(new SyncOrder([
                    'description' => 'Syncing order '. $order->title,
                    'order' => $order
                ]));
            } else {
                Translations::$plugin->orderRepository->syncOrder($order);
            }
        }

        if ($job) {
            $params = [
                'id' => (int) $job,
                'notice' => 'Done syncing orders',
                'url' => $url
            ];
            Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
        } else {
            Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app',  'Cannot sync orders.'));
            return $this->redirect($url, 302, true);
        }
    }

    public function actionEditOrderName()
    {
        $this->requireLogin();
        $this->requirePostRequest();
        if (!Translations::$plugin->userRepository->userHasAccess('translations:orders:edit')) {
            return $this->redirect('translations', 302, true);
        }

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

    public function actionRegeneratePreviewUrls()
    {
        $url = ltrim(Craft::$app->getRequest()->getQueryParam('p'), 'admin/');

        $this->requireLogin();
        $this->requirePostRequest();
        if (!Translations::$plugin->userRepository->userHasAccess('translations:orders:edit')) {
            Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app',  'User does have permission to edit orders'));
            return $this->redirect('translations/orders/detail/'. $order->id, 302, true);
        }

        $orderId = Craft::$app->getRequest()->getParam('orderId');

        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        // Authenticate service
        $translator = $order->getTranslator();
        $service = $translator->service;
        $settings = $translator->getSettings();
        $authenticate = self::authenticateService($service, $settings);
        
        if (!$authenticate && $service == 'acclaro') {
            $message = Translations::$plugin->translator->translate('app', 'Invalid API key');
            Craft::$app->getSession()->setError($message);
            return;
        }

        if ($order) {

            $totalWordCount = ($order->wordCount * count($order->getTargetSitesArray()));

            if ($totalWordCount > self::WORDCOUNT_LIMIT) {
                $job = Craft::$app->queue->push(new RegeneratePreviewUrls([
                    'description' => 'Regenerating preview urls for '. $order->title,
                    'order' => $order
                ]));

                if ($job) {
                    $params = [
                        'id' => (int) $job,
                        'notice' => 'Done regenerating preview urls for '. $order->title,
                        'url' => 'translations/orders/detail/'. $order->id
                    ];
                    Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
                } else {
                    $this->redirect('translations/orders/detail/'. $order->id, 302, true);
                }
            } else {
                Translations::$plugin->fileRepository->regeneratePreviewUrls($order);
                Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app',  'Done regenerating preview urls for '. $order->title));
            }
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

    // Category Draft CRUD Methods

    public function actionEditCategoryDraft(array $variables = array())
    {
        $variables = Craft::$app->getRequest()->resolve()[1];

        if (empty($variables['slug'])) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Param “{name}” doesn’t exist.', array('name' => 'categoryGroup')));
            return;
        }

        $category = explode('-', $variables['slug']);
        $categoryId = $category[0];
        $category = Craft::$app->categories->getCategoryById($categoryId);
        $categoryGroup = Craft::$app->categories->getGroupById($category->groupId);
        $variables['category'] = $category;
        $variables['groupHandle'] = $variables['group'];
        $variables['group'] = $categoryGroup;

        $variables['categoryId'] = $categoryId;

        $variables['orders'] = array();

        $draft = Translations::$plugin->categoryDraftRepository->getDraftById($variables['draftId']);

        $variables['draft'] = $draft;

        $variables['file'] = Translations::$plugin->fileRepository->getFileByDraftId($draft->draftId, $categoryId);

        $variables['continueEditingUrl'] = '';
        $variables['nextCategoryUrl'] = '';
        $variables['title'] = $category->title;
        $variables['siteIds'] = Craft::$app->getSites()->getAllSiteIds();
        $variables['showPreviewBtn'] = false;

        $this->renderTemplate('translations/categories/_editDraft', $variables);
    }

    public function actionSaveCategoryDraft()
    {
        $this->requirePostRequest();

        $site = Craft::$app->getRequest()->getParam('site', Craft::$app->sites->getPrimarySite()->id);

        $categoryId = Craft::$app->getRequest()->getParam('categoryId');

        $category = Translations::$plugin->categoryRepository->getCategoryById($categoryId, $site);

        if (!$category) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'No category exists with the ID “{id}”.', array('id' => $categoryId)));
            return;
        }

        $draftId = Craft::$app->getRequest()->getParam('draftId');

        if ($draftId) {
            $draft = Translations::$plugin->categoryDraftRepository->getDraftById($draftId);

            if (!$draft) {
                Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'No draft exists with the ID “{id}”.', array('id' => $draftId)));
                return;
            }
        } else {
            $draft = Translations::$plugin->categoryDraftRepository->makeNewDraft();
            $draft->id = $categoryId;
            $draft->site = $site;
        }

        $fieldsLocation = Craft::$app->getRequest()->getParam('fieldsLocation', 'fields');

        $draft->setFieldValuesFromRequest($fieldsLocation);

        if (Translations::$plugin->categoryDraftRepository->saveDraft($draft)) {
            Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Draft saved.'));

            $this->redirect($draft->getCpEditUrl(), 302, true);
        } else {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Couldn’t save draft.'));

            Craft::$app->urlManager->setRouteParams(array(
                'category' => $draft
            ));
        }
    }

    public function actionPublishCategoryDraft()
    {
        $this->requirePostRequest();

        $draftId = Craft::$app->getRequest()->getParam('draftId');

        $draft = Translations::$plugin->categoryDraftRepository->getDraftById($draftId);

        if (!$draft) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'No draft exists with the ID “{id}”.', array('id' => $draftId)));
            return;
        }

        $category = Translations::$plugin->categoryRepository->getCategoryById($draft->categoryId, $draft->site);

        if (!$category) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'No category exists with the ID “{id}”.', array('id' => $draft->id)));
            return;
        }

        $fieldsLocation = Craft::$app->getRequest()->getParam('fieldsLocation', 'fields');

        $draft->setFieldValuesFromRequest($fieldsLocation);

        // restore the original name
        $draft->name = $category->title;

        $file = Translations::$plugin->fileRepository->getFileByDraftId($draftId, $category->id);

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

        if (Translations::$plugin->categoryDraftRepository->publishDraft($draft)) {
            $this->redirect($category->getCpEditUrl(), 302, true);

            Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Draft published.'));

            return Translations::$plugin->categoryDraftRepository->deleteDraft($draft);
        } else {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Couldn’t publish draft.'));

            // Send the draft back to the template
            Craft::$app->urlManager->setRouteParams(array(
                'draft' => $draft
            ));
        }
    }

    public function actionDeleteCategoryDraft()
    {
        $this->requirePostRequest();

        $draftId = Craft::$app->getRequest()->getParam('draftId');
        $draft = Translations::$plugin->categoryDraftRepository->getDraftById($draftId);

        if (!$draft) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'No draft exists with the ID “{id}”.', array('id' => $draftId)));
            return;
        }

        $category = Translations::$plugin->categoryRepository->getCategoryById($draft->categoryId);
        $url = $category->getCpEditUrl();
        $elementId = $draft->categoryId;

        Translations::$plugin->categoryDraftRepository->deleteDraft($draft);

        Translations::$plugin->fileRepository->delete($draftId, $elementId);

        Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Draft deleted.'));

        return $this->redirect($url, 302, true);
    }

    public function actionGetFileDiff() {

        $variables = Craft::$app->getRequest()->resolve()[1];
        $fileId = isset($variables['fileId']) ? $variables['fileId'] : null;

        $file = Translations::$plugin->fileRepository->getFileById($fileId);
        $data = [];

        if ($file && ($file->status == 'complete' || $file->status == 'published')) {
            // Current entries XML
            $currentXML = $file->target;
            $currentXML = simplexml_load_string($currentXML)->body->asXML();

            // Translated file XML
            $translatedXML = $file->source;
            $translatedXML = simplexml_load_string($translatedXML)->body->asXML();

            // Load a new Diff class
            $differ = new Differ();

            $element = Craft::$app->getElements()->getElementById($file->elementId);

            $countElement = $element;
            if ($element instanceof Entry) {
                // Now we can get the element
                if ($file->status == 'complete') {
                    $element = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);
                } else {
                    $element = Craft::$app->getElements()->getElementById($file->elementId, null, $file->targetSite);
                }
                $countElement = $element;
                $data['entryName'] = Craft::$app->getEntries()->getEntryById($element->id) ? Craft::$app->getEntries()->getEntryById($element->id)->title : '';
            } else if ($element instanceof GlobalSet) {
                if ($file->status == 'complete') {
                    $element = Translations::$plugin->globalSetDraftRepository->getDraftById($file->draftId);
                } else {
                    $element = Translations::$plugin->globalSetRepository->getSetById($file->elementId, $file->targetSite);
                }

                $data['entryName'] = $element->name;
            } else if ($element instanceof Category) {
                if ($file->status == 'complete') {
                    $element = Translations::$plugin->categoryDraftRepository->getDraftById($file->draftId);
                } else {
                    $element = Translations::$plugin->categoryRepository->getCategoryById($file->elementId, $file->targetSite);
                }
                $data['entryName'] = $element->title;
            }

            $wordCount = (Translations::$plugin->elementTranslator->getWordCount($element) - $file->wordCount);


            // Create data array
            $data['entryId'] = $element->id;
            $data['fileId'] = $file->id;
            $data['siteId'] = $element->siteId;
            $data['siteLabel'] = Craft::$app->sites->getSiteById($element->siteId)->name. '<span class="light"> ('. Craft::$app->sites->getSiteById($element->siteId)->language. ')</span>';
            $handle = isset($element->section) ? $element->section->handle : '';
            $data['entryUrl'] = UrlHelper::cpUrl('entries/'.$handle.'/'.$element->id.'/'.Craft::$app->sites->getSiteById($element->siteId)->handle);
            $data['dateApplied'] = ($file->status == 'published') ? $element->dateUpdated->format('M j, Y g:i a') : '--' ;
            $dateDelivered = new DateTime($file->dateDelivered);
            $data['dateDelivered'] = ($dateDelivered) ? $dateDelivered->format('M j, Y g:i a') : '';
            $data['fileStatus'] = $file->status;
            $data['wordDifference'] = (int)$wordCount == $wordCount && (int)$wordCount > 0 ? '+'.$wordCount : $wordCount;
            $data['diff'] = $differ->diff($translatedXML, $currentXML);
        }

        return $this->asJson([
            'success' => true,
            'data' => $data,
            'error' => null
        ]);
    }

    public function actionGetFileDiffHtml() {

        $variables = Craft::$app->getRequest()->resolve()[1];
        $fileId = isset($variables['fileId']) ? $variables['fileId'] : null;

        $file = Translations::$plugin->fileRepository->getFileById($fileId);
        $data = [];

        if ($file && ($file->status == 'complete' || $file->status == 'published')) {

            $element = Craft::$app->getElements()->getElementById($file->elementId, null, $file->sourceSite);

            $data['originalUrl'] = $element->url;
            $data['newUrl'] = $file->previewUrl;
        }

        return $this->asJson([
            'success' => true,
            'data' => $data,
            'error' => null
        ]);
    }

    public function actionAddEntries()
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser->can('translations:orders:edit')) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'User does not have permission to perform this action.'));
            return;
        }

        $orderId = Craft::$app->getRequest()->getParam('orderId');

        if (!$orderId) {
            return $this->asJson([
                'success' => false,
                'error' => Translations::$plugin->translator->translate('app', 'Missing order ID')
            ]);
        }

        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        if (!$order) {
            return $this->asJson([
                'success' => false,
                'error' => Translations::$plugin->translator->translate('app', 'No order exists with the ID “{id}”.', array('id' => $orderId))
            ]);
        }
        $job = '';
        try {

            $elementIds = Craft::$app->getRequest()->getParam('elements') ? Craft::$app->getRequest()->getParam('elements') : array();
            $skipOrReplace = null;
            $existingElementIds = json_decode($order->elementIds);
            $duplicateElements = array_intersect($existingElementIds, $elementIds);

            if(Craft::$app->getRequest()->getParam('checkDuplicate')) {
                if ($duplicateElements) {
                    $dupElementsTitle = [];
                    foreach ($duplicateElements as $dupId) {
                        $element = Craft::$app->getElements()->getElementById($dupId, null, $order->siteId);
                        $dupElementsTitle[] = $element->title;
                    }

                    return $this->asJson([
                        'success' => true,
                        'data' => ['duplicates' => $dupElementsTitle],
                        'error' => null
                    ]);
                }
            } else {
                $skipOrReplace = Craft::$app->getRequest()->getParam('skipOrReplace');
            }

            // if have duplicate elements and user selected to skip
            if ($duplicateElements && $skipOrReplace != 'replace') {
                $elementIds = array_diff($elementIds, $duplicateElements);
            }

            $newAddElement = [];
            foreach ($order->getTargetSitesArray() as $key => $site) {
                foreach ($elementIds as $elementId) {
                    $element = Craft::$app->getElements()->getElementById($elementId);
                    $wordCounts[$element->id] = Translations::$plugin->elementTranslator->getWordCount($element);
                    $file = '';

                    if ($element instanceof Entry) {
                        $sites = [];

                        $elementSection = Craft::$app->getSections()->getSectionById($element->sectionId);
                        foreach ($elementSection->getSiteIds() as $key => $sectionSite) {
                            $sites[] = $sectionSite;
                        }

                        $hasTargetSites = !array_diff(json_decode($order->targetSites), $sites);
                        if (!$hasTargetSites) {
                            continue;
                        }
                    }

                    if (in_array($element->id, $duplicateElements)) {
                        if ($skipOrReplace == 'replace') {
                            $order->logActivity(sprintf(Translations::$plugin->translator->translate('app', 'Updating File '.$element->title)));
                            $file = Translations::$plugin->fileRepository->getFilesByOrderId($order->id, $element->id, $site);
                            if (!$file) {
                                continue;
                            }
                            $file = $file[0];

                            if ($file->status == 'complete' || $file->status == 'published') {
                                continue;
                            } else if($file->status == 'canceled' || $file->status == 'failed') {
                                $file->status = 'in progress';
                            }

                            $file = Translations::$plugin->draftRepository->createDrafts($element, $order, $site, $wordCounts, $file);
                        }
                    } else {
                        $order->logActivity(sprintf(Translations::$plugin->translator->translate('app', 'Adding file '.$element->title)));
                        $file = Translations::$plugin->draftRepository->createDrafts($element, $order, $site, $wordCounts);
                        $newAddElement[] = $element->id;
                    }

                    if ($order->translator->service !== 'export_import') {
                        $translator = $order->getTranslator();

                        $translationService = Translations::$plugin->translatorFactory->makeTranslationService($translator->service, $translator->getSettings());

                        $file = Translations::$plugin->fileRepository->getFileByDraftId($file->draftId, $file->elementId);

                        if ($file) {
                            $translationService->sendOrderFile($order, $file, $translator->getSettings());
                        }
                    }

                }
            }

            if ($newAddElement) {
                $elements = array_values(array_unique(array_merge($existingElementIds, $newAddElement)));
            }
            $order->elementIds = json_encode($elements);

            $entriesCount = 0;
            $wordCounts = [];
            foreach ($order->getElements() as $element) {
                $entriesCount++;
                $wordCounts[$element->id] = Translations::$plugin->elementTranslator->getWordCount($element);
            }
            $order->entriesCount = $entriesCount;
            $order->wordCount = array_sum($wordCounts);

            $success = Craft::$app->getElements()->saveElement($order);

            if (!$success) {
                return $this->asJson([
                    'success' => false,
                    'error' => Translations::$plugin->translator->translate('app', 'Couldn’t save the order')
                ]);
            }

        } catch (Exception $e) {

            $order->logActivity(sprintf(Translations::$plugin->translator->translate('app', 'Add Entries Failed '.$e->getMessage())));
            Craft::error('Add Entries Failed. Error: '.$e->getMessage(), __METHOD__);
        }

        Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Entries added.'));

        return $this->asJson([
            'success' => true,
            'data' => ['duplicates' => []],
            'error' => null
        ]);
    }

    /**
     * @param $elements
     * @return array
     */
    public function checkOrderDuplicates($elements) {

        $orderIds = [];
        foreach ($elements as $element) {

            $orders = Translations::$plugin->fileRepository->getOrdersByElement($element->id);
            if ($orders) {
                $orderIds[$element->id] = $orders;
            }
        }

        return $orderIds;
    }
}
