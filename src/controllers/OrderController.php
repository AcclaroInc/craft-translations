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
use craft\elements\Entry;
use yii\web\HttpException;

use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\services\job\CreateDrafts;
use acclaro\translations\services\job\SyncOrderJob;
use acclaro\translations\services\repository\OrderRepository;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class OrderController extends BaseController
{
    protected $service;

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     * @access protected
     */
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE;

    /**
     * @var int
     */
    protected $pluginVersion;

    // Public Methods
    // =========================================================================

    public function __construct($id, $module = null) {
        parent::__construct($id, $module);

        $this->service = new OrderRepository();
        $this->pluginVersion = Craft::$app->getPlugins()->getPlugin(Constants::PLUGIN_HANDLE)->getVersion();
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

        $variables['orderCount'] = $this->service->getOrdersCount();

        $variables['orderCountAcclaro'] = $this->service->getAcclaroOrdersCount();

        $variables['selectedSubnavItem'] = 'orders';

        $variables['context'] = 'index';

        $this->renderTemplate('translations/orders/_index', $variables);
    }

    // Detail Page Methods
    // =========================================================================

	public function actionOrderDetail(array $variables = array())
    {
        $variables = Craft::$app->getRequest()->resolve()[1];

		if (!Translations::$plugin->userRepository->userHasAccess('translations:orders:create')) {
			return $this->redirect(Constants::URL_ENTRIES, 302, true);
		}

        $variables['isProcessing'] = Craft::$app->getRequest()->getParam('isProcessing');
		$variables['isChanged'] = Craft::$app->getRequest()->getQueryParam('changed');
		$variables['orientation'] = Craft::$app->getLocale()->orientation;
		$variables['chkDuplicateEntries'] = Translations::getInstance()->settings->chkDuplicateEntries;
        $variables['tagGroup'] = Craft::$app->getTags()->getTagGroupByHandle(Constants::ORDER_TAG_GROUP_HANDLE);
        $variables['apiLogging'] =  Translations::getInstance()->settings->apiLogging;

        $variables['versionsByElementId'] = [];
        $variables['elements'] = [];
        $variables['orderId'] = $variables['orderId'] ?? null;
        $variables['isSourceChanged'] = [];
		$variables['isTargetChanged'] = [];
		$variables['selectedSubnavItem'] = 'orders';
		$variables['isDefaultTranslator'] = true;
		$variables['elementWordCounts'] = array();
        $variables['orderWordCount'] = 0;
        $variables['translatorOptions'] = Translations::$plugin->translatorRepository->getTranslatorOptions();
        $variables['translatorServices'] = [];

        if ($variables['isProcessing']) {
            $submitAction = Craft::$app->getRequest()->getParam('submit');
            if ($submitAction == "draft" || $submitAction == "publish") {
                $variables['isProcessing'] = $submitAction;
            }
            if (Craft::$app->getSession()->get('importQueued')) {
                Craft::$app->getSession()->remove('importQueued');
            } else {
                $variables['isProcessing'] = null;
            }

            // Added here when api order is created and inject after reload.
            if ($newJs = Craft::$app->getSession()->get('registerJs')) {
                Craft::$app->getSession()->remove('registerJs');
                Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, ' . $newJs . '); });');
            }
        }

        $sourceSite = Craft::$app->getRequest()->getQueryParam('sourceSite') ?? Craft::$app->getRequest()->getBodyParam('sourceSite');

        if ($sourceSite && !Translations::$plugin->siteRepository->isSiteSupported($sourceSite)) {
				$this->setError('Source site is not supported.');
				return;
		}

		$order = null;
        if ($variables['orderId']) {
            $order  = $this->service->getOrderById($variables['orderId']);

            $variables['inputElements'] = Craft::$app->getRequest()->getQueryParam('elements') ?? [];

            if (!$order) {
                throw new HttpException(404);
            }
        } else {
            $order = $this->service->makeNewOrder($sourceSite);

			$orderElements= Craft::$app->getRequest()->getQueryParam('elements') ?? Craft::$app->getRequest()->getParam('elements');
			$order->elementIds = json_encode($orderElements ?: []);

            $variables['inputElements'] = $orderElements ?: [];
        }

        // Check if source site is exists
        if (!Craft::$app->getSites()->getSiteById($order->sourceSite)) throw new Exception("Source Site Does Not Exist", 404);

		// Check for changes if we are adding an element
		if ($variables['isChanged']) {
			if ($orderTitle= Craft::$app->getRequest()->getQueryParam('title')) {
				$order->title = $orderTitle;
			}

			if ($orderTargetSites= Craft::$app->getRequest()->getQueryParam('targetSite')) {
				$order->targetSites = json_encode($orderTargetSites);
			}

			if ($orderTags= Craft::$app->getRequest()->getQueryParam('tags') ?? Craft::$app->getRequest()->getParam('tags')) {
				if (! is_array($orderTags)) {
					$orderTags = explode(',', $orderTags);
				}
				$order->tags = json_encode($orderTags);
			}

			if ($requestedDueDate= Craft::$app->getRequest()->getQueryParam('dueDate')) {
				$order->requestedDueDate = $requestedDueDate;
			}

			if ($orderComments= Craft::$app->getRequest()->getQueryParam('comments')) {
				$order->comments = $orderComments;
			}

			if ($orderTranslatorId= Craft::$app->getRequest()->getQueryParam('translatorId')) {
				$order->translatorId = $orderTranslatorId;
			}

			if ($orderTrackChanges= Craft::$app->getRequest()->getQueryParam('trackChanges')) {
				$order->trackChanges = $orderTrackChanges;
			}

			if ($orderTrackTargetChanges = Craft::$app->getRequest()->getQueryParam('trackTargetChanges')) {
				$order->trackTargetChanges = $orderTrackTargetChanges;
			}

			if ($orderIncludeTmFiles = Craft::$app->getRequest()->getQueryParam('includeTmFiles')) {
				$order->includeTmFiles = $orderIncludeTmFiles;
			}

			if ($requestOrderQuote = Craft::$app->getRequest()->getQueryParam('requestQuote')) {
				$order->requestQuote = $requestOrderQuote;
			}
		}

		$finalElements = $order->getElements();

        if ($variables['inputElements']) {
            foreach ($variables['inputElements'] as $elementId) {
                $element = Translations::$plugin->elementRepository->getElementById((int) $elementId, $order->sourceSite);

                if ($element) {
                    $variables['elements'][] = $element;
                    if (! array_key_exists($element->id, $finalElements)) $finalElements[$element->id] = $element;
                }
            }
        } else {
			$variables['elements'] = $order->getElements();
        }

        $variables['duplicateEntries'] = $this->service->checkOrderDuplicates($variables['elements']);

        foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
            $site = Craft::$app->getSites()->getSiteById($siteId);
            $variables['sites'][] = array(
                'value' => $site->id,
                'label' => $site->name . '(' . $site->language . ')'
            );
            $variables['siteObjects'][$siteId] = $site;
        }

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

        foreach ($finalElements as $element) {
            $canonicalElement = $element->getIsDraft() ? $element->getCanonical() : $element;
            $drafts = Craft::$app->getDrafts()->getEditableDrafts($canonicalElement);
            $tempDraftNames = [[
				'value' => $canonicalElement->id,
				'label' => 'Current...'
			]];
            foreach ($drafts as $draft) {
                if (Translations::$plugin->draftRepository->isTranslationDraft($draft->draftId)) {
                    continue;
                }
                $draftBehaviour = $draft->getBehavior("draft");
                $tempDraftNames[] = [
                    'value' => $draft->id,
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

        $totalWordCount = ($variables['orderWordCount'] * count($order->getTargetSitesArray()));

        if ($totalWordCount <= Constants::WORD_COUNT_LIMIT || Craft::$app->getSession()->get('fileImportError') ?? null) {
            Craft::$app->getSession()->set('fileImportError', false);
            $variables['isProcessing'] = null;
        }

		// Set Translators dd options
        if (!$variables['translatorOptions']) {
            $variables['translatorOptions'] = array('' => Translations::$plugin->translator->translate('app', 'No Translators'));
        } else {
            foreach ($variables['translatorOptions'] as $translatorId => $val) {
                $translator = Translations::$plugin->translatorRepository->getTranslatorById($translatorId);

                if ($translator->service === Constants::TRANSLATOR_DEFAULT) {
                    $variables['defaultTranslatorId'] = $translatorId;
                }
                
                $variables['translatorServices'][$translatorId] = $translator->service;
            }
        }

        $userId = $order->ownerId ?? Craft::$app->getUser()->id;
        $user = Craft::$app->getUsers()->getUserById($userId);

        $variables['owners'] = array(
            $user->id => $user->username,
        );

        $variables['author'] = $user;

        $variables['translator'] = null;
        $variables['isEditable'] =  true;
        $variables['orderRecentStatus'] = null;

        $translatorId = !is_null($order->translator) ?
            $order->translator->id : null;

        if ($translatorId) {
            $variables['translator'] = Translations::$plugin->translatorRepository
                ->getTranslatorById($translatorId);

            $variables['orderRecentStatus'] = $order->status;
            if (
                $variables['orderRecentStatus'] === Constants::ORDER_STATUS_PUBLISHED &&
                $variables['translator']->service !== Constants::TRANSLATOR_DEFAULT
            ) $variables['isEditable'] = false;
        }

		// Set lastest Order status if api order
        if (
            !is_null($variables['translator']) &&
            $variables['translator']->service === Constants::TRANSLATOR_ACCLARO
        ) {
			$variables['isDefaultTranslator'] = false;

			if (!$order->isPending()) {
				/** @var \acclaro\translations\services\translator\AcclaroTranslationService */
				$translationService = $order->getTranslationService();

				$translatorUrl = $translationService->getOrderUrl($order);
				$variables['translator_url'] = $translatorUrl;
				$orderStatus = $translationService->getOrderStatus($order);
				if ($order->isCanceled()) {
					$variables['isEditable'] = false;
				}
				if ($orderStatus === Constants::ORDER_STATUS_COMPLETE) {
					$variables['isEditable'] = false;
					if ($order->isPublished()) {
						$variables['orderRecentStatus'] = Constants::ORDER_STATUS_PUBLISHED;
					} else {
						$variables['orderRecentStatus'] = $orderStatus;
					}
				}

				if ($orderStatus !== Constants::ORDER_STATUS_COMPLETE) {
					$variables['isUpdateable'] = true;
				}

                if ($order->requestQuote() && !$order->isGettingQuote()) {
                    $variables['orderQuote'] = $translationService->getOrderQuote($order->serviceOrderId);
                }
			}
        }

        $variables['isSubmitted'] = !($order->isPending() || $order->isFailed());

        if ($order->trackChanges && $variables['isSubmitted']) {
            $variables['isSourceChanged'] = Translations::$plugin->orderRepository->getIsSourceChanged($order);
		}

		if ($order->trackTargetChanges && $variables['isSubmitted'] && $order->isTmMisaligned()) {
            $variables['isTargetChanged'] = Translations::$plugin->orderRepository->getIsTargetChanged($order);
		}

		$variables['canUpdateFiles'] = $variables['orderRecentStatus'] !== Constants::ORDER_STATUS_CANCELED && $variables['orderRecentStatus'] !== Constants::ORDER_STATUS_PUBLISHED;

		$variables['isCancelable'] = $variables['orderRecentStatus'] !== Constants::ORDER_STATUS_PENDING && $variables['orderRecentStatus'] !== Constants::ORDER_STATUS_FAILED && $variables['orderRecentStatus'] !== Constants::ORDER_STATUS_COMPLETE && $variables['orderRecentStatus'] !== Constants::ORDER_STATUS_CANCELED && $variables['orderRecentStatus'] !== Constants::ORDER_STATUS_PUBLISHED;

		$variables['order'] = $order;

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
        $backToNew = count($flow) > 1;

        /** @var craft\elements\User $currentUser */
        $currentUser = Craft::$app->getUser()->getIdentity();

        $orderTags = Craft::$app->getRequest()->getParam('tags');
		$orderId = Craft::$app->getRequest()->getParam('id');
        $sourceSite = Craft::$app->getRequest()->getParam('sourceSite');

        if (!$currentUser->can('translations:orders:create')) {
            return $this->asFailure($this->getErrorMessage("User does not have permission to perform this action."));
        }

        if ($sourceSite && !Translations::$plugin->siteRepository->isSiteSupported($sourceSite)) {
            return $this->asFailure($this->getErrorMessage("Source site is not supported."));
        }

        if ($orderId) {
            // This is for draft converting to order.
            $order = $this->service->getOrderById($orderId);

            $order->logActivity(Translations::$plugin->translator->translate('app', 'Order created'));
        } else {
            $order = $this->service->makeNewOrder($sourceSite);

            $order->logActivity(Translations::$plugin->translator->translate('app', 'Order created'));
        }

        $job = '';
        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $targetSites = Craft::$app->getRequest()->getParam('targetSites');

            if ($targetSites === '*') {
                $targetSites = Craft::$app->getSites()->getAllSiteIds();

                if (($key = array_search($sourceSite, $targetSites)) !== false) {
                    unset($targetSites[$key]);
                    $targetSites = array_values($targetSites);
                }
            }

            if (! is_array($targetSites)) {
                $targetSites = explode(",", str_replace(", ", ",", $targetSites));
            }

			$elementIds = Craft::$app->getRequest()->getBodyParam('elements', []);

            $requestedDueDate = Craft::$app->getRequest()->getParam('requestedDueDate');
            $order->ownerId = Craft::$app->getRequest()->getParam('ownerId');

            $order->tags = json_encode($orderTags ?? []);
            $order->title = Craft::$app->getRequest()->getParam('title');
            $order->trackChanges = Craft::$app->getRequest()->getBodyParam('trackChanges');
			$order->trackTargetChanges = Craft::$app->getRequest()->getBodyParam('trackTargetChanges');
			$order->includeTmFiles = Craft::$app->getRequest()->getBodyParam('includeTmFiles');
			$order->requestQuote = Craft::$app->getRequest()->getBodyParam('requestQuote');
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
            $order->translatorId = Craft::$app->getRequest()->getParam('translatorId');

            $order->elementIds = json_encode($elementIds);

            $entriesCount = 0;
            $wordCounts = array();

            // Authenticate service
            $translator = $order->getTranslator();
            $translationService = $order->getTranslationService();

            if (!$translationService->authenticate()) {
                $transaction->rollBack();
                return $this->asFailure($this->getErrorMessage('Failed to authenticate API key.'));
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
                            "The target site(s) selected are not available for the entry “%s”. \
                            Please check your settings in Settings > Sections > %s to change this entry's \
                            target sites.",
                            $element->title,
                            $element->section->name
                        );
                        $transaction->rollBack();
                        return $this->asFailure($this->getErrorMessage($message));
                    }
                }
            }

            $order->entriesCount = $entriesCount;
            $order->wordCount = array_sum($wordCounts);

            // Manual Translation will make orders 'in progress' status after creation
            $success = Craft::$app->getElements()->saveElement($order, true, true, false);

            if (!$success) {
                Translations::$plugin->logHelper->log('[' . __METHOD__ . '] Couldn’t save the order', Constants::LOG_LEVEL_ERROR);
                $transaction->rollBack();
                return $this->asFailure($this->getErrorMessage("Error saving Order."));
            }

            // Check supported languages for order service
            if ($order->getTranslator()->service === Constants::TRANSLATOR_ACCLARO) {
                if ($translationService->getLanguages()) {
                    $sourceSite = Craft::$app->getSites()->getSiteById($order->sourceSite);
                    $sourceLanguage = Translations::$plugin->siteRepository->normalizeLanguage($sourceSite->language);
                    $unsupported = false;
                    $unsupportedLangs = [];
                    $supportedLanguagePairs = [];
                    $sourceSlug = "$sourceSite->name ($sourceSite->language)";

                    foreach ($translationService->getLanguagePairs($sourceLanguage) as $key => $languagePair) {
                        $supportedLanguagePairs[] = strtolower($languagePair->target['code']);
                    }

                    foreach (json_decode($order->targetSites) as $key => $siteId) {
                        $site = Craft::$app->getSites()->getSiteById($siteId);
                        $language = Translations::$plugin->siteRepository->normalizeLanguage($site->language);
                        $targetSlug = "$site->name ($site->language)";

                        if (!in_array($language, array_map('strtolower', array_column($translationService->getLanguages(), 'code')))) {
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
                        return $this->asFailure(sprintf(
                            $this->getErrorMessage("The following language pair(s) are not supported by %s {%s}"),
                            ucfirst($order->getTranslator()->service),
                            implode("}, {", array_column($unsupportedLangs, "language"))
                        ));
                    }
                }
            }

            // Create Order Files
            $success = Translations::$plugin->fileRepository->createOrderFiles($order, $wordCounts);

            if (! $success) {
                Translations::$plugin->logHelper->log('[' . __METHOD__ . '] Couldn’t create the order file', Constants::LOG_LEVEL_ERROR);
                $transaction->rollBack();

                return $this->asFailure($this->getErrorMessage("Saving order files."));
            }

            $order->status = Constants::ORDER_STATUS_NEW;
            $order->dateOrdered = new DateTime();

            $success = Craft::$app->getElements()->saveElement($order, true, true, false);

            if (! $success) {
                Translations::$plugin->logHelper->log('[' . __METHOD__ . '] Couldn’t save the order', Constants::LOG_LEVEL_ERROR);
                $transaction->rollBack();
                return $this->asFailure($this->getErrorMessage("Couldn’t save the order."));
            }

            // Sending Order To Acclaro
            if ($order->getTranslator()->service === Constants::TRANSLATOR_ACCLARO) {
                $totalWordCount = ($order->wordCount * count($order->getTargetSitesArray()));

                if ($totalWordCount > Constants::WORD_COUNT_LIMIT) {
                    $job = $translationService->SendOrder($order);

                    $queueOrders = Craft::$app->getSession()->get('queueOrders');
                    $queueOrders[$job] = $order->id;
                    Craft::$app->getSession()->set('importQueued', 1);
                    Craft::$app->getSession()->set('queueOrders', $queueOrders);
                } else {
                    $job =  null;
                    $this->service->sendAcclaroOrder(
                        $order,
                        $translator->getSettings()
                    );
                }
            }

            $orderAction = sprintf('Order submitted to %s', $order->translator->getName());

            if ($order->requestQuote())
                $orderAction = sprintf('Order quote requested from %s', $order->translator->getName());

            $order->logActivity(Translations::$plugin->translator->translate('app', $orderAction));

            $transaction->commit();
        } catch (Exception $e) {
            Translations::$plugin->logHelper->log('[' . __METHOD__ . '] Couldn’t save the order. Error: ' . $e->getMessage(), Constants::LOG_LEVEL_ERROR);
            $transaction->rollBack();
            return $this->asFailure($this->getErrorMessage($e->getMessage()));
        }

        $redirectUrl = Translations::$plugin->urlHelper->cpUrl(
            $backToNew ? Constants::URL_ORDER_CREATE : Constants::URL_ORDER_DETAIL . $order->id
        );

        if ($job) {
            $params = [
                'id' => (int) $job,
                'notice' => 'Translation order sent',
                'url' => $redirectUrl
            ];
            Craft::$app->getSession()->set('registerJs', json_encode($params));
            return $this->asSuccess(null, [], $redirectUrl . "&isProcessing=1");
        } else {
            $this->setSuccess("New order created '{$order->title}'");
            return $this->asSuccess(null, [], $redirectUrl);
        }
    }

    /**
     * Clone an existing order
     *
     * @return void
     */
    public function actionCloneOrder()
    {
        $variables = Craft::$app->getRequest()->resolve()[1];
        $data = Craft::$app->getRequest()->getBodyParams();

		if (!Translations::$plugin->userRepository->userHasAccess('translations:orders:create')) {
			return $this->redirect(Constants::URL_ENTRIES, 302, true);
		}

		$variables['orientation'] = Craft::$app->getLocale()->orientation;
        $elementIds = Craft::$app->getRequest()->getBodyParam('elements', []);
        $variables['tagGroup'] = Craft::$app->getTags()->getTagGroupByHandle(Constants::ORDER_TAG_GROUP_HANDLE);

        $variables['isProcessing'] = null;
        $variables['isChanged'] = null;
        $variables['isEditable'] = true;
        $variables['isSubmitted'] = null;
        $variables['isTargetChanged'] = [];
        $variables['selectedSubnavItem'] = 'orders';
		$variables['isDefaultTranslator'] = false;
        $variables['orderId'] = null;
        $variables['sourceSite'] = $data['sourceSite'];
		$variables['canUpdateFiles'] = false;
		$variables['isCancelable'] = false;
        $variables['translatorServices'] = [];

        $requestedDueDate = null;
        if ($data['requestedDueDate']['date'] ?? null) {
            $requestedDueDate = DateTime::createFromFormat('n/j/Y', $data['requestedDueDate']['date'])->format("Y-n-j");
        }

        $newOrder = $this->service->makeNewOrder($variables['sourceSite']);

        $newOrder->title = $data['title'] ?? '';
        $newOrder->trackChanges = $variables['shouldTrackSourceContent'] = $data['trackChanges'] ?? null;
		$newOrder->trackTargetChanges = $variables['shouldTrackTargetContent'] = $data['trackTargetChanges'] ?? null;
		$newOrder->includeTmFiles = $data['includeTmFiles'] ?? null;
		$newOrder->requestQuote = $data['requestQuote'] ?? null;
        $newOrder->targetSites = json_encode($data['targetSites'] ?? '');
        $newOrder->elementIds = json_encode($elementIds);
        $newOrder->comments = $data['comments'] ?? '';
        $newOrder->requestedDueDate = $requestedDueDate ?? '';
        $newOrder->translatorId = $data['translatorId'] ?? '';

        $variables['order'] = $newOrder;

        $variables['translatorId'] = $variables['order']['translatorId'];
        foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
            $site = Craft::$app->getSites()->getSiteById($siteId);
            $variables['sites'][] = array(
                'value' => $site->id,
                'label' => $site->name . '(' . $site->language . ')'
            );
            $variables['siteObjects'][$siteId] = $site;
        }

        $userId = Craft::$app->getUser()->id;
        $user = Craft::$app->getUsers()->getUserById($userId);

        $variables['owners'] = array(
            $user->id => $user->username,
        );

        $variables['author'] = $user;

        $variables['elements'] = [];

        foreach ($data['elements'] as $elementId) {
            $element = Translations::$plugin->elementRepository->getElementById((int) $elementId, $variables['order']->sourceSite);

            if ($element) {
                $variables['elements'][] = $element;
            }
        }

        $variables['originalElementIds'] = '';

        $variables['duplicateEntries'] = $this->service->checkOrderDuplicates($variables['elements']);

        $variables['chkDuplicateEntries'] = Translations::getInstance()->settings->chkDuplicateEntries;

        $variables['orderWordCount'] = 0;

        $variables['elementWordCounts'] = array();

        foreach ($variables['elements'] as $element) {
			$canonicalElement = $element->getIsDraft() ? $element->getCanonical() : $element;
            $drafts = Craft::$app->getDrafts()->getEditableDrafts($canonicalElement);
            $tempDraftNames = [[
				'value' => $canonicalElement->id,
				'label' => 'Current...'
			]];
            foreach ($drafts as $draft) {
                if (Translations::$plugin->draftRepository->isTranslationDraft($draft->draftId)) {
                    continue;
                }
                $draftBehaviour = $draft->getBehavior("draft");
                $tempDraftNames[] = [
                    'value' => $draft->id,
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

        $variables['translatorOptions'] = Translations::$plugin->translatorRepository->getTranslatorOptions();
        if (!$variables['translatorOptions']) {
            $variables['translatorOptions'] = array('' => Translations::$plugin->translator->translate('app', 'No Translators'));
        } else {
            foreach ($variables['translatorOptions'] as $translatorId => $val) {
                $translator = Translations::$plugin->translatorRepository->getTranslatorById($translatorId);
                if ($translator->service === Constants::TRANSLATOR_DEFAULT) {
                    $variables['defaultTranslatorId'] = $translatorId;
					if ($translatorId == $variables['translatorId'])
						$variables['isDefaultTranslator'] = true;
                }
                $variables['translatorServices'][$translatorId] = $translator->service;
            }
        }

        $orderTags = $data['tags'] ?? array();

        foreach ($orderTags as $tagId) {
            $variables['tags'][] = Craft::$app->getTags()->getTagById($tagId);
        }

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

        /** @var craft\elements\User $currentUser */
        $currentUser = Craft::$app->getUser()->getIdentity();

        $elementIds = Craft::$app->getRequest()->getBodyParam('elements');
        $sourceSite = Craft::$app->getRequest()->getBodyParam('sourceSite');

        if (!$currentUser->can('translations:orders:create')) {
            return $this->asFailure($this->getErrorMessage("User does not have permission to perform this action."));
        }

        if (!$orderId = Craft::$app->getRequest()->getParam('id')) {
            return $this->asFailure($this->getErrorMessage("Invalid OrderId."));
        }

        if (!$order = $this->service->getOrderById($orderId)) {
            return $this->asFailure($this->getErrorMessage("Invalid Order."));
        }

        // Authenticate service
        $translator = $order->getTranslator();

        $translatorService = $order->getTranslationService();

        $isAcclaroTranslator = $translator->service === Constants::TRANSLATOR_ACCLARO;

        if (!$translatorService->authenticate()) {
            return $this->asFailure($this->getErrorMessage('Failed to authenticate API key.'));
        }

        $queueOrders = Craft::$app->getSession()->get('queueOrders');
        if (!empty($queueOrders) && ($key = array_search($orderId, $queueOrders)) !== false) {
            if (
                Craft::$app->getQueue()->status($key) == Queue::STATUS_WAITING ||
                Craft::$app->getQueue()->status($key) == Queue::STATUS_RESERVED
            ) {
                return $this->asSuccess($this->getSuccessMessage("This order is currently being processed."));
            } else {
                unset($queueOrders[$key]);
                Craft::$app->getSession()->set('queueOrders', $queueOrders);
            }
        }

        try {
			// Set Tags to order
			if ($isAcclaroTranslator) {
				if ($newTags = Craft::$app->getRequest()->getParam('tags')) {
					$updatedTags = [];
					foreach ($newTags as $tagId) {
						$tag = Craft::$app->getTags()->getTagById((int) $tagId);
						if ($tag) {
							$updatedTags[$tag->id] = $tag->title;
						}
					}

					if ($updatedTags) {
						$order->tags = json_encode(array_keys($updatedTags));
						$translatorService->editOrderTags($order, array_values($updatedTags));
					}
				}
			}

			// Set order title
			if ($order->title != $newTitle = trim(Craft::$app->getRequest()->getBodyParam('title'))) {
				$order->title = $newTitle;

				if ($isAcclaroTranslator) {
					$translatorService->editOrderName($order->serviceOrderId, trim($newTitle));
				}
			}

			// Update entry, targetSites and order file
			$targetSites = Craft::$app->getRequest()->getBodyParam('targetSites');
			if ($targetSites === '*') {
				$targetSites = Craft::$app->getSites()->getAllSiteIds();

				if (($key = array_search($sourceSite, $targetSites)) !== false) {
					unset($targetSites[$key]);
					$targetSites = array_values($targetSites);
				}
			}

			$removedSites = array_diff(json_decode($order->targetSites, true), $targetSites);
			$addedSites = array_diff($targetSites, json_decode($order->targetSites, true));
			$removedEntries = array_diff(json_decode($order->elementIds, true), $elementIds);
			$addedEntries = array_diff($elementIds, json_decode($order->elementIds, true));

            if ($removedSites) {
				foreach ($removedSites as $site) {
					if ($isAcclaroTranslator) {
						$files = Translations::$plugin->fileRepository->getFiles($order->id, null, $site);
						foreach ($files as $file) {
							$translatorService->addFileComment($order, $file, "CANCEL FILE");
						}
					}
					Translations::$plugin->fileRepository->delete($order->id, null, $site);
				}
            }

			if ($removedEntries) {
				foreach ($removedEntries as $entryId) {
					if ($isAcclaroTranslator) {
						$files = Translations::$plugin->fileRepository->getFiles($order->id, $entryId);
						foreach ($files as $file) {
							$translatorService->addFileComment($order, $file, "CANCEL FILE");
						}
					}
					Translations::$plugin->fileRepository->delete($order->id, $entryId);
				}
			}

            if ($addedSites) {
				foreach ($addedSites as $site) {
					foreach ($order->getElements() as $entry) {
						if (in_array($entry->id, $removedEntries)) continue;
						$file = Translations::$plugin->fileRepository->createOrderFile($order, $entry->id, $site);
						Translations::$plugin->fileRepository->saveFile($file);
						if ($isAcclaroTranslator) {
							$files = Translations::$plugin->fileRepository->getFiles($order->id, $entry->id, $site);
							foreach ($files as $file) {
								$translatorService->sendOrderFile($order, $file);
								$translatorService->addFileComment($order, $file, "NEW FILE");
							}
						}
					}
				}
            }

            if ($addedEntries) {
				foreach ($addedEntries as $entryId) {
					foreach ($targetSites as $site) {
						$file = Translations::$plugin->fileRepository->createOrderFile($order, $entryId, $site);
						Translations::$plugin->fileRepository->saveFile($file);
						if ($isAcclaroTranslator) {
							$files = Translations::$plugin->fileRepository->getFiles($order->id, $entryId, $site);
							foreach ($files as $file) {
								$translatorService->sendOrderFile($order, $file);
								$translatorService->addFileComment($order, $file, "NEW FILE");
							}
						}
					}
				}
            }

            // Update Order Status
			$order->elementIds = json_encode($elementIds);
			$order->targetSites = json_encode($targetSites);
			$order->trackChanges = Craft::$app->getRequest()->getBodyParam('trackChanges');
			$order->trackTargetChanges = Craft::$app->getRequest()->getBodyParam('trackTargetChanges');
			$order->includeTmFiles = Craft::$app->getRequest()->getBodyParam('includeTmFiles');
			$translatorService->updateOrder($order);

			Craft::$app->getElements()->saveElement($order);

			return $this->asSuccess($this->getSuccessMessage("Order updated."));
        } catch (Exception $e) {
            return $this->asFailure($this->getErrorMessage($e->getMessage()));
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

        /** @var craft\elements\User $currentUser */
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser->can('translations:orders:create')) {
            $this->setNotice('User does not have permission to "create draft or publish" this order.');
            return;
        }

        $orderId = Craft::$app->getRequest()->getParam('orderId');
        $fileIds = Craft::$app->getRequest()->getBodyParam('files', []);

        $order = $this->service->getOrderById($orderId);

        $wordCounts = [];
        $totalWordCount = 0;

        foreach ($order->getFilesById($fileIds) as $file) {
			$totalWordCount += $file->wordCount;
			$wordCounts[$file->elementId] = $file->wordCount;
        }

        $job = '';

        try {
            if ($totalWordCount > Constants::WORD_COUNT_LIMIT) {
                $job = Craft::$app->queue->push(new CreateDrafts([
                    'orderId' => $order->id,
                    'wordCounts' => $wordCounts,
                    'publish' => $action === 'publish',
                    'fileIds' => $fileIds,
                ]));

                $queueOrders = Craft::$app->getSession()->get('queueOrders');
                $queueOrders[$job] = $order->id;
                Craft::$app->getSession()->set('importQueued', 1);
                Craft::$app->getSession()->set('queueOrders', $queueOrders);
            } else {
                $job =  null;
                Translations::$plugin->draftRepository->createOrderDrafts(
                    $order->id, $wordCounts, $action === 'publish', $fileIds, null
                );
            }
        } catch (Exception $e) {
            $actionName = $action == "publish" ? "publish" : "merge";
            $order->logActivity(Translations::$plugin->translator->translate('app', "Could not $actionName draft Error: " . $e->getMessage()));
            Translations::$plugin->logHelper->log( '['. __METHOD__ .'] Couldn’t save the draft. Error: '.$e, Constants::LOG_LEVEL_ERROR );

            Craft::$app->getElements()->saveElement($order);
            $this->setError($e->getMessage());
            return;
        }

        if ($job) {
            $params = [
                'id' => (int) $job,
                'notice' => $action == "draft" ? 'Translation draft(s) saved' : 'Translation draft(s) published',
                'url' => Constants::URL_ORDER_DETAIL . $order->id
            ];
            Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
        } else {
            $this->setSuccess($action == "draft" ? 'Translation draft(s) saved' : 'Translation draft(s) published');
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
        $order = $this->service->getOrderById($orderId);
        if (!$order) {
            $this->setError("Order not found with ID '{$orderId}'.");
            return;
        }

        $translator = $order->translatorId ? Translations::$plugin->translatorRepository->getTranslatorById($order->translatorId) : null;

        if (! $translator) {
            $this->setError('Invalid translator.');
            return;
        }

        if ($translator->service === Constants::TRANSLATOR_ACCLARO) {
            $translatorService = $order->getTranslationService();

            $res = $translatorService->cancelOrder($order);

            if (empty($res)) {
                $this->setError("Unable to cancel order '{$order->title}'.");
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

        $this->setSuccess("Order canceled '{$order->title}'");

        return $this->redirect(Constants::URL_ORDER_DETAIL.$order->id, 302, true);
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
        $files = [];
        
        if ($selected = Craft::$app->getRequest()->getParam('files')) {
            $files = json_decode($selected, true);
        }

        $order = $this->service->getOrderById((int) $orderId);

        // Authenticate service
        $translationService = $order->getTranslationService();

        if (!$translationService->authenticate()) {
            $this->setError('Failed to authenticate API key.');
            return;
        }

        if ($order) {
            if ($order->shouldProcessByQueue()) {
                $job = Craft::$app->queue->push(new SyncOrderJob([
                    'description' => 'Syncing order '. $order->title,
                    'files' => $files,
                    'orderId' => $order->id
                ]));

                if ($job) {
                    $params = [
                        'id' => (int) $job,
                        'notice' => 'Done syncing order '. $order->title,
                        'url' => Constants::URL_ORDER_DETAIL . $order->id
                    ];
                    $this->setNotice("Order is being synced via queue. Refer queue for updates.");
                    Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(false, false, '. json_encode($params) .'); });');
                } else {
                    $this->setError("Cannot sync order '{$order->title}'.");
                    return $this->redirect(Constants::URL_ORDER_DETAIL . $order->id, 302, true);
                }
            } else {
                $translationService->syncOrder($order, $files);
                $this->setSuccess("Done syncing order '{$order->title}'");
                return $this->redirect(Constants::URL_ORDER_DETAIL . $order->id, 302, true);
            }
        }
    }

    /**
     * Sync order from translator service
     *
     * @return void
     */
    public function actionSyncOrders()
    {
        /** @var craft\elements\User $currentUser */
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser->can('translations:orders:import')) {
            $this->setNotice('User does not have permission to sync this order.');
            return;
        }

        $orders = $this->service->getInProgressOrders();
        $allFileCounts = $totalWordCount = 0;
        foreach ($orders as $order) {
            if ($order->translator->service !== Constants::TRANSLATOR_ACCLARO) {
                continue;
            }
            $totalWordCount += ($order->wordCount * count($order->getTargetSitesArray()));
            $allFileCounts += count($order->files);
        }

        $job = '';
        $url = Craft::$app->getRequest()->absoluteUrl;
        $translationService = null;

        try {
            foreach ($orders as $order) {
                if (!$translationService) {
                    $translationService = $order->getTranslationService();
                }

                // Only update acclaro orders
                if ($order->translator->service !== Constants::TRANSLATOR_ACCLARO) {
                    continue;
                }

                if ($totalWordCount > Constants::WORD_COUNT_LIMIT) {
                    $job = Craft::$app->queue->push(new SyncOrderJob([
                        'description' => 'Syncing order '. $order->title,
                        'orderId' => $order->id
                    ]));
                } else {
                    $translationService->syncOrder($order, []);
                }
            }
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            return;
        }

        if ($job) {
            $params = [
                'id' => (int) $job,
                'notice' => 'Done syncing orders',
                'url' => $url
            ];
            $this->setNotice('Order\'s are being synced via queue. Refer queue for updates.');
            Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
        } else {
            $this->setSuccess('Order sync complete.');
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

        /** @var craft\elements\User $currentUser */
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser->can('translations:orders:create')) {
            $this->setNotice('User does not have permission to create orders.');
            return;
        }

        $sourceSite = Craft::$app->getRequest()->getParam('sourceSite');

        if ($sourceSite && !Translations::$plugin->siteRepository->isSiteSupported($sourceSite)) {
            throw new HttpException(400, Translations::$plugin->translator
                ->translate('app', 'Source site is not supported'));
        }
        
        $orderId = Craft::$app->getRequest()->getBodyParam('id');
        $order = $this->service->getOrderById($orderId);

        if (! $order->isPending()) {
            $order = $this->service->makeNewOrder($sourceSite);
            $order->logActivity(Translations::$plugin->translator->translate('app', 'Order draft created'));
        }

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

            $elementIds = Craft::$app->getRequest()->getParam('elements');

            $requestedDueDate = Craft::$app->getRequest()->getParam('requestedDueDate');

            $translatorId = Craft::$app->getRequest()->getParam('translatorId');

            $title = Craft::$app->getRequest()->getParam('title');

            if (!$title) {
                $title = sprintf(
                    'Translation Order #%s',
                    $this->service->getOrdersCount() + 1
                );
            }

            $order->ownerId = Craft::$app->getRequest()->getParam('ownerId');

            $orderTags = Craft::$app->getRequest()->getParam('tags') ?? null;

            $order->tags = $orderTags ? json_encode($orderTags) : '[]';
            $order->title = $title;
            $order->trackChanges = Craft::$app->getRequest()->getBodyParam('trackChanges');
			$order->trackTargetChanges = Craft::$app->getRequest()->getBodyParam('trackTargetChanges');
			$order->includeTmFiles = Craft::$app->getRequest()->getBodyParam('includeTmFiles');
			$order->requestQuote = Craft::$app->getRequest()->getBodyParam('requestQuote');
            $order->sourceSite = $sourceSite;
            $order->targetSites = $targetSites ? json_encode($targetSites) : '[]';

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
                            "The target site(s) selected are not available for the entry “%s”. \
                            Please check your settings in Settings > Sections > %s to change this entry's \
                            target sites.",
                            $element->title,
                            $element->section->name
                        );

                        $this->setError($message);
                        return;
                    }
                }
            }

            $order->entriesCount = $entriesCount;
            $order->wordCount = array_sum($wordCounts);

            $success = Craft::$app->getElements()->saveElement($order, true, true, false);

            if (! $success) {
                Translations::$plugin->logHelper->log('[' . __METHOD__ . '] Couldn’t save the order', Constants::LOG_LEVEL_ERROR);
                $this->setError('Error saving Order.');
            } else {
                $this->setSuccess('Order Saved.');
                return $this->redirect($order->getCpEditUrl(), 302, true);
            }
        } catch (Exception $e) {
            Translations::$plugin->logHelper->log('[' . __METHOD__ . '] Couldn’t save the order. Error: ' . $e->getMessage(), Constants::LOG_LEVEL_ERROR);
            $this->setError($e->getMessage());
        }
    }

    /**
     * Update source content changes to order source
     *
     * @return JsonResponse
     */
    public function actionUpdateOrderFilesSource()
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $orderId = Craft::$app->getRequest()->getBodyParam('id');
        $order = Translations::$plugin->orderRepository->getOrderById((int) $orderId);

        if (! $order) return $this->asFailure($this->getErrorMessage('Order not found'));

        $elements = Craft::$app->getRequest()->getRequiredBodyParam('selected');
        if ($elements) $elements = json_decode($elements, true);

        // Authenticate service
        $translationService = $order->getTranslationService();
        if (! $translationService->authenticate()) {
            return $this->asFailure($this->getErrorMessage('Failed to authenticate API key.'));
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $changeLog = [];
            foreach ($order->getFiles() as $file) {
                if (in_array($file->elementId, $elements)) {
                    if ($file->isPublished()) continue;

                    $element = Translations::$plugin->elementRepository->getElementById($file->elementId, $file->sourceSite);

                    $file->source = Translations::$plugin->elementToFileConverter->convert(
                        $element,
                        Constants::FILE_FORMAT_XML,
                        [
                            'sourceSite'    => $file->sourceSite,
                            'targetSite'    => $file->targetSite,
                            'wordCount'     => $file->wordCount,
                            'orderId'       => $orderId,
                        ]
                    );

                    // Delete draft here so that it does not try to merge in existing draft when merged after updating
                    // source entry in order
                    if ($file->hasDraft()) {
                        Translations::$plugin->draftRepository->deleteDraft($file->draftId, $file->targetSite);
                        $file->draftId = null;
                    }
                    $file->status = Constants::FILE_STATUS_MODIFIED;
                    Translations::$plugin->fileRepository->saveFile($file);

                    if (!in_array($element->id, $changeLog)) {
                        array_push($changeLog, $element->id);
                        $order->logActivity(Translations::$plugin->translator->translate('app', "Source content updated {$element->title}."));
                    }

                    // Cancel old file and send new files to translator
                    if ($order->hasTranslator(Constants::TRANSLATOR_ACCLARO)) {
                        $translationService->addFileComment($order, $file, "CANCEL FILE");
                        $translationService->sendOrderFile($order, $file);
                        $translationService->addFileComment($order, $file, "NEW FILE");
                    }
                }
            }

            $oldStatus = $order->status;
            $order->status = Translations::$plugin->orderRepository->getNewStatus($order);

            if ($oldStatus != $order->status) {
                $order->logActivity(sprintf(
                    Translations::$plugin->translator->translate('app', 'Order status changed to \'%s\''),
                    $order->getStatusLabel()
                ));
            }

            if ($order->hasTranslator(Constants::TRANSLATOR_ACCLARO)) {
                if (! $order->isNew()) {
                    foreach ($order->getFiles() as $file) {
                        if ($file->isNew()) {
                            $file->status = Constants::FILE_STATUS_IN_PROGRESS;
                            Translations::$plugin->fileRepository->saveFile($file);
                        }
                    }
                }
            }

            Craft::$app->getElements()->saveElement($order, true, true, false);
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            Translations::$plugin->logHelper->log($e, Constants::LOG_LEVEL_ERROR);

            return $this->asFailure($this->getErrorMessage($e->getMessage()));
        }

        return $this->asSuccess($this->getSuccessMessage('Entries updated.'));
    }

    /**
     * Accept order quote
     */
    public function actionAcceptQuote()
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $orderId = Craft::$app->getRequest()->getBodyParam('id');
        $comment = Craft::$app->getRequest()->getBodyParam('quoteComment');
        $order = Translations::$plugin->orderRepository->getOrderById((int) $orderId);

        if (!$order) return $this->asFailure($this->getErrorMessage('Order not found'));

        try {
            $translator = $order->getTranslator();
            if ($translator->service != Constants::TRANSLATOR_DEFAULT) {
                $translatorService = $order->getTranslationService();

                $response = $translatorService->acceptOrderQuote($order->serviceOrderId, $comment);

                if (empty($response)) {
                    return $this->asFailure($this->getErrorMessage('Unable to approve quote'));
                }
            }
        } catch (\Exception $e) {
            Translations::$plugin->logHelper->log($e, Constants::LOG_LEVEL_ERROR);
            return $this->asFailure($this->getErrorMessage($e->getMessage()));
        }

        $order->status = Constants::ORDER_STATUS_NEW;
        $order->logActivity("Order quote accepted");
        Craft::$app->getElements()->saveElement($order, true, true, false);

        return $this->asSuccess($this->getSuccessMessage("Quote approve request sent"));
    }

    /**
     * Decline order quote
     */
    public function actionDeclineQuote()
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $orderId = Craft::$app->getRequest()->getBodyParam('id');
        $comment = Craft::$app->getRequest()->getBodyParam('quoteComment');
        $order = Translations::$plugin->orderRepository->getOrderById((int) $orderId);

        if (!$order) return $this->asFailure($this->getErrorMessage('Order not found'));

        try {
            $translator = $order->getTranslator();
            if ($translator->service != Constants::TRANSLATOR_DEFAULT) {
                $translatorService = $order->getTranslationService();

                $response = $translatorService->declineOrderQuote($order->serviceOrderId, $comment);

                if (empty($response)) {
                    return $this->asFailure($this->getErrorMessage('Unable to decline quote'));
                }
            }
        } catch (\Exception $e) {
            Translations::$plugin->logHelper->log($e, Constants::LOG_LEVEL_ERROR);
            return $this->asFailure($this->getErrorMessage($e->getMessage()));
        }

        $order->status = Constants::ORDER_STATUS_GETTING_QUOTE;
        $order->logActivity("Order quote declined");
        Craft::$app->getElements()->saveElement($order, true, true, false);

        return $this->asSuccess($this->getSuccessMessage("Quote decline request sent"));
    }
}
