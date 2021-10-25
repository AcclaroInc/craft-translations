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

use acclaro\translations\Constants;
use acclaro\translations\services\AcclaroService;
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
use craft\elements\Asset;

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

    // Public Methods
    // =========================================================================
    
    public function __construct(
        $id,
        $module = null
    ) {
        parent::__construct($id, $module);

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
        if ($order->status === Constants::ORDER_STATUS_PUBLISHED) {
            Craft::$app->end('Order already published');
        }

        // don't process canceled orders
        if ($order->status === Constants::ORDER_STATUS_CANCELED) {
            Craft::$app->end('Can not update canceled order');
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
        if ($file->status === Constants::FILE_STATUS_PUBLISHED) {
            Craft::$app->end('File already published');
        }

        // don't process canceled files
        if ($file->status === Constants::FILE_STATUS_CANCELED) {
            Craft::$app->end('Can not update canceled file');
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
            Craft::error( '['. __METHOD__ .'] Couldn’t save the order', 'translations' );
        }

        Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Added to order.'));

        $this->redirect('translations/orders/detail/'. $order->id, 302, true);
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
        $authenticate = (new AcclaroService())->authenticateService($service, $settings);
        
        if (!$authenticate && $service === Constants::TRANSLATOR_ACCLARO) {
            $message = Translations::$plugin->translator->translate('app', 'Invalid API key');
            Craft::$app->getSession()->setError($message);
            return;
        }

        if ($order) {

            $totalWordCount = ($order->wordCount * count($order->getTargetSitesArray()));

            if ($totalWordCount > Constants::WORD_COUNT_LIMIT) {
                $job = Craft::$app->queue->push(new RegeneratePreviewUrls([
                    'description' => 'Regenerating preview urls for '. $order->title,
                    'order' => $order
                ]));

                if ($job) {
                    $params = [
                        'id' => (int) $job,
                        'notice' => 'Done building draft previews',
                        'url' => 'translations/orders/detail/'. $order->id
                    ];
                    Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
                } else {
                    $this->redirect('translations/orders/detail/'. $order->id, 302, true);
                }
            } else {
                Translations::$plugin->fileRepository->regeneratePreviewUrls($order);
                Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app',  'Done building draft previews'));
            }
        }
    }

    public function actionGetFileDiff() {

        $variables = Craft::$app->getRequest()->resolve()[1];
        $fileId = isset($variables['fileId']) ? $variables['fileId'] : null;

        $file = Translations::$plugin->fileRepository->getFileById($fileId);
        $data = [];

        if ($file && ($file->status === Constants::FILE_STATUS_COMPLETE || $file->status === Constants::FILE_STATUS_PUBLISHED)) {
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
            
            switch (get_class($element)) {
                case Asset::class:
                    if ($file->status === Constants::FILE_STATUS_COMPLETE) {
                        $element = Translations::$plugin->assetDraftRepository->getDraftById($file->draftId);
                    } else {
                        $element = Translations::$plugin->assetDraftRepository->getAssetById($file->elementId, $file->targetSite);
                    }
    
                    $data['entryName'] = $element->name;
                    break;
                case GlobalSet::class:
                    if ($file->status === Constants::FILE_STATUS_COMPLETE) {
                        $element = Translations::$plugin->globalSetDraftRepository->getDraftById($file->draftId);
                    } else {
                        $element = Translations::$plugin->globalSetRepository->getSetById($file->elementId, $file->targetSite);
                    }
    
                    $data['entryName'] = $element->name;
                    break;
                case Category::class:
                    if ($file->status === Constants::FILE_STATUS_COMPLETE) {
                        $element = Translations::$plugin->categoryDraftRepository->getDraftById($file->draftId);
                    } else {
                        $element = Translations::$plugin->categoryRepository->getCategoryById($file->elementId, $file->targetSite);
                    }
                    $data['entryName'] = $element->title;
                    break;
                
                default:
                    // Now we can get the element
                    if ($file->status === Constants::FILE_STATUS_COMPLETE) {
                        $element = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);
                    } else {
                        $element = Craft::$app->getElements()->getElementById($file->elementId, null, $file->targetSite);
                    }
                    $countElement = $element;
                    $data['entryName'] = Craft::$app->getEntries()->getEntryById($element->id) ? Craft::$app->getEntries()->getEntryById($element->id)->title : '';
                    break;
            }

            $wordCount = (Translations::$plugin->elementTranslator->getWordCount($element) - $file->wordCount);


            // Create data array
            $data['entryId'] = $element->id;
            $data['fileId'] = $file->id;
            $data['siteId'] = $element->siteId;
            $data['siteLabel'] = Craft::$app->sites->getSiteById($element->siteId)->name. '<span class="light"> ('. Craft::$app->sites->getSiteById($element->siteId)->language. ')</span>';
            $handle = isset($element->section) ? $element->section->handle : '';
            $data['entryUrl'] = UrlHelper::cpUrl('entries/'.$handle.'/'.$element->id.'/'.Craft::$app->sites->getSiteById($element->siteId)->handle);
            $data['dateApplied'] = ($file->status === Constants::FILE_STATUS_PUBLISHED) ? $element->dateUpdated->format('M j, Y g:i a') : '--' ;
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

        if ($file && ($file->status === Constants::FILE_STATUS_COMPLETE || $file->status === Constants::FILE_STATUS_PUBLISHED)) {

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

                            if ($file->status === Constants::FILE_STATUS_COMPLETE || $file->status === Constants::FILE_STATUS_PUBLISHED) {
                                continue;
                            } else if($file->status === Constants::FILE_STATUS_CANCELED || $file->status === Constants::FILE_STATUS_FAILED) {
                                $file->status === Constants::FILE_STATUS_IN_PROGRESS;
                            }

                            $file = Translations::$plugin->draftRepository->createDrafts($element, $order, $site, $wordCounts, $file);
                        }
                    } else {
                        $order->logActivity(sprintf(Translations::$plugin->translator->translate('app', 'Adding file '.$element->title)));
                        $file = Translations::$plugin->draftRepository->createDrafts($element, $order, $site, $wordCounts);
                        $newAddElement[] = $element->id;
                    }

                    if ($order->translator->service !== Constants::TRANSLATOR_DEFAULT) {
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
            Craft::error( '['. __METHOD__ .'] Add Entries Failed. Error: '.$e->getMessage(), 'translations' );
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
