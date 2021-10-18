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

use Craft;
use DateTime;
use Exception;
use craft\queue\Queue;
use craft\web\Controller;
use craft\elements\Entry;
use yii\web\HttpException;
use craft\helpers\UrlHelper;
use acclaro\translations\services\App;
use acclaro\translations\services\job\acclaro\SendOrder;
use acclaro\translations\Translations;
use acclaro\translations\Constants;
use acclaro\translations\services\AcclaroService;
use acclaro\translations\services\job\SyncOrder;
use acclaro\translations\services\job\CreateDrafts;
use acclaro\translations\services\job\ApplyDrafts;
use acclaro\translations\services\job\DeleteDrafts;
use acclaro\translations\services\translator\AcclaroTranslationService;
use Dotenv\Regex\Success;
use Error;

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
    protected $allowAnonymous = true;

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

        $variables['context'] = 'index';

        $this->renderTemplate('translations/orders/_index', $variables);
    }

    // Detail Page Methods
    // =========================================================================

    public function actionOrderDetail(array $variables = array())
    {
        $variables = Craft::$app->getRequest()->resolve()[1];

        $variables['isProcessing'] = Craft::$app->getRequest()->getParam('isProcessing') ?? null;

        if ($variables['isProcessing']) {
            $submitAction = Craft::$app->getRequest()->getParam('submit');
            if ($submitAction == "draft" || $submitAction == "publish") {
                $variables['isProcessing'] = $submitAction;
            }
            if (Craft::$app->getSession()->get('importQueued')) {
                Craft::$app->getSession()->set('importQueued', "0");
            } else {
                $variables['isProcessing'] = null;
            }
        }

        $variables['isChanged'] = Craft::$app->getRequest()->getQueryParam('changed') ?? null;

        $variables['orderId'] = $variables['orderId'] ?? null;
        $variables['tagGroup'] = Craft::$app->getTags()->getTagGroupByHandle(Constants::ORDER_TAG_GROUP_HANDLE);

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

            if ($orderElements= Craft::$app->getRequest()->getQueryParam('elements') ?? Craft::$app->getRequest()->getParam('elements')) {
                $newOrder->elementIds = json_encode($orderElements);
            }

            if ($orderTags= Craft::$app->getRequest()->getQueryParam('tags') ?? Craft::$app->getRequest()->getParam('tags')) {
                if (! is_array($orderTags)) {
                    $orderTags = explode(',', $orderTags);
                }
                $newOrder->tags = json_encode($orderTags);
            }

            if ($requestedDueDate= Craft::$app->getRequest()->getQueryParam('dueDate')) {
                $newOrder->requestedDueDate = $requestedDueDate;
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

        $variables['hasTags'] = false;
        if (! empty(json_decode($variables['order']->tags, true))) {
            $variables['hasTags'] = true;
            $variables['tags'] = [];

            foreach (json_decode($variables['order']->tags, true) as $tagId) {
                $variables['tags'][] = Craft::$app->getTags()->getTagById($tagId);
            }
        }

        $variables['orientation'] = Craft::$app->getLocale()->orientation;

        $variables['versionsByElementId'] = [];
        $variables['translatorOptions'] = Translations::$plugin->translatorRepository->getTranslatorOptions();

        $variables['elements'] = [];
        $variables['elementVersionMap'] = array();
        $originalElementIds = [];
        $finalElements = [];

        foreach ($variables['order']->getElements() as $element) {
            $finalElements[$element->id] = $element;
            if ($element->getIsDraft()) {
                $variables['elementVersionMap'][$element->getCanonicalId()] = $element->draftId;
                array_push($originalElementIds, $element->getCanonicalId());
            } else {
                $variables['elementVersionMap'][$element->id] = "current";
                array_push($originalElementIds, $element->id);
            }
        }

        if ($variables['inputElements']) {
            foreach ($variables['inputElements'] as $elementId) {
                $element = Craft::$app->getElements()
                    ->getElementById((int) $elementId, null, $variables['order']->sourceSite);

                if ($element) {
                    $variables['elements'][] = $element;
                    if (! array_key_exists($element->id, $finalElements)) $finalElements[$element->id] = $element;
                    if (! isset($variables['elementVersionMap'][$element->id])) {
                        $variables['elementVersionMap'][$element->id] = 'current';
                    }
                }
            }
        } else {
            foreach ($variables['order']->getElements() as $element) {
                if ($element->getIsDraft()) $element = $element->getCanonical();
                $variables['elements'][] = $element;
            }
        }

        $variables['originalElementIds'] = implode(",", $originalElementIds);

        $variables['duplicateEntries'] = Translations::$plugin->orderRepository->checkOrderDuplicates($variables['elements']);

        // Remove current order id from duplicates
        if (! empty($duplicates = $variables['duplicateEntries']) && $variables['orderId']) {
            foreach ($variables['elements'] as $element) {
                if (array_key_exists($element->id, $duplicates)) {
                    $orderIds = array_diff($duplicates[$element->id], [$variables['orderId']]);
                    if (! empty($orderIds)) {
                        $variables['duplicateEntries'][$element->id] = $orderIds;
                    } else {
                        unset($variables['duplicateEntries'][$element->id]);
                    }
                }
            }
        }

        $variables['chkDuplicateEntries'] = Translations::getInstance()->settings->chkDuplicateEntries;

        $variables['orderWordCount'] = 0;

        $variables['elementWordCounts'] = array();

        $variables['entriesCountByElement'] = 0;
        $variables['entriesCountByElementCompleted'] = 0;
        $variables['translatedFiles'] = [];

        foreach ($finalElements as $key => $element) {
            $drafts = Craft::$app->getDrafts()->getEditableDrafts($element);
            $tempElement = $element;
            $element = $element->getIsDraft() ? $element->getCanonical(true) : $element;
            $tempDraftNames = [];
            foreach ($drafts as $draft) {
                if (Translations::$plugin->draftRepository->isTranslationDraft($draft->draftId)) {
                    continue;
                }
                $draftBehaviour = $draft->getBehavior("draft");
                $tempDraftNames[] = [
                    'value' => $draft->draftId,
                    'label' => $draftBehaviour->draftName
                ];
            }

            if (! empty($tempDraftNames)) {
                $variables['versionsByElementId'][$element->id] = $tempDraftNames;
            }

            $wordCount = Translations::$plugin->elementTranslator->getWordCount($tempElement);

            $variables['elementWordCounts'][$element->id] = $wordCount;

            $variables['orderWordCount'] += $wordCount;

            //Is an order being created or are we on the detail page?
            if (!isset($variables['inputSourceSite']) || ($variables['isChanged'] && $variables['orderId'])) {
                $variables['files'][$element->id] =
                    Translations::$plugin->fileRepository->getFilesByOrderId($variables['orderId'], $tempElement->id);

                $variables['entriesCountByElement'] += count($variables['files'][$element->id]);

                $isElementPublished = true;

                foreach ($variables['files'][$element->id] as $file) {
                    $translatedElement = Craft::$app->getElements()->getElementById($element->id, null, $file->targetSite);
                    if ($file->status !== Constants::FILE_STATUS_PUBLISHED) {
                        $isElementPublished = false;
                    }

                    if ($file->status === Constants::FILE_STATUS_COMPLETE) {
                        $variables['translatedFiles'][$file->id] = Translations::$plugin->orderRepository->getFileTitle($file);
                    } else if ($file->status === Constants::FILE_STATUS_PUBLISHED) {
                        $variables['translatedFiles'][$file->id] = $translatedElement->title;
                    } else {
                        $variables['translatedFiles'][$file->id] = $tempElement->title;
                    }

                    if ($element instanceof Entry) {
                        if ($file->status === Constants::FILE_STATUS_PUBLISHED) {

                            $variables['webUrls'][$file->id] = $translatedElement ? $translatedElement->url : $element->url;
                        } else {
                            $variables['webUrls'][$file->id] = $file->previewUrl ?? ($translatedElement ? $translatedElement->url : $element->url);
                        }
                    }

                    if (
                        $file->status === Constants::FILE_STATUS_COMPLETE ||
                        $file->status === Constants::FILE_STATUS_REVIEW_READY ||
                        $file->status === Constants::FILE_STATUS_PUBLISHED
                    ) {
                        $variables['entriesCountByElementCompleted']++;
                    }

                    $variables['fileTargetSites'][$file->targetSite] =
                        (Craft::$app->getSites()->getSiteById($file->targetSite) ?
                        Craft::$app->getSites()->getSiteById($file->targetSite) :
                        [ 'language' => 'Deleted' ]);

                    if (Craft::$app->getSites()->getSiteById($file->targetSite)) {
                        if ($file->status !== Constants::FILE_STATUS_PUBLISHED) {
                            $element = $tempElement;
                        }
                        $variables['fileUrls'][$file->id] = Translations::$plugin->urlGenerator->generateFileUrl($element, $file);
                    }
                    $variables['isElementPublished'][$element->id] = $isElementPublished;
                }
            }
        }

        $totalWordCount = ($variables['orderWordCount'] * count($variables['order']->getTargetSitesArray()));

        if ($totalWordCount <= Constants::WORD_COUNT_LIMIT || Craft::$app->getSession()->get('fileImportError') ?? null) {
            Craft::$app->getSession()->set('fileImportError', false);
            $variables['isProcessing'] = null;
        }

        $variables['entriesCountByElement'] -=  $variables['entriesCountByElementCompleted'];

        if (!$variables['translatorOptions']) {
            $variables['translatorOptions'] = array('' => Translations::$plugin->translator->translate('app', 'No Translators'));
        } else {
            foreach ($variables['translatorOptions'] as $translatorId => $val) {
                $translator = Translations::$plugin->translatorRepository->getTranslatorById($translatorId);
                if ($translator->service === Constants::TRANSLATOR_DEFAULT) {
                    $variables['defaultTranslatorId'] = $translatorId;
                }
            }
        }

        $userId = $variables['order']->ownerId ?? Craft::$app->getUser()->id;
        $user = Craft::$app->getUsers()->getUserById($userId);

        $variables['owners'] = array(
            $user->id => $user->username,
        );

        $variables['author'] = $user;

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
        $variables['isEditable'] =  true;
        $variables['orderRecentStatus'] = null;

        $variables['translatorId'] = !is_null($variables['order']->translator) ?
            $variables['order']->translator->id : null;

        if ($variables['translatorId']) {
            $variables['translator'] = Translations::$plugin->translatorRepository
                ->getTranslatorById($variables['translatorId']);

            $variables['orderRecentStatus'] = $variables['order']->status;
            if (
                $variables['orderRecentStatus'] === Constants::ORDER_STATUS_PUBLISHED &&
                $variables['translator']->service !== Constants::TRANSLATOR_DEFAULT
            ) $variables['isEditable'] = false;
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
            $variables['translator']->service !== Constants::TRANSLATOR_DEFAULT
        ) {
            $translationService = Translations::$plugin->translatorFactory->makeTranslationService(
                $variables['translator']->service,
                json_decode($variables['translator']->settings, true)
            );

            $translatorUrl = $translationService->getOrderUrl($variables['order']);
            $variables['translator_url'] = $translatorUrl;
            $orderStatus = $translationService->getOrderStatus($variables['order']);
            if ($variables['order']->status == Constants::ORDER_STATUS_CANCELED) {
                $variables['isEditable'] = false;
            }
            if ($orderStatus === Constants::ORDER_STATUS_COMPLETE) {
                $variables['isEditable'] = false;
                if ($variables['order']->status === Constants::ORDER_STATUS_PUBLISHED) {
                    $variables['orderRecentStatus'] = Constants::ORDER_STATUS_PUBLISHED;
                } else {
                    $variables['orderRecentStatus'] = $orderStatus;
                }
            }
        }

        $variables['isSubmitted'] = ($variables['order']->status !== Constants::ORDER_STATUS_NEW &&
            $variables['order']->status !== Constants::ORDER_STATUS_FAILED);

        $this->renderTemplate('translations/orders/_detail', $variables);
    }

    /**
     * Save order
     *
     * @return void
     */
    public function actionSaveOrder()
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $flow = explode("_", Craft::$app->getRequest()->getParam('flow'));
        $backToNew = count($flow) > 1 ? true : false;

        $isOrderUpdated = $flow[0] === "update";
        $isNewOrder = $flow[0] === "save";

        $currentUser = Craft::$app->getUser()->getIdentity();

        $elementVersions = trim(Craft::$app->getRequest()->getParam('elementVersions'), ',') ?? array();
        $orderTags = Craft::$app->getRequest()->getParam('tags');

        if (!$currentUser->can('translations:orders:create')) {
            return $this->asJson(["success" => false, "message" => "User does not have permission to perform this action."]);
        }

        $orderId = Craft::$app->getRequest()->getParam('id');
        $sourceSite = Craft::$app->getRequest()->getParam('sourceSiteSelect');

        if ($orderId) {
            $order = Translations::$plugin->orderRepository->getOrderById($orderId);
            
            if (!$order) {
                return $this->asJson(["success" => false, "message" => "Invalid Order."]);
            }
            $isNewOrder = $order->status === Constants::ORDER_STATUS_NEW;
            if ($order->status === Constants::ORDER_STATUS_FAILED) {
                $order->status = Constants::ORDER_STATUS_IN_PROGRESS;
                $isOrderUpdated = true;
            }

            $sourceSite = $sourceSite ?: $order->sourceSite;
            // Authenticate service
            $translator = $order->getTranslator();
            $service = $translator->service;
            $settings = $translator->getSettings();
            $authenticate = self::authenticateService($service, $settings);

            if (!$authenticate && $service == Constants::TRANSLATOR_ACCLARO) {
                $message = Translations::$plugin->translator->translate('app', 'Invalid API key');
                return $this->asJson(["success" => false, "message" => $message]);
            }

            $queueOrders = Craft::$app->getSession()->get('queueOrders');
            if (!empty($queueOrders) && ($key = array_search($orderId, $queueOrders)) !== false) {
                if (
                    Craft::$app->getQueue()->status($key) == Queue::STATUS_WAITING ||
                    Craft::$app->getQueue()->status($key) == Queue::STATUS_RESERVED
                ) {
                    return $this->asJson(["success" => false, "message" => "This order is currently being processed."]);
                } else {
                    unset($queueOrders[$key]);
                    Craft::$app->getSession()->set('queueOrders', $queueOrders);
                }
            }
        } else {
            if ($sourceSite && !Translations::$plugin->siteRepository->isSiteSupported($sourceSite)) {
                return $this->asJson(["success" => false, "message" => "Source site is not supported."]);
            }

            $order = Translations::$plugin->orderRepository->makeNewOrder($sourceSite);

            $order->logActivity(Translations::$plugin->translator->translate('app', 'Order Created'));
        }

        $job = '';
        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $targetSites = Craft::$app->getRequest()->getParam('targetSites');

            if ($targetSites === '*') {
                $targetSites = Craft::$app->getSites()->getAllSiteIds();

                $source_site = Craft::$app->getRequest()->getParam('sourceSite');
                if (($key = array_search($source_site, $targetSites)) !== false) {
                    unset($targetSites[$key]);
                    $targetSites = array_values($targetSites);
                }
            }

            if (! is_array($targetSites)) {
                $targetSites = explode(",", str_replace(", ", ",", $targetSites));
            }

            $elementIds = [];
            if ($elementVersions) {
                $elementVersions = explode(',', $elementVersions);
                foreach ($elementVersions as $element) {
                    $temp = explode('_', $element);
                    if ($temp[1] != "current") {
                        $draftElement = Translations::$plugin->elementRepository->getElementByDraftId($temp[1], $sourceSite);
                        array_push($elementIds, $draftElement->id);
                    } else {
                        array_push($elementIds, $temp[0]);
                    }
                }
            } else {
                $elementIds = Craft::$app->getRequest()->getBodyParam('currentElementIds') ?? '';
                $elementIds = explode(',', $elementIds);
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

            $orderTags = Craft::$app->getRequest()->getParam('tags') ?? array();
            $orderTagIds = array();

            foreach ($orderTags as $tagId) {
                $tag = Craft::$app->getTags()->getTagById($tagId);
                if ($tag) {
                    array_push($orderTagIds, $tag->id);
                }
            }
            $tagDiff = array_diff($order->tags ? json_decode($order->tags, true) : [], $orderTagIds);

            $order->tags = json_encode($orderTagIds);
            $order->title = $title;
            $order->sourceSite = $sourceSite;
            $order->targetSites = $targetSites ? json_encode($targetSites) : null;

            if ($requestedDueDate) {
                if (!is_array($requestedDueDate)) {
                    $orderDueDate = DateTime::createFromFormat('n/j/Y', $requestedDueDate);
                } else {
                    if (isset($requestedDueDate['date']) && $requestedDueDate['date'] != '') {
                        $orderDueDate = DateTime::createFromFormat('n/j/Y', $requestedDueDate['date']);
                    }
                }
            }
            $order->requestedDueDate = $orderDueDate ?? null;

            $order->comments = Craft::$app->getRequest()->getParam('comments');
            $order->translatorId = $translatorId;

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
                $transaction->rollBack();
                return $this->asJson(["success" => false, "message" => $message]);
            }

            // Calculating total Entries and their WordCount
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
                        $transaction->rollBack();
                        return $this->asJson(["success" => false, "message" => $message]);
                    }
                }
            }

            $order->entriesCount = $entriesCount;
            $order->wordCount = array_sum($wordCounts);

            // Manual Translation will make orders 'in progress' status after creation
            $success = Craft::$app->getElements()->saveElement($order, true, true, false);

            if (!$success) {
                Craft::error('[' . __METHOD__ . '] Couldn’t save the order', 'translations');
                $transaction->rollBack();
                return $this->asJson(["success" => false, "message" => "Error saving Order."]);
            } else {
                if (Craft::$app->getRequest()->getParam('submit') || Craft::$app->getRequest()->getParam('flow')) {
                    // Check supported languages for order service
                    if ($order->getTranslator()->service !== Constants::TRANSLATOR_DEFAULT) {
                        $translationService = Translations::$plugin->translatorFactory->makeTranslationService(
                            $order->getTranslator()->service,
                            $order->getTranslator()->getSettings()
                        );

                        if ($translationService->getLanguages()) {
                            $sourceSite = Craft::$app->getSites()->getSiteById($order->sourceSite);
                            $sourceLanguage = Translations::$plugin->siteRepository
                                ->normalizeLanguage($sourceSite->language);
                            $unsupported = false;
                            $unsupportedLangs = [];
                            $supportedLanguagePairs = [];
                            $sourceSlug = "$sourceSite->name ($sourceSite->language)";

                            foreach ($translationService->getLanguagePairs($sourceLanguage) as $key => $languagePair) {
                                $supportedLanguagePairs[] = $languagePair->target['code'];
                            }

                            foreach (json_decode($order->targetSites) as $key => $siteId) {
                                $site = Craft::$app->getSites()->getSiteById($siteId);
                                $language = Translations::$plugin->siteRepository->normalizeLanguage($site->language);
                                $targetSlug = "$site->name ($site->language)";

                                if (!in_array($language, array_column($translationService->getLanguages(), 'code'))) {
                                    $unsupported = true;
                                    $unsupportedLangs[] = array(
                                        'language' => "$sourceSlug to $targetSlug"
                                    );
                                    continue;
                                }

                                if (!in_array($language, $supportedLanguagePairs)) {
                                    $unsupported = true;
                                    $unsupportedLangs[] = array(
                                        'language' => "$sourceSlug to $targetSlug"
                                    );
                                }
                            }

                            if ($unsupported || !empty($unsupportedLangs)) {
                                $transaction->rollBack();
                                return $this->asJson(
                                    ["success" => false,
                                    "message" => "The following language pair(s) are not supported by "
                                    .ucfirst($order->getTranslator()->service).": "
                                    .implode(", ", array_column($unsupportedLangs, "language"))
                                    ]
                                );
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
                            $transaction->rollBack();

                            return $this->asJson(["success" => false, "message" => "Error saving order."]);
                        } else {
                            $order->status = Constants::ORDER_STATUS_IN_PROGRESS;
                            $order->dateOrdered = new DateTime();

                            $success = Craft::$app->getElements()->saveElement($order, true, true, false);

                            if (! $success) {
                                Craft::error('[' . __METHOD__ . '] Couldn’t save the order', 'translations');
                                $transaction->rollBack();
                                return $this->asJson(["success" => false, "message" => "Couldn’t save the order."]);
                            }
                        }
                    }

                    // Sending Order To Acclaro
                    if ($order->getTranslator()->service !== Constants::TRANSLATOR_DEFAULT) {
                        $translationService = Translations::$plugin->translatorFactory->makeTranslationService(
                            $order->getTranslator()->service,
                            $order->getTranslator()->getSettings()
                        );

                        if (! empty($tagDiff)) {
                            Translations::$plugin->orderRepository->deleteOrderTags($order, $translator->getSettings(), $tagDiff);
                        }

                        $order->wordCount = array_sum($wordCounts);
                        $totalWordCount = ($order->wordCount * count($order->getTargetSitesArray()));
    
                        if ($totalWordCount > Constants::WORD_COUNT_LIMIT) {
                            $job = $translationService->SendOrder($order);
    
                            $queueOrders = Craft::$app->getSession()->get('queueOrders');
                            $queueOrders[$job] = $order->id;
                            Craft::$app->getSession()->set('importQueued', 1);
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
                }
            }
            $transaction->commit();
        } catch (Exception $e) {
            Craft::error('[' . __METHOD__ . '] Couldn’t save the order. Error: ' . $e->getMessage(), 'translations');
            $transaction->rollBack();
            return $this->asJson(["success" => false, "message" => "Couldn’t save the order. Error: ".$e->getMessage()]);
        }

        $redirectUrl = $backToNew ? Constants::URL_ORDER_CREATE : null;

        if ($job) {
            Craft::$app->getSession()->setNotice(
                Translations::$plugin->translator->translate(
                    'app',
                    'Sending order to Acclaro, refer queue for updates'
                )
            );
            $url = Translations::$plugin->urlHelper->cpUrl($redirectUrl ?: Constants::URL_ORDER_DETAIL . $order->id);
            return $this->asJson(["success" => true, "message" => "", "url" => $url]);
        } else {
            Craft::$app->getSession()->setNotice(
                Translations::$plugin->translator->translate('app', 'New order created: ' . $order->title)
            );
            $url = Translations::$plugin->urlHelper->cpUrl($redirectUrl ?: Constants::URL_ORDER_DETAIL . $order->id);
            return $this->asJson(["success" => true, "message" => "", "url" => $url]);
        }
    }

    /**
     * Clone an existing order
     *
     * @return void
     */
    public function actionCloneOrder()
    {
        $variables = $variables = Craft::$app->getRequest()->resolve()[1];
        $data = Craft::$app->getRequest()->getBodyParams();

        $variables['isProcessing'] = null;
        $variables['isChanged'] = null;
        $variables['isEditable'] = true;
        $variables['isSubmitted'] = null;
        $variables['selectedSubnavItem'] = 'orders';
        $variables['orderId'] = null;
        $variables['tagGroup'] = Craft::$app->getTags()->getTagGroupByHandle(Constants::ORDER_TAG_GROUP_HANDLE);
        $elementVersions = trim(Craft::$app->getRequest()->getParam('elementVersions'), ',') ?? array();

        $elementIds = [];
        if ($elementVersions) {
            $elementVersions = explode(',', $elementVersions);
            foreach ($elementVersions as $element) {
                $temp = explode('_', $element);
                if ($temp[1] != "current") {
                    $draftElement = Translations::$plugin->elementRepository->getElementByDraftId($temp[1], $data['sourceSiteSelect']);
                    array_push($elementIds, $draftElement->id);
                } else {
                    array_push($elementIds, $temp[0]);
                }
            }
        }
        $variables['elementIds'] = json_encode($elementIds);
        $variables['sourceSite'] = $data['sourceSiteSelect'];

        if (!empty($data['sourceSiteSelect'])) {
            if (!Translations::$plugin->userRepository->userHasAccess('translations:orders:create')) {
                return $this->redirect(Constants::URL_ENTRIES, 302, true);
            }
        }

        $requestedDueDate = null;
        if ($data['requestedDueDate']['date'] ?? null) {
            $requestedDueDate = DateTime::createFromFormat('n/j/Y', $data['requestedDueDate']['date'])->format("Y-n-j");
        }

        $newOrder = Translations::$plugin->orderRepository->makeNewOrder($variables['sourceSite']);

        $newOrder->title = $data['title'] ?? '';
        $newOrder->targetSites = json_encode($data['targetSites'] ?? '');
        $newOrder->elementIds = $variables['elementIds'];
        $newOrder->comments = $data['comments'] ?? '';
        $newOrder->requestedDueDate = $requestedDueDate ?? '';
        $newOrder->translatorId = $data['translatorId'] ?? '';

        $variables['order'] = $newOrder;

        $variables['sourceSiteObject'] = Craft::$app->getSites()->getSiteById($variables['sourceSite']);
        $variables['translatorId'] = $variables['order']['translatorId'];
        $variables['sites'] = Craft::$app->getSites()->getAllSiteIds();
        
        $userId = Craft::$app->getUser()->id;
        $user = Craft::$app->getUsers()->getUserById($userId);

        $variables['owners'] = array(
            $user->id => $user->username,
        );

        $variables['author'] = $user;

        $variables['elements'] = [];
        $variables['elementVersionMap'] = array();

        foreach ($variables['order']->getElements() as $element) {
            if ($element->getIsDraft()) {
                $variables['elementVersionMap'][$element->getCanonicalId()] = $element->draftId;
            } else {
                $variables['elementVersionMap'][$element->id] = "current";
            }
        }

        foreach ($data['elements'] as $elementId) {
            $element = Craft::$app->getElements()
                ->getElementById((int) $elementId, null, $variables['order']->sourceSite);

            if ($element) {
                $variables['elements'][] = $element;
                if (! isset($variables['elementVersionMap'][$element->id])) {
                    $variables['elementVersionMap'][$element->id] = 'current';
                }
            }
        }

        $variables['originalElementIds'] = '';

        $variables['duplicateEntries'] = Translations::$plugin->orderRepository->checkOrderDuplicates($variables['elements']);

        $variables['chkDuplicateEntries'] = Translations::getInstance()->settings->chkDuplicateEntries;

        $variables['orderWordCount'] = 0;

        $variables['elementWordCounts'] = array();

        $variables['entriesCountByElement'] = 0;
        $variables['entriesCountByElementCompleted'] = 0;
        $variables['translatedFiles'] = [];

        foreach ($variables['elements'] as $element) {
            $drafts = Craft::$app->getDrafts()->getEditableDrafts($element);
            $tempDraftNames = [];
            foreach ($drafts as $draft) {
                if (Translations::$plugin->draftRepository->isTranslationDraft($draft->draftId)) {
                    continue;
                }
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
        }

        $allSites = Craft::$app->getSites()->getAllSiteIds();
        $variables['sourceSites'] = array();

        foreach ($allSites as $key => $site) {
            $site = Craft::$app->getSites()->getSiteById($site);
            $variables['sourceSites'][] = array(
                'value' => $site->id,
                'label' => $site->name . '(' . $site->language . ')'
            );
        }

        if (($key = array_search($variables['sourceSite'], $allSites)) !== false) {
            unset($allSites[$key]);
        }

        $variables['targetSites'] = array();
        $variables['targetSiteCheckboxOptions'] = array();

        foreach ($allSites as $key => $site) {
            $site = Craft::$app->getSites()->getSiteById($site);
            $variables['targetSiteCheckboxOptions'][] = array(
                'value' => $site->id,
                'label' => $site->name . ' (' . $site->language . ')'
            );
            $variables['targetSites'][] = $site;
        }

        $variables['translatorOptions'] = Translations::$plugin->translatorRepository->getTranslatorOptions();
        if (!$variables['translatorOptions']) {
            $variables['translatorOptions'] = array('' => Translations::$plugin->translator->translate('app', 'No Translators'));
        } else {
            foreach ($variables['translatorOptions'] as $translatorId => $val) {
                $translator = Translations::$plugin->translatorRepository->getTranslatorById($translatorId);
                if ($translator->service === Constants::TRANSLATOR_DEFAULT) {
                    $variables['defaultTranslatorId'] = $translatorId;
                }
            }
        }

        $orderTags = $data['tags'] ?? array();
        $variables['hasTags'] = false;
        $orderTagIds = array();

        foreach ($orderTags as $tagId) {
            $variables['hasTags'] = true;
            $tag = Craft::$app->getTags()->getTagById($tagId);
            if ($tag) {
                array_push($orderTagIds, $tag->id);
                $variables['tags'][] = $tag;
            }
        }
        if ($orderTagIds) $variables['order']->tags = implode(",", $orderTagIds);

        return $this->renderTemplate('translations/orders/_detail', $variables);
    }

    /**
     * Update Existing Order
     *
     * @return void
     */
    public function actionUpdateOrder()
    {
        $this->requireLogin();
        $this->requirePostRequest();
        $newData = Craft::$app->getRequest()->getBodyParams();
        $resetStatus = false;
        $resetStatuses = [
            Constants::ORDER_STATUS_FAILED,
            Constants::ORDER_STATUS_CANCELED,
            Constants::ORDER_STATUS_COMPLETE,
            Constants::ORDER_STATUS_PUBLISHED,
            Constants::ORDER_STATUS_REVIEW_READY
        ];

        $currentUser = Craft::$app->getUser()->getIdentity();

        $elementVersions = trim(Craft::$app->getRequest()->getParam('elementVersions'), ',') ?? array();
        $elementVersionsMap = [];
        $elementIds = [];
        $sourceSite = Craft::$app->getRequest()->getParam('sourceSiteSelect');

        if ($elementVersions) {
            $elementVersions = explode(',', $elementVersions);
            foreach ($elementVersions as $element) {
                $temp = explode('_', $element);
                if ($temp[1] != "current") {
                    $draftElement = Translations::$plugin->elementRepository->getElementByDraftId($temp[1], $sourceSite);
                    array_push($elementIds, $draftElement->id);
                    $elementVersionsMap[$temp[0]] = $draftElement->id;
                } else {
                    array_push($elementIds, $temp[0]);
                }
            }
        }

        $orderTags = Craft::$app->getRequest()->getParam('tags');

        if (!$currentUser->can('translations:orders:create')) {
            return $this->asJson(["success" => false, "message" => "User does not have permission to perform this action."]);
        }

        $orderId = Craft::$app->getRequest()->getParam('id');

        if (!$orderId) {
            return $this->asJson(["success" => false, "message" => "Invalid OrderId."]);
        }
        $order = Translations::$plugin->orderRepository->getOrderById($orderId);
        
        if (!$order) {
            return $this->asJson(["success" => false, "message" => "Invalid Order."]);
        }

        $sourceSite = $sourceSite ?: $order->sourceSite;
        // Authenticate service
        $translator = $order->getTranslator();
        $service = $translator->service;
        $settings = $translator->getSettings();
        $authenticate = self::authenticateService($service, $settings);

        if (!$authenticate && $service !== Constants::TRANSLATOR_DEFAULT) {
            $message = Translations::$plugin->translator->translate('app', 'Invalid API key');
            return $this->asJson(["success" => false, "message" => $message]);
        }

        $queueOrders = Craft::$app->getSession()->get('queueOrders');
        if (!empty($queueOrders) && ($key = array_search($orderId, $queueOrders)) !== false) {
            if (
                Craft::$app->getQueue()->status($key) == Queue::STATUS_WAITING ||
                Craft::$app->getQueue()->status($key) == Queue::STATUS_RESERVED
            ) {
                return $this->asJson(["success" => false, "message" => "This order is currently being processed."]);
            } else {
                unset($queueOrders[$key]);
                Craft::$app->getSession()->set('queueOrders', $queueOrders);
            }
        }

        try {
            $updatedFields = json_decode($newData['updatedFields']) ?? [];

            $isDefaultTranslator = $order->getTranslator()->service === Constants::TRANSLATOR_DEFAULT;
            if (!$isDefaultTranslator) {
                $translatorService = Translations::$plugin->translatorFactory
                    ->makeTranslationService(
                        $order->getTranslator()->service,
                        json_decode($order->getTranslator()->settings, true)
                    );
            }

            $oldData = [];
            $editOrderRequest = [];
            foreach ($updatedFields as $field) {
                $updated = $newData[$field] ?? '';
                if ($field == "requestedDueDate") {
                    if ($updated['date']) {
                        $updated = DateTime::createFromFormat('n/j/Y', $updated['date'])->format("Y-n-j");
                    } else {
                        $updated = '';
                    }
                    $editOrderRequest[$field] = $updated;
                }
                if ($field == "elements" || $field == 'version') {
                    if (! isset($oldData['elements'])) {
                        $updated = json_encode($elementIds);
                        $oldData['elements'] = $order->elementIds;
                        $field = 'elementIds';
                    } else {
                        continue;
                    }
                }
                if ($field == "tags") {
                    if ($tags = isset($newData[$field]) ? $newData[$field] : []) {
                        $updatedTags = [];
                        $updatedTagIds = [];
                        foreach ($tags as $tagId) {
                            $tag = Craft::$app->getTags()->getTagById((int) $tagId);
                            if ($tag) {
                                array_push($updatedTagIds, $tag->id);
                                array_push($updatedTags, $tag->title);
                            }
                        }
                        $updated = !empty($updatedTagIds) ? json_encode($updatedTagIds) : '';
                        // Make Api Request to update tags
                        if (! $isDefaultTranslator) {
                            $translatorService->editOrderTags($order, $settings, $updatedTags);
                        }
                    }
                }
                if ($field == "targetSites") {
                    $targetSites = $newData[$field];
                    if ($targetSites === '*') {
                        $targetSites = Craft::$app->getSites()->getAllSiteIds();
        
                        $source_site = Craft::$app->getRequest()->getParam('sourceSite');
                        if (($key = array_search($source_site, $targetSites)) !== false) {
                            unset($targetSites[$key]);
                            $targetSites = array_values($targetSites);
                        }
                    }
                    $newData['targetSites'] = $targetSites;
                    $updated = json_encode($targetSites);
                    $oldData['targetSites'] = $order->targetSites;
                }
                $order->$field = $updated;

                // Make Api Request to update title
                if ($field == 'title' && ! $isDefaultTranslator) {
                    $translatorService->editOrderName($order->serviceOrderId, trim($updated));
                }
                if ($field == 'comments') $editOrderRequest['comment'] = $updated;
            }

            // Logic to update dueDate and comments in acclaro order
            // if (! empty($editOrderRequest) && ! $isDefaultTranslator) {
            //     $translatorService->editOrder($order, $settings, $editOrderRequest);
            // }

            if (! empty($oldData)) {
                $resetStatus = true;
                if ($oldData['elements'] ?? null) {
                    $oldElementIds = json_decode($oldData['elements'] ?? null);
                    $added = array_diff($elementIds, $oldElementIds);
                    $removed = array_diff($oldElementIds, $elementIds);
                    if (!empty($removed)) {
                        foreach ($removed as $elementId) {
                            $files = Translations::$plugin->fileRepository->getFilesByElementId($elementId, $order->id);
                            foreach ($files as $file) {
                                if (! $isDefaultTranslator) {
                                    $translatorService->addFileComment($order, $settings, $file, "CANCEL FILE");
                                }
                                Translations::$plugin->fileRepository->deleteById($file->id);
                            }
                        }
                    }

                    if (!empty($added)) {
                        if ($targetSites = $oldData['targetSites'] ?? null) {
                            $targetSites = json_decode($oldData['targetSites'], true);
                        } else {
                            $targetSites = json_decode($order->targetSites, true);
                        }
                        foreach ($added as $elementId) {
                            foreach ($targetSites as $site) {
                                $file = Translations::$plugin->fileRepository->createOrderFile($order, $elementId, $site);
                                if (! $isDefaultTranslator) {
                                    $translatorService->sendOrderFile($order, $file, $settings);
                                    $translatorService->addFileComment($order, $settings, $file, "NEW FILE");
                                } else {
                                    Translations::$plugin->fileRepository->saveFile($file);
                                }
                            }
                        }
                    }
                }

                if ($oldData['targetSites'] ?? null) {
                    $oldTargetSites = json_decode($oldData['targetSites'], true);
                    $newTargetSites = array_diff($newData['targetSites'], $oldTargetSites);
                    $removedTargetSites = array_diff($oldTargetSites, $newData['targetSites']);

                    foreach ($newTargetSites as $site) {
                        $orderElements = $newData['elements'];
                        foreach ($orderElements as $elementId) {
                            $file = Translations::$plugin->fileRepository->createOrderFile($order, $elementId, $site);
                            if (! $isDefaultTranslator) {
                                $translatorService->sendOrderFile($order, $file, $settings);
                                $translatorService->addFileComment($order, $settings, $file, "NEW FILE");
                            } else {
                                Translations::$plugin->fileRepository->saveFile($file);
                            }
                        }
                    }

                    if ($isDefaultTranslator) {
                        foreach ($removedTargetSites as $site) {
                            Translations::$plugin->fileRepository->deleteByOrderId($order->id, $site);
                        }
                    }
                }
            }

            // Update Order and File Status
            if ($resetStatus) {
                $order->status = Translations::$plugin->orderRepository->getNewStatus($order);
            }
        } catch (Exception $e) {
            return $this->asJson(["success" => false, "message" => $e->getMessage()]);
        }
        if (Craft::$app->getElements()->saveElement($order, true, true, false)) {
            return $this->asJson([
                'success' => true,
                'message' => "Order updated."
            ]);
        } else {
            return $this->asJson([
                'success' => false,
                'message' => "Error saving order."
            ]);
        }
    }

    /**
     * Publish order files or create draft of files
     *
     * @return void
     */
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
        $totalWordCount = 0;

        foreach ($order->getElements() as $element) {
            $originalElement = ! $element->getIsDraft() ? $element : $element->getCanonical();
            if (in_array($originalElement->id, $elementIds)) {
                $fileWordCount = Translations::$plugin->elementTranslator->getWordCount($element);
                $elementFiles = Translations::$plugin->fileRepository->getFilesByElementId($element->id, $order->id);

                foreach ($elementFiles as $elementFile) {
                    if (in_array($elementFile->id, $fileIds)) {
                        $totalWordCount += $fileWordCount;
                    }
                }
                $wordCounts[$originalElement->id] = $fileWordCount;
            }
        }

        $job = '';

        try {
            if ($totalWordCount > Constants::WORD_COUNT_LIMIT) {
                $job = Craft::$app->queue->push(new CreateDrafts([
                    'description' => $action == 'publish' ? 'Applying translation drafts' : 'Creating translation drafts',
                    'orderId' => $order->id,
                    'wordCounts' => $wordCounts,
                    'publish' => $action == 'publish' ? true : false,
                    'elementIds' => $elementIds,
                    'fileIds' => $fileIds,
                ]));

                $queueOrders = Craft::$app->getSession()->get('queueOrders');
                $queueOrders[$job] = $order->id;
                Craft::$app->getSession()->set('importQueued', 1);
                Craft::$app->getSession()->set('queueOrders', $queueOrders);
            } else {
                $job =  null;
                Translations::$plugin->draftRepository->createOrderDrafts(
                    $order->id, $wordCounts, null, $action == 'publish' ? true : false,
                    $elementIds, $fileIds
                );
            }
        } catch (Exception $e) {
            $actionName = $action == "publish" ? "publish" : "save";
            $order->logActivity(Translations::$plugin->translator->translate('app', "Could not $actionName draft Error: " . $e->getMessage()));
            Craft::error( '['. __METHOD__ .'] Couldn’t save the draft. Error: '.$e->getMessage(), 'translations' );
            $order->status = 'failed';
            Craft::$app->getElements()->saveElement($order);
            Craft::$app->getSession()->setNotice(
                Translations::$plugin->translator->translate('app', "Could not $actionName draft Error: " . $e->getMessage())
            );
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
        } else {
            Craft::$app->getSession()->setNotice(
                Translations::$plugin->translator->translate(
                    'app', $action == "draft" ? 'Translation draft(s) saved' : 'Translation draft(s) published'
                )
            );
        }
    }

    /**
     * Cancel an acclaro order
     *
     * @return void
     */
    public function actionCancelOrder()
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $variables = Craft::$app->getRequest()->resolve()[1];

        $orderId = $variables['orderId'] ?? Craft::$app->getRequest()->getParam('id');
        $order = Translations::$plugin->orderRepository->getOrderById($orderId);
        if (!$order) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator
                ->translate('app', 'Order not found.'));
            return;
        }

        $translator = $order->translatorId ? Translations::$plugin->translatorRepository->getTranslatorById($order->translatorId) : null;

        if (! $translator) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator
                ->translate('app', 'Invalid translator.'));
            return;
        }

        if ($translator->service == Constants::TRANSLATOR_ACCLARO) {
            $translatorService = Translations::$plugin->translatorFactory
                ->makeTranslationService(
                    $order->getTranslator()->service,
                    json_decode($order->getTranslator()->settings, true)
                );

            $res = $translatorService->cancelOrder($order, $translator->getSettings());

            if (empty($res)) {
                Craft::$app->getSession()->setError(Translations::$plugin->translator
                ->translate('app', "Unable to cancel order: $order->title"));
                return;
            }
        }

        foreach ($order->files as $file) {
            Translations::$plugin->fileRepository->cancelOrderFile($file);
        }

        $order->status = Constants::ORDER_STATUS_CANCELED;
        $order->logActivity(Translations::$plugin->translator->translate(
            'app',
            'Sent My Acclaro order cancellation request.'
        ));
        Craft::$app->getElements()->saveElement($order);

        Craft::$app->getSession()->setNotice(Translations::$plugin->translator
                ->translate('app', "Order canceled: $order->title"));

        return $this->redirect(Constants::URL_ORDER_DETAIL.$order->id, 302, true);
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

    // Acclaro Order methods
    public function actionSyncOrder()
    {
        $this->requireLogin();
        $this->requirePostRequest();
        if (!Translations::$plugin->userRepository->userHasAccess('translations:orders:import')) {
            return $this->redirect(Constants::URL_TRANSLATIONS, 302, true);
        }

        $orderId = Craft::$app->getRequest()->getParam('orderId');

        $order = Translations::$plugin->orderRepository->getOrderById((int) $orderId);

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
            if ($totalWordCount > Constants::WORD_COUNT_LIMIT) {
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
                    Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app',  "Order is being synced via queue."));
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
            if ($order->translator->service === Constants::TRANSLATOR_DEFAULT) {
                continue;
            }
            $totalWordCount += ($order->wordCount * count($order->getTargetSitesArray()));
            $allFileCounts += count($order->files);
        }

        $job = '';
        $url = Craft::$app->getRequest()->absoluteUrl;

        try {
            foreach ($orders as $order) {
                // Don't update manual orders
                if ($order->translator->service === Constants::TRANSLATOR_DEFAULT) {
                    continue;
                }
    
                if ($totalWordCount > Constants::WORD_COUNT_LIMIT) {
                    $job = Craft::$app->queue->push(new SyncOrder([
                        'description' => 'Syncing order '. $order->title,
                        'order' => $order
                    ]));
                } else {
                    Translations::$plugin->orderRepository->syncOrder($order);
                }
            }
        } catch (Exception $e) {
            Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app',  'Cannot sync orders. Error: '.$e->getMessage()));
            return;
        }

        if ($job) {
            $params = [
                'id' => (int) $job,
                'notice' => 'Done syncing orders',
                'url' => $url
            ];
            Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app',  'Order\'s are being synced via queue.'));
            Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
        } else {
            Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app',  'Order sync complete.'));
        }
    }

    // Order Draft Methods
    /**
     * Save Order Draft Action
     *
     * @return void
     */
    public function actionSaveOrderDraft()
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $currentUser = Craft::$app->getUser()->getIdentity();

        $elementVersions = trim(Craft::$app->getRequest()->getParam('elementVersions'), ',') ?? array();

        if (!$currentUser->can('translations:orders:create')) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator
                ->translate('app', 'User does not have permission to perform this action.'));
            return;
        }

        $orderId = Craft::$app->getRequest()->getParam('id');
        $sourceSite = Craft::$app->getRequest()->getParam('sourceSiteSelect');

        if ($sourceSite && !Translations::$plugin->siteRepository->isSiteSupported($sourceSite)) {
            throw new HttpException(400, Translations::$plugin->translator
                ->translate('app', 'Source site is not supported'));
        }

        $order = Translations::$plugin->orderRepository->makeNewOrder($sourceSite);
        $order->logActivity(Translations::$plugin->translator->translate('app', 'Order draft created'));

        try {
            $targetSites = Craft::$app->getRequest()->getParam('targetSites');

            if ($targetSites === '*') {
                $targetSites = Craft::$app->getSites()->getAllSiteIds();
                
                $source_site = Craft::$app->getRequest()->getParam('sourceSite');
                if (($key = array_search($source_site, $targetSites)) !== false) {
                    unset($targetSites[$key]);
                    $targetSites = array_values($targetSites);
                }
            }

            if (! is_array($targetSites)) {
                $targetSites = explode(",", str_replace(", ", ",", $targetSites));
            }

            $elementIds = [];
            if ($elementVersions) {
                $elementVersions = explode(',', $elementVersions);
                foreach ($elementVersions as $element) {
                    $temp = explode('_', $element);
                    if ($temp[1] != "current") {
                        $draftElement = Translations::$plugin->elementRepository->getElementByDraftId($temp[1], $sourceSite);
                        array_push($elementIds, $draftElement->id);
                    } else {
                        array_push($elementIds, $temp[0]);
                    }
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
            
            $orderTags = Craft::$app->getRequest()->getParam('tags') ?? null;

            $order->tags = $orderTags ? json_encode($orderTags) : '';
            $order->title = $title;
            $order->sourceSite = $sourceSite;
            $order->targetSites = $targetSites ? json_encode($targetSites) : null;

            if ($requestedDueDate) {
                if (!is_array($requestedDueDate)) {
                    $requestedDueDate = DateTime::createFromFormat('n/j/Y', $requestedDueDate);
                } else {
                    $requestedDueDate = DateTime::createFromFormat('n/j/Y', $requestedDueDate['date']);
                }
            }
            $order->requestedDueDate = $requestedDueDate ?: null;

            $order->comments = Craft::$app->getRequest()->getParam('comments');
            $order->translatorId = $translatorId;

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

            $success = Craft::$app->getElements()->saveElement($order, true, true, false);

            if (! $success) {
                Craft::error('[' . __METHOD__ . '] Couldn’t save the order', 'translations');
                Craft::$app->getSession()->setNotice(
                    Translations::$plugin->translator->translate('app', 'Error saving Order.')
                );
            } else {
                Craft::$app->getSession()->setNotice(
                    Translations::$plugin->translator->translate('app', 'Order Saved.')
                );
                return $this->redirect(Constants::URL_ORDERS, 302, true);
            }
        } catch (Exception $e) {
            Craft::error('[' . __METHOD__ . '] Couldn’t save the order. Error: ' . $e->getMessage(), 'translations');
            Craft::$app->getSession()->setError(Translations::$plugin->translator
                ->translate('app', 'Error saving draft. Error: '.$e->getMessage()));
        }
    }
    
    /**
     * Delete Order Draft Action
     *
     * @return void
     */
    public function actionDeleteOrderDraft()
    {
        // TODO: need to add logic later
        Craft::$app->getSession()->setError(
            Translations::$plugin->translator->translate('app', 'Delete Order Draft WIP.')
        );
    }
}
