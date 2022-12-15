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
use craft\web\Controller;
use craft\elements\Entry;
use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\base\AlertsTrait;
use acclaro\translations\services\Services;
use acclaro\translations\services\job\RegeneratePreviewUrls;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class BaseController extends Controller
{
    use AlertsTrait;

    /**
     * @var    array|int|bool Allows anonymous access to this controller's actions.
     * @access protected
     */
    protected array|int|bool $allowAnonymous = true;

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

        $this->pluginVersion = Craft::$app->getPlugins()->getPlugin(Constants::PLUGIN_HANDLE)->getVersion();
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
        if ($order->isPublished()) {
            Craft::$app->end('Order already published');
        }

        // don't process canceled orders
        if ($order->isCanceled()) {
            Craft::$app->end('Can not update canceled order');
        }

        $translationService = $order->getTranslationService();

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

        $translationService = $order->getTranslationService();

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

    public function actionAddElementsToOrder()
    {
        $this->requireLogin();
        $this->requirePostRequest();
        if (!Translations::$plugin->userRepository->userHasAccess('translations:orders:create')) {
            $this->setNotice("User doesn't have permission to add elements to this order.");
            return;
        }

        $orderId = Craft::$app->getRequest()->getParam('id');

        $sourceSite = Craft::$app->getRequest()->getParam('sourceSite');

        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        if (!$order) {
            $this->setError("Order not found with ID '{$orderId}'.");
            return;
        }

        if (!Translations::$plugin->siteRepository->isSiteSupported($sourceSite)) {
            $this->setError('Source site is not supported.');
            return;
        }

        if ((int) $order->sourceSite !== (int) $sourceSite) {
            $this->setError('All entries within an order must have the same source site.');
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

                    $element = Translations::$plugin->elementRepository->getElementById($elementId, $order->sourceSite);

                    if ($element instanceof Entry) {
                        $sites = array();

                        $elementSection = Craft::$app->getSections()->getSectionById($element->sectionId);
                        foreach ($elementSection->getSiteIds() as $key => $site) {
                            $sites[] = $site;
                        }

                        $hasTargetSites = !array_diff(json_decode($order->targetSites), $sites);

                        if (!$hasTargetSites) {
                            $message = sprintf(
                                "The target site(s) on this order are not available for the entry “%s”. Please check your settings in in Sections > %s.",
                                $element->title,
                                $element->section->name
                            );

                            $this->setError($message);
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
            Translations::$plugin->logHelper->log( '['. __METHOD__ .'] Couldn’t save the order', Constants::LOG_LEVEL_ERROR );
        }

        $this->setSuccess('Added to order.');

        $this->redirect(Constants::URL_ORDER_DETAIL . $order->id, 302, true);
    }

    public function actionRegeneratePreviewUrls()
    {
        $this->requireLogin();
        $this->requirePostRequest();
        if (!Translations::$plugin->userRepository->userHasAccess('translations:orders:edit')) {
            $this->setNotice('User does have permission to regenerate preview urls.');
            return;
        }

        $orderId = Craft::$app->getRequest()->getParam('orderId');
        $files = json_decode(Craft::$app->getRequest()->getBodyParam('files'), true);

        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        // Authenticate service
        $translationService = $order->getTranslationService();

        if (!$translationService->authenticate()) {
            $this->setError('Failed to authenticate API key.');
            return;
        }

        if ($order) {

            $totalWordCount = ($order->wordCount * count($order->getTargetSitesArray()));
            $filePreviewUrls = Translations::$plugin->fileRepository->getOrderFilesPreviewUrl($order);

            if ($totalWordCount > Constants::WORD_COUNT_LIMIT) {
                $job = Craft::$app->queue->push(new RegeneratePreviewUrls([
                    'description' => 'Regenerating preview urls for '. $order->title,
                    'orderId' => $order->id,
                    'filePreviewUrls' => $filePreviewUrls,
                    'files' => $files,
                ]));

                if ($job) {
                    Craft::$app->getSession()->set('importQueued', "1");
                    $params = [
                        'id' => (int) $job,
                        'notice' => 'Done building draft previews',
                        'url' => Constants::URL_ORDER_DETAIL . $order->id
                    ];
                    Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
                } else {
                    $this->redirect(Constants::URL_ORDER_DETAIL . $order->id, 302, true);
                }
            } else {
                Translations::$plugin->fileRepository->regeneratePreviewUrls($order, $filePreviewUrls, $files);
                $this->setSuccess('Done building draft previews.');
            }
        }
    }

    // PRIVATE METHODS
    private function logIncomingRequest($endpoint)
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
            $request .= "\n" . http_build_query($_POST);
        }

        $tempPath = Craft::$app->getPath()->getTempPath() . '/translations';

        if (!is_dir($tempPath)) {
            mkdir($tempPath);
        }

        $filename = 'request-' . $endpoint . '-' . date('YmdHis') . '.txt';

        $filePath = $tempPath . '/' . $filename;

        $handle = fopen($filePath, 'w+');

        fwrite($handle, $request);

        fclose($handle);
    }
}
