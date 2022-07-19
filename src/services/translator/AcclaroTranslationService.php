<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\translator;

use Craft;
use craft\elements\Asset;
use craft\elements\GlobalSet;
use craft\helpers\ElementHelper;

use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\elements\Order;
use acclaro\translations\models\FileModel;
use acclaro\translations\services\api\AcclaroApiClient;
use acclaro\translations\services\job\acclaro\SendOrder;
use acclaro\translations\services\job\acclaro\UpdateReviewFileUrls;

class AcclaroTranslationService implements TranslationServiceInterface
{
    /**
     * @var boolean
     */
    protected $sandboxMode = false;

    /**
     * @var \acclaro\translations\services\api\AcclaroApiClient
     */
    protected $acclaroApiClient;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @param array                                                         $settings
     * @param \acclaro\translations\services\api\AcclaroApiClient    $acclaroApiClient
     */
    public function __construct(
        array $settings,
        AcclaroApiClient $acclaroApiClient
    ) {
        if (!isset($settings['apiToken'])) {
            throw new \Exception('Missing apiToken');
        }

        $this->settings = $settings;

        $this->sandboxMode = !empty($settings['sandboxMode']);

        $this->acclaroApiClient = $acclaroApiClient ?: new AcclaroApiClient(
            $settings['apiToken'],
            !empty($settings['sandboxMode'])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate()
    {
        $response = $this->acclaroApiClient->getAccount();

        return !empty($response);
    }

    /**
     * {@inheritdoc}
     */
    public function updateOrder(Order $order)
    {
        if ($order->isCanceled()) {
            $error = sprintf('Can not update canceled order. OrderId: %s', $order->id);
            Craft::error($error, Constants::PLUGIN_HANDLE);
            return;
        }

        $orderResponse = $this->acclaroApiClient->getOrder($order->serviceOrderId);

        if (empty($orderResponse->status)) {
            $error = sprintf('Empty order response from acclaro. OrderId: %s', $order->id);
            Craft::error($error, Constants::PLUGIN_HANDLE);
            return;
        }

        if ($orderResponse->status === Constants::ORDER_STATUS_IN_PROGRESS) {
            foreach ($order->getFiles() as $file) {
                if ($file->isNew()) {
                    $file->status = Constants::FILE_STATUS_IN_PROGRESS;
                    Translations::$plugin->fileRepository->saveFile($file);
                }
            }
        }

        // Ignore changing order status untill order is in quote flow
        if ($order->isGettingQuote() || $order->isAwaitingApproval()) {
            $order->status = $orderResponse->status;
        } else {
            $orderStatus = Translations::$plugin->orderRepository->getNewStatus($order);

            if ($order->status !== $orderStatus) {
                $order->status = $orderStatus;
                $order->logActivity(
                    sprintf(Translations::$plugin->translator->translate('app', 'Order status changed to \'%s\''), $order->getStatusLabel())
                );
            }
        }

        if ($order->title !== $orderResponse->name) {
            Translations::$plugin->orderRepository->saveOrderName($order->id, $orderResponse->name);
        }

        // check if due date set then update it
        if($orderResponse->duedate){
            $dueDate = new \DateTime($orderResponse->duedate);
            $order->orderDueDate = $dueDate->format('Y-m-d H:i:s');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateFile(Order $order, FileModel $file)
    {
        try {
            if ($file->isCanceled()) {
                $error = sprintf('Can not update canceled file. FileId: %s', $file->id);
                Craft::error($error, Constants::PLUGIN_HANDLE);
                return;
            }

            $fileInfoResponse = $this->acclaroApiClient->getFileInfo($order->serviceOrderId);

            if (!is_array($fileInfoResponse)) {
                $error = sprintf('Invalid file Info from acclaro. FileId: %s', $file->id);
                Craft::error($error, Constants::PLUGIN_HANDLE);
                return;
            }
            // find the matching file
            foreach ($fileInfoResponse as $fileInfo) {
                if ($fileInfo->fileid == $file->serviceFileId) break;

                $fileInfo = null;
            }

            /** @var object $fileInfo */
            if (empty($fileInfo->targetfile)) {
                if ($fileInfo->filetype == Constants::ACCLARO_SOURCE_FILE_TYPE) {
                    Craft::error('[' . __METHOD__ . '] target file missing for fileId: ' . $file->id , 'translations');
                }
                return;
            }

            $targetFileId = $fileInfo->targetfile;

            $fileStatusResponse = $this->acclaroApiClient->getFileStatus($order->serviceOrderId, $targetFileId);

            $file->status = $fileStatusResponse->status === Constants::FILE_STATUS_COMPLETE ?
                Constants::FILE_STATUS_REVIEW_READY : $fileStatusResponse->status;
            $file->dateDelivered = new \DateTime();

            // download the file
            $target = $this->acclaroApiClient->getFile($order->serviceOrderId, $targetFileId);

            if ($target) $file->target = $target;

        } catch (\Exception $e) {
            Craft::error(  '['. __METHOD__ .'] Couldn’t update file. Error: '.$e->getMessage(), 'translations' );
        }
    }

    /**
     * Get order status on acclaro
     *
     * @param [type] $order
     * @return void|string
     */
    public function getOrderStatus($order)
    {
        $orderResponse = $this->acclaroApiClient->getOrder($order->serviceOrderId);
        return empty($orderResponse) || is_array($orderResponse) ? null : $orderResponse->status;
    }

    /**
     * {@inheritdoc}
     */
    public function sendOrder(Order $order)
    {
        $job = Craft::$app->queue->push(new SendOrder([
            'description' => Constants::JOB_ACCLARO_SENDING_ORDER,
            'order' => $order,
            'sandboxMode' => $this->sandboxMode,
            'settings' => $this->settings
        ]));

        return $job;
    }

    /**
     * {@inheritdoc}
     */
    public function updateReviewFileUrls(Order $order)
    {
        $job = Craft::$app->queue->push(new UpdateReviewFileUrls([
            'description' => Constants::JOB_ACCLARO_UPDATING_REVIEW_URL,
            'order' => $order,
            'sandboxMode' => $this->sandboxMode,
            'settings' => $this->settings
        ]));
    }

    public function getOrderUrl(Order $order)
    {
        $subdomain = $this->sandboxMode ? 'apisandbox' : 'my';

        return sprintf('https://%s.acclaro.com/orders/details/%s', $subdomain, $order->serviceOrderId);
    }

    public function getLanguages()
    {
        return $this->acclaroApiClient->getLanguages();
    }

    public function getLanguagePairs($source)
    {
        return $this->acclaroApiClient->getLanguagePairs($source);
    }

    public function editOrderName($orderId, $name)
    {
        return $this->acclaroApiClient->editOrderName($orderId, $name);
    }

    public function sendOrderFile($order, $file) {

        $tempPath = Craft::$app->path->getTempPath();
        $acclaroApiClient = $this->acclaroApiClient;

        if ($file) {

            $element = Translations::$plugin->elementRepository->getElementById($file->elementId, $file->sourceSite);

            $sourceSite = Translations::$plugin->siteRepository->normalizeLanguage(Craft::$app->getSites()->getSiteById($file->sourceSite)->language);
            $targetSite = Translations::$plugin->siteRepository->normalizeLanguage(Craft::$app->getSites()->getSiteById($file->targetSite)->language);

            if ($element instanceof GlobalSet) {
                $filename = ElementHelper::normalizeSlug($element->name).'-'.$targetSite.'.'.Constants::FILE_FORMAT_XML;
            } else if ($element instanceof Asset) {
                $assetFilename = $element->getFilename();
                $fileInfo = pathinfo($element->getFilename());
                $filename = $file->elementId . '-' . basename($assetFilename,'.'.$fileInfo['extension']) . '-' . $targetSite . '.' . Constants::FILE_FORMAT_XML;
            } else {
                $filename = $element->slug.'-'.$targetSite.'.'.Constants::FILE_FORMAT_XML;
            }

            $path = $tempPath .'/'. $file->elementId .'-'. $filename;

            $stream = fopen($path, 'w+');

            fwrite($stream, $file->source);

            $fileResponse = $acclaroApiClient->sendSourceFile(
                $order->serviceOrderId,
                $sourceSite,
                $targetSite,
                $path
            );

            $file->serviceFileId = $fileResponse->fileid ? $fileResponse->fileid : $file->id;
            $file->status = Constants::FILE_STATUS_NEW;

            $acclaroApiClient->requestFileCallback(
                $order->serviceOrderId,
                $file->serviceFileId,
                Translations::$plugin->urlGenerator->generateFileCallbackUrl($file)
            );

            Translations::$plugin->fileRepository->saveFile($file);

            fclose($stream);

            unlink($path);
        }
    }

    /**
     * @param \acclaro\translations\models\FileModel $file
     */
    public function sendOrderReferenceFile($order, $file) {
        $tempPath = Craft::$app->path->getTempPath();
        $acclaroApiClient = $this->acclaroApiClient;

        if ($file) {
            $sourceSite = Translations::$plugin->siteRepository->normalizeLanguage(Craft::$app->getSites()->getSiteById($file->sourceSite)->language);
            $targetSite = Translations::$plugin->siteRepository->normalizeLanguage(Craft::$app->getSites()->getSiteById($file->targetSite)->language);

            $tmFile = $file->getTmMisalignmentFile();
            $path = $tempPath .'-'. $tmFile['fileName'];

            $stream = fopen($path, 'w+');

            fwrite($stream, $tmFile['fileContent']);

            $acclaroApiClient->sendReferenceFile(
                $order->serviceOrderId,
                $sourceSite,
                $targetSite,
                $path
            );

            $file->reference = $tmFile['reference'];
            Translations::$plugin->fileRepository->saveFile($file);

            fclose($stream);
            unlink($path);
        }
    }

    /**
     * Cancel an Acclaro order.
     *
     * @return void
     */
    public function cancelOrder($order)
    {
        return $this->acclaroApiClient->addOrderComment($order->serviceOrderId, Constants::ACCLARO_ORDER_CANCEL);
    }

    /**
     * Add comment to Acclaro order.
     *
     * @return void
     */
    public function addFileComment($order, $file, $comment)
    {
        $this->acclaroApiClient->addFileComment($order->serviceOrderId, $file->serviceFileId, $comment);
    }

    /**
     * Cancel an Acclaro order.
     *
     * @return void
     */
    public function editOrderTags($order, $newTags)
    {
        $oldTags = [];

        foreach (json_decode($order->tags, true) as $tagId) {
            $tag = Craft::$app->getTags()->getTagById($tagId);
            if ($tag) {
                array_push($oldTags, $tag->title);
            }
        }

        $remove = array_diff($oldTags, $newTags);
        $add = array_diff($newTags, $oldTags);

        if (! empty($remove)) {
            foreach ($remove as $title) {
                $this->acclaroApiClient->removeOrderTags($order->serviceOrderId, $title);
            }
        }

        if (! empty($add)) {
            foreach ($add as $title) {
                $this->acclaroApiClient->addOrderTags($order->serviceOrderId, $title);
            }
        }
    }

    public function getOrderQuote($orderId)
    {
        return $this->acclaroApiClient->getQuoteDetails($orderId);
    }

    public function acceptOrderQuote($orderId)
    {
        $this->acclaroApiClient->approveQuote($orderId);
    }

    public function getOrderQuoteDocument($orderId)
    {
        return $this->acclaroApiClient->getQuoteDocument($orderId);
    }

    public function declineOrderQuote($orderId, $comment = '')
    {
        return $this->acclaroApiClient->declineQuote($orderId, $comment);
    }
}
