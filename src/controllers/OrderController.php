<?php

/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows
 * for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\controllers;

use acclaro\translations\Constants as TranslationsConstants;
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
use acclaro\translations\services\job\acclaro\SendOrder;
use acclaro\translations\Translations;
use acclaro\translations\Constants;
use acclaro\translations\services\job\SyncOrder;
use acclaro\translations\services\job\CreateDrafts;
use acclaro\translations\services\job\ApplyDrafts;
use acclaro\translations\services\job\DeleteDrafts;
use acclaro\translations\services\job\RegeneratePreviewUrls;
use acclaro\translations\services\translator\AcclaroTranslationService;
use Dotenv\Regex\Success;
use Error;

use function PHPSTORM_META\type;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class OrderController extends Controller
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

    public const WORDCOUNT_LIMIT = 2000;

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

    // Detail Page Methods
    // =========================================================================

    public function actionOrderDetail(array $variables = array())
    {
        $variables = Craft::$app->getRequest()->resolve()[1];

        $variables['orderSubmitted'] = Craft::$app->getRequest()->getParam('submit') ?? null;

        $variables['isChanged'] = Craft::$app->getRequest()->getQueryParam('changed') ?? null;

        $variables['pluginVersion'] = $this->pluginVersion;

        $variables['fileCountByElementId'] = [];

        $variables['orderId'] = $variables['orderId'] ?? null;

        $variables['inputSourceSite'] = Craft::$app->getRequest()->getQueryParam('sourceSite');

        $variables['elementIds'] = Craft::$app->getRequest()->getParam('elements');

        if (empty($variables['inputSourceSite'])) {
            $variables['inputSourceSite'] = Craft::$app->getRequest()->getParam('sourceSite');
        }

        if (!empty($variables['inputSourceSite'])) {
            if (!Translations::$plugin->userRepository->userHasAccess('translations:orders:create')) {
                return $this->redirect(Constants::URL_ENTRIES, 302, true);
            }
        }

        $variables['translatorId'] = isset($variables['order']) ? $variables['order']['translatorId'] : null;

        $variables['selectedSubnavItem'] = 'orders';

        $variables['licenseStatus'] = Craft::$app->plugins->getPluginLicenseKeyStatus('translations');

        if (
            $variables['inputSourceSite'] &&
            ! Translations::$plugin->siteRepository->isSiteSupported($variables['inputSourceSite'])
        ) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Source site is not supported'));
            return;
        }

        if ($variables['orderId']) {
            $variables['order']  = Translations::$plugin->orderRepository->getOrderById($variables['orderId']);

            $variables['inputElements'] = Craft::$app->getRequest()->getQueryParam('elements') ?? [];

            if (!$variables['order']) {
                throw new HttpException(404);
            }

            $orders = Translations::$plugin->orderRepository->getAllOrderIds();
            $key = array_search($variables['orderId'], $orders);
            if ($key !== false) {
                $variables['previous_order'] = ($key > 0) ?Translations::$plugin->urlGenerator
                    ->generateCpUrl('admin/translations/orders/detail/' . $orders[$key - 1]) : '';
                $variables['next_order'] = ($key < count($orders) - 1) ? Translations::$plugin->urlGenerator
                    ->generateCpUrl('admin/translations/orders/detail/' . $orders[$key + 1]) : '';
            }
        } else {
            $newOrder = Translations::$plugin->orderRepository->makeNewOrder($variables['inputSourceSite']);
            if ($orderTitle= Craft::$app->getRequest()->getQueryParam('title')) {
                $newOrder->title = $orderTitle;
            }

            if ($orderTargetSites= Craft::$app->getRequest()->getQueryParam('targetSite')) {
                $newOrder->targetSites = json_encode($orderTargetSites);
            }

            if ($orderDueDate= Craft::$app->getRequest()->getQueryParam('dueDate')) {
                $newOrder->requestedDueDate = $orderDueDate;
            }

            if ($orderComments= Craft::$app->getRequest()->getQueryParam('comments')) {
                $newOrder->comments = $orderComments;
            }

            if ($orderTranslatorId= Craft::$app->getRequest()->getQueryParam('translatorId')) {
                $newOrder->translatorId = $orderTranslatorId;
            }

            $variables['order'] = $newOrder;
            $variables['inputElements'] = Craft::$app->getRequest()->getQueryParam('elements');

            if (empty($variables['inputElements'])) {
                $variables['inputElements'] = Craft::$app->getRequest()->getParam('elements');
            }
        }

        $variables['sourceSiteObject'] = Craft::$app->getSites()->getSiteById($variables['order']['sourceSite']);

        if ($variables['order']->targetSites) {
            $variables['orderTargetSitesObject'] = array();
            foreach (json_decode($variables['order']->targetSites) as $key => $site) {
                $variables['orderTargetSitesObject'][] =
                    (Craft::$app->getSites()->getSiteById($site) ?
                    Craft::$app->getSites()->getSiteById($site) : [ 'language' => 'Deleted']);
            }
        }

        $variables['orientation'] = Craft::$app->getLocale()->orientation;

        $variables['versionsByElementId'] = [];
        $variables['translatorOptions'] = Translations::$plugin->translatorRepository->getTranslatorOptions();

        $variables['elements'] = [];
        
        if ($variables['inputElements']) {
            foreach ($variables['inputElements'] as $elementId) {
                $element = Craft::$app->getElements()
                    ->getElementById((int) $elementId, null, $variables['order']->sourceSite);

                if ($element) {
                    $variables['elements'][] = $element;
                }
            }
        } else {
            $variables['elements'] = $variables['order']->getElements();
        }

        $originalElementIds = [];

        foreach($variables['order']->getElements() as $element) {
            array_push($originalElementIds, $element->id);
        }

        $variables['originalElementIds'] = implode(",", $originalElementIds);

        $variables['duplicateEntries'] = $this->checkOrderDuplicates($variables['elements']);

        $variables['chkDuplicateEntries'] = Translations::getInstance()->settings->chkDuplicateEntries;

        $variables['orderEntriesCount'] = count($variables['elements']);

        $variables['orderWordCount'] = 0;

        $variables['elementWordCounts'] = array();

        $variables['entriesCountBySection'] = array();

        $variables['entriesCountByElement'] = 0;
        $variables['entriesCountByElementCompleted'] = 0;

        foreach ($variables['elements'] as $element) {
            $drafts = Craft::$app->getDrafts()->getEditableDrafts($element);
            $tempDraftNames = [];

            foreach ($drafts as $draft) {
                $draftBehaviour = $draft->getBehavior("draft");
                $tempDraftNames[] = [
                    'value' => $draft->draftId,
                    'label' => $draftBehaviour->draftName
                ];
            }

            if (! empty($tempDraftNames)) {
                $variables['versionsByElementId'][$element->id] = $tempDraftNames;
            }

            $wordCount = Translations::$plugin->elementTranslator->getWordCount($element);

            $variables['elementWordCounts'][$element->id] = $wordCount;

            $variables['orderWordCount'] += $wordCount;

            if ($element instanceof GlobalSet) {
                $sectionName = 'Globals';
            } elseif ($element instanceof Category) {
                $sectionName = 'Category';
            } else {
                $sectionName = $element->section->name;
            }


            if (!isset($variables['entriesCountBySection'][$sectionName])) {
                $variables['entriesCountBySection'][$sectionName] = 0;
            }

            $variables['entriesCountBySection'][$sectionName]++;

            //Is an order being created or are we on the detail page?
            if (!isset($variables['inputSourceSite'])) {
                $variables['files'][$element->id] =
                    Translations::$plugin->fileRepository->getFilesByOrderId($variables['orderId'], $element->id);

                $variables['entriesCountByElement'] += count($variables['files'][$element->id]);

                $variables['fileCountByElementId'][$element->id] = count($variables['files'][$element->id]);

                $isElementPublished = true;

                foreach ($variables['files'][$element->id] as $file) {
                    if ($file->status !== Constants::ORDER_STATUS_PUBLISHED) {
                        $isElementPublished = false;
                    }

                    if ($element instanceof Entry) {
                        if ($file->status === Constants::ORDER_STATUS_PUBLISHED) {
                            $translatedElement = Craft::$app->getElements()->getElementById($element->id, null, $file->targetSite);

                            $variables['webUrls'][$file->id] = $translatedElement ? $translatedElement->url : $element->url;
                        } else {
                            $variables['webUrls'][$file->id] = $file->previewUrl;
                        }

                        if (
                            $file->status === Constants::ORDER_STATUS_COMPLETE ||
                            $file->status === Constants::ORDER_STATUS_PUBLISHED
                        ) {
                            $variables['entriesCountByElementCompleted']++;
                        }
                    } elseif ($element instanceof GlobalSet or $element instanceof Category) {
                        if (
                            $file->status === Constants::ORDER_STATUS_COMPLETE ||
                            $file->status === Constants::ORDER_STATUS_PUBLISHED
                        ) {
                            $variables['entriesCountByElementCompleted']++;
                        }
                    }

                    $variables['fileTargetSites'][$file->targetSite] =
                        (Craft::$app->getSites()->getSiteById($file->targetSite) ?
                        Craft::$app->getSites()->getSiteById($file->targetSite) :
                        [ 'language' => 'Deleted' ]);

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

        $variables['author'] = $user->getRememberedUsername();

        $variables['sites'] = Craft::$app->getSites()->getAllSiteIds();

        $targetSites = Craft::$app->getSites()->getAllSiteIds();

        $variables['sourceSites'] = array();

        foreach ($targetSites as $key => $site) {
            $site = Craft::$app->getSites()->getSiteById($site);
            $variables['sourceSites'][] = array(
                'value' => $site->id,
                'label' => $site->name . '(' . $site->language . ')'
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

        $variables['translatorId'] = !is_null($variables['order']->translator) ?
            $variables['order']->translator->id : null;

        if ($variables['translatorId']) {
            $variables['translator'] = Translations::$plugin->translatorRepository
                ->getTranslatorById($variables['translatorId']);
        }

        $variables['targetSiteCheckboxOptions'] = array();

        foreach ($targetSites as $key => $site) {
            $site = Craft::$app->getSites()->getSiteById($site);
            $variables['targetSiteCheckboxOptions'][] = array(
                'value' => $site->id,
                'label' => $site->name . ' (' . $site->language . ')'
            );
        }

        if (
            !is_null($variables['translator']) &&
            $variables['translator']->service !== Constants::TRANSLATOR_EXPORT_IMPORT
        ) {
            $translationService = Translations::$plugin->translatorFactory
                ->makeTranslationService(
                    $variables['translator']->service,
                    json_decode($variables['translator']->settings, true)
                );

            $translatorUrl = $translationService->getOrderUrl($variables['order']);
            $variables['translator_url'] = $translatorUrl;
        }

        $variables['isSubmitted'] = ($variables['order']->status !== Constants::ORDER_STATUS_NEW &&
            $variables['order']->status !== Constants::ORDER_STATUS_FAILED);
        // echo "<pre>";print_r(json_encode($variables['constants'], true));die;
        $this->renderTemplate('translations/orders/detail', $variables);
    }

    public function actionSaveOrder()
    {
        $backToNew = Craft::$app->getRequest()->getParam('flow') === "saveAndCreateNew";

        $this->requireLogin();
        $this->requirePostRequest();
        $isOrderUpdated = Craft::$app->getRequest()->getParam('submit') === "update";
        $isNewOrder = $isOrderUpdated ? false : true;

        $currentUser = Craft::$app->getUser()->getIdentity();

        // ? Logic to process version based orders WIP
        // $elementIdVersion = ltrim(Craft::$app->getRequest()->getParam('elementIdVersions'), ',');

        // if (trim($elementIdVersion)) {
        //     $elementIdVersion = explode(',', $elementIdVersion);
        //     $elementVersion = [];
        //     foreach (explode(',', $elementIdVersion) as $element) {
        //         $temp = explode('_', $element);
        //         $elementVersion[$temp[0]] = $temp[1];
        //     }
        // }

        if (!$currentUser->can('translations:orders:create')) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator
                ->translate('app', 'User does not have permission to perform this action.'));
            return;
        }

        $orderId = Craft::$app->getRequest()->getParam('id');

        if ($orderId) {
            $isNewOrder = false;
            $order = Translations::$plugin->orderRepository->getOrderById($orderId);
            
            if (!$order) {
                throw new HttpException(400, Translations::$plugin->translator->translate('app', 'Invalid Order'));
            }

            // Authenticate service
            $translator = $order->getTranslator();
            $service = $translator->service;
            $settings = $translator->getSettings();
            $authenticate = self::authenticateService($service, $settings);

            if (!$authenticate && $service == Constants::TRANSLATOR_ACCLARO) {
                $message = Translations::$plugin->translator->translate('app', 'Invalid API key');
                Craft::$app->getSession()->setError($message);
                return $this->redirect(Constants::URL_ORDER_DETAIL . $orderId, 302, true);
            }

            $queueOrders = Craft::$app->getSession()->get('queueOrders');
            if (!empty($queueOrders) && ($key = array_search($orderId, $queueOrders)) !== false) {
                if (
                    Craft::$app->getQueue()->status($key) == Queue::STATUS_WAITING ||
                    Craft::$app->getQueue()->status($key) == Queue::STATUS_RESERVED
                ) {
                    Craft::$app->getSession()->setError('This order is currently being processed.');
                    return $this->redirect(Constants::URL_ORDERS , 302, true);
                } else {
                    unset($queueOrders[$key]);
                    Craft::$app->getSession()->set('queueOrders', $queueOrders);
                }
            }
        } else {
            $sourceSite = Craft::$app->getRequest()->getParam('sourceSiteSelect');

            if ($sourceSite && !Translations::$plugin->siteRepository->isSiteSupported($sourceSite)) {
                throw new HttpException(400, Translations::$plugin->translator
                    ->translate('app', 'Source site is not supported'));
            }

            $order = Translations::$plugin->orderRepository->makeNewOrder($sourceSite);

            $order->logActivity(Translations::$plugin->translator->translate('app', 'Order Created'));
        }

        $job = '';

        try {
            $targetSites = Craft::$app->getRequest()->getParam('targetSites');

            if (! is_array($targetSites)) {
                $targetSites = explode(",", str_replace(", ", ",", $targetSites));
            }

            if ($targetSites === '*') {
                $targetSites = Craft::$app->getSites()->getAllSiteIds();

                $source_site = Craft::$app->getRequest()->getParam('sourceSite');
                if (($key = array_search($source_site, $targetSites)) !== false) {
                    unset($targetSites[$key]);
                    $targetSites = array_values($targetSites);
                }
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

            $elementIds = Craft::$app->getRequest()->getParam('elements') ?
                Craft::$app->getRequest()->getParam('elements') : array();

            $order->elementIds = json_encode($elementIds);

            $entriesCount = 0;
            $wordCounts = array();

            // Authenticate service
            $translator = $order->getTranslator();
            $service = $translator->service;
            $settings = $translator->getSettings();
            $authenticate = self::authenticateService($service, $settings);

            if (!$authenticate && $service == Constants::TRANSLATOR_ACCLARO) {
                $message = Translations::$plugin->translator->translate('app', 'Invalid API key');
                Craft::$app->getSession()->setError($message);
                return $this->redirect(Constants::URL_ORDER_CREATE , 302, true);
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
                            Translations::$plugin->translator->translate(
                                'app',
                                "The target site(s) selected are not available for the entry “%s”. \
                                Please check your settings in Settings > Sections > %s to change this entry's \
                                target sites."
                            ),
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

            $success = Craft::$app->getElements()->saveElement($order, true, true, false);

            if (!$success) {
                Craft::error('[' . __METHOD__ . '] Couldn’t save the order', 'translations');
                Craft::$app->getSession()->setNotice(
                    Translations::$plugin->translator->translate('app', 'Error saving Order')
                );
            } else {
                if (Craft::$app->getRequest()->getParam('submit') || Craft::$app->getRequest()->getParam('flow')) {
                    // Check supported languages for order service
                    if ($order->getTranslator()->service !== Constants::TRANSLATOR_EXPORT_IMPORT) {
                        $translationService = Translations::$plugin->translatorFactory->makeTranslationService(
                            $order->getTranslator()->service,
                            $order->getTranslator()->getSettings()
                        );

                        if ($translationService->getLanguages()) {
                            $sourceLanguage = Translations::$plugin->siteRepository
                                ->normalizeLanguage(Craft::$app->getSites()->getSiteById($order->sourceSite)->language);
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
                                        'language' => $site->name . ' (' . $site->language . ')'
                                    );
                                }

                                if (!in_array($language, $supportedLanguagePairs)) {
                                    $unsupported = true;
                                    $unsupportedLangs[] = array(
                                        'language' => $site->name . ' (' . $site->language . ')'
                                    );
                                }
                            }

                            if ($unsupported || !empty($unsupportedLangs)) {
                                $order->status = Constants::ORDER_STATUS_FAILED;
                                $success = Craft::$app->getElements()->saveElement($order);
                                if (!$success) {
                                    Craft::error('[' . __METHOD__ . '] Couldn’t save the order', 'translations');
                                }
                                Craft::$app->getSession()->setError(
                                    Translations::$plugin->translator->translate(
                                        'app',
                                        'The following language pair(s) are not supported: ' . implode(
                                            ', ',
                                            array_column($unsupportedLangs, 'language')
                                        ) . ' Contact Acclaro for assistance.'
                                    )
                                );
                                return $this->redirect(Constants::URL_ORDER_DETAIL . $order->id);
                            }
                        }
                    }
                    // ? Save Json File
                    if ($isNewOrder || $isOrderUpdated) {
                        if ($isOrderUpdated) {
                            Translations::$plugin->fileRepository->deleteByOrderId($order->id);
                        }
                        $success = Translations::$plugin->fileRepository->createOrderFiles($order, $wordCounts);

                        if (! $success) {
                            Craft::error('[' . __METHOD__ . '] Couldn’t create the order file', 'translations');
                            Craft::$app->getSession()->setError(
                                Translations::$plugin->translator->translate('app', 'Error saving order.')
                            );
                        } else {
                            $order->status = Constants::ORDER_STATUS_IN_PROGRESS;
                            $order->dateOrdered = new DateTime();

                            $success = Craft::$app->getElements()->saveElement($order, true, true, false);

                            if (! $success) {
                                Craft::error('[' . __METHOD__ . '] Couldn’t save the order', 'translations');
                            } else {
                                Craft::$app->getSession()->setNotice(
                                    Translations::$plugin->translator->translate('app', 'Order Saved.')
                                );
                            }
                        }
                    }

                    if ($order->getTranslator()->service !== Constants::TRANSLATOR_EXPORT_IMPORT) {
                        // Sending Order To Acclaro
                        $translationService = Translations::$plugin->translatorFactory->makeTranslationService(
                            $order->getTranslator()->service,
                            $order->getTranslator()->getSettings()
                        );

                        $order->wordCount = array_sum($wordCounts);
                        $totalWordCount = ($order->wordCount * count($order->getTargetSitesArray()));
    
                        if ($totalWordCount > self::WORDCOUNT_LIMIT) {
                            $job = $translationService->SendOrder($order);
    
                            $queueOrders = Craft::$app->getSession()->get('queueOrders');
                            $queueOrders[$job] = $order->id;
                            Craft::$app->getSession()->set('queueOrders', $queueOrders);
                        } else {
                            $job =  null;
                            Translations::$plugin->orderRepository->sendAcclaroOrder(
                                $order,
                                $translator->getSettings()
                            );
                        }
                    }

                    $order->logActivity(sprintf(
                        Translations::$plugin->translator->translate('app', 'Order Submitted to %s'),
                        $order->translator->getName()
                    ));
                } else {
                    Craft::$app->getSession()->setNotice(
                        Translations::$plugin->translator->translate('app', 'Order Saved.')
                    );
                }
            }
        } catch (Exception $e) {
            Craft::error('[' . __METHOD__ . '] Couldn’t save the order. Error: ' . $e->getMessage(), 'translations');
            $order->status = Constants::ORDER_STATUS_FAILED;
            Craft::$app->getElements()->saveElement($order);
        }

        $redirectUrl = $backToNew ? Constants::URL_ORDER_CREATE : null;

        if ($job) {
            if ($order->getTranslator()->service == Constants::TRANSLATOR_EXPORT_IMPORT) {
                $params = [
                    'id' => (int) $job,
                    'notice' => 'Done creating translation drafts',
                    'url' => $redirectUrl ?: Constants::URL_ORDER_DETAIL . $order->id
                ];
                Craft::$app->getView()
                    ->registerJs(
                        '$(function(){ Craft.Translations.trackJobProgressById(true, false, '
                        . json_encode($params) . '); });'
                    );
            } else {
                Craft::$app->getSession()->setNotice(
                    Translations::$plugin->translator->translate(
                        'app',
                        'Sending order to Acclaro, please refresh your Orders once complete'
                    )
                );
            }
        } elseif (is_null($job)) {
            Craft::$app->getSession()->setNotice(
                Translations::$plugin->translator->translate('app', 'New order created: ' . $order->title)
            );
            return $this->redirect($redirectUrl ?: Constants::URL_ORDER_DETAIL . $order->id);
        } else {
            return $this->redirect($redirectUrl ?: Constants::URL_ORDERS, 302, true);
        }
    }

    public function actionSyncOrder()
    {
        $this->requireLogin();
        $this->requirePostRequest();
        if (!Translations::$plugin->userRepository->userHasAccess('translations:orders:import')) {
            return $this->redirect(Constants::URL_TRANSLATIONS, 302, true);
        }

        $orderId = Craft::$app->getRequest()->getParam('orderId');

        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        // Authenticate service
        $translator = $order->getTranslator();
        $service = $translator->service;
        $settings = $translator->getSettings();
        $authenticate = self::authenticateService($service, $settings);
        
        if (!$authenticate && $service == Constants::TRANSLATOR_ACCLARO) {
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
                        'url' => Constants::URL_ORDER_DETAIL . $order->id
                    ];
                    Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
                } else {
                    Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app',  'Cannot sync order '. $order->title));
                    return $this->redirect(Constants::URL_ORDER_DETAIL . $order->id, 302, true);
                }
            } else {
                Translations::$plugin->orderRepository->syncOrder($order);
                Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app',  'Done syncing order '. $order->title));
                return $this->redirect(Constants::URL_ORDER_DETAIL . $order->id, 302, true);
            }
        }
    }

    /**
     * @param $elements
     * @return array
     */
    public function checkOrderDuplicates($elements)
    {
        $orderIds = [];
        foreach ($elements as $element) {
            $orders = Translations::$plugin->fileRepository->getOrdersByElement($element->id);
            if ($orders) {
                $orderIds[$element->id] = $orders;
            }
        }

        return $orderIds;
    }

    public function actionSaveDraftAndPublish()
    {
        $this->requireLogin();
        $this->requirePostRequest();
        $action = Craft::$app->getRequest()->getParam('submit');

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser->can('translations:orders:create')) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator
                ->translate('app', 'User does not have permission to perform this action.'));
            return;
        }

        $orderId = Craft::$app->getRequest()->getParam('orderId');
        $elementIds = Craft::$app->getRequest()->getParam('elementIds');
        $elementIds = explode(",", $elementIds);
        $fileIds = Craft::$app->getRequest()->getParam('fileIds');
        $fileIds = explode(",", $fileIds);

        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        $wordCounts = [];
        $allElementIds = [];

        foreach ($order->getElements() as $element) {
            array_push($allElementIds, $element->id);
            $wordCounts[$element->id] = Translations::$plugin->elementTranslator->getWordCount($element);
        }
        
        if ($action == "draft-all") {
            $elementIds = $allElementIds;
            $action = "draft";
        }

        $totalWordCount = ($order->wordCount * count($order->getTargetSitesArray()));

        $job = '';

        try {
            if ($totalWordCount > self::WORDCOUNT_LIMIT) {
                $job = Craft::$app->queue->push(new CreateDrafts([
                    'description' => 'Creating translation drafts',
                    'orderId' => $order->id,
                    'wordCounts' => $wordCounts,
                    'publish' => $action == 'publish' ? true : false,
                    'elementIds' => $elementIds,
                    'fileIds' => $fileIds,
                ]));

                $queueOrders = Craft::$app->getSession()->get('queueOrders');
                $queueOrders[$job] = $order->id;
                Craft::$app->getSession()->set('queueOrders', $queueOrders);
            } else {
                $job =  null;
                Translations::$plugin->draftRepository->createOrderDrafts(
                    $order->id, $wordCounts, null, $action == 'publish' ? true : false,
                    $elementIds, $fileIds
                );
            }
        } catch (Exception $e) {
            Craft::error( '['. __METHOD__ .'] Couldn’t save the draft. Error: '.$e->getMessage(), 'translations' );
            $order->status = 'failed';
            Craft::$app->getElements()->saveElement($order);
            return;
        }

        if ($job) {
            $params = [
                'id' => (int) $job,
                'notice' => $action == "draft" ? 'Translation drafts saved' : 'Entries published',
                'url' => Constants::URL_ORDER_DETAIL . $order->id
            ];
            Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
        } else if(is_null($job)) {
            Craft::$app->getSession()->setNotice(
                Translations::$plugin->translator->translate(
                    'app', $action == "draft" ? 'Translation drafts saved' : 'Entries published'
                )
            );
        }
    }

    public function actionGetFileDiff()
    {
        $variables = Craft::$app->getRequest()->resolve()[1];
        $success = false;
        $error = null;
        $data = [];

        $fileId = Craft::$app->getRequest()->getParam('fileId');
        if (!$fileId) {
            $error = "FileId not found.";
        } else {
            $file = Translations::$plugin->fileRepository->getFileById($fileId);
            $error = "File not found.";
            if ($file && ($file->status == 'complete' || $file->status == 'published')) {
                try {
                    // Current entries XML
                    $sourceContent = Translations::$plugin->elementTranslator->getTargetData($file->source, true);
    
                    // Translated file XML
                    $targetContent = Translations::$plugin->elementTranslator->getTargetData($file->target, true);
                    foreach ($sourceContent as $key => $value) {
                        $data['diff'][$key] = [
                            'source' => $value ?? '',
                            'target' => $targetContent[$key] ?? '',
                        ];
                    }

                    Craft::debug($data['diff'], "bhutarget");
                    $data['source'] = $sourceContent;
                    $data['target'] = $targetContent;
                    $error = null;
                    $success = true;
                } catch(Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->asJson([
            'success' => $success,
            'data' => $data,
            'error' => $error
        ]);
    }

    public function actionSaveOrderDraft()
    {
        // TODO: need to add logic later
        Craft::$app->getSession()->setError(
            Translations::$plugin->translator->translate('app', 'Save Order Draft WIP.')
        );
    }
    
    public function actionDeleteOrderDraft()
    {
        // TODO: need to add logic later
        Craft::$app->getSession()->setError(
            Translations::$plugin->translator->translate('app', 'Delete Order Draft WIP.')
        );
    }
}
