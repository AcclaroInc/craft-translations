<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\translation;

use Craft;
use DateTime;
use Exception;
use craft\elements\GlobalSet;
use acclaro\translations\services\App;
use acclaro\translations\elements\Order;
use acclaro\translations\models\FileModel;
use acclaro\translations\Translations;
use acclaro\translations\services\api\AcclaroApiClient;
use acclaro\translations\services\job\UpdateDraftFromXml;
use acclaro\translations\services\job\Factory as JobFactory;

class AcclaroTranslationService implements TranslationServiceInterface
{
    /**
     * @var boolean
     */
    protected $sandboxMode = false;
    
    /**
     * @var acclaro\translations\services\api\AcclaroApiClient
     */
    protected $acclaroApiClient;

    /**
     * @param array                                                         $settings
     * @param acclaro\translations\services\api\AcclaroApiClient    $acclaroApiClient
     */
    public function __construct(
        array $settings,
        AcclaroApiClient $acclaroApiClient
    ) {
        if (!isset($settings['apiToken'])) {
            throw new Exception('Missing apiToken');
        }

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

        return !empty($response->plunetid);
    }

    /**
     * {@inheritdoc}
     */
    public function updateOrder(JobFactory $jobFactory, Order $order)
    {
        $orderResponse = $this->acclaroApiClient->getOrder($order->serviceOrderId);

        if ($order->status !== $orderResponse->status) {
            $order->logActivity(
                sprintf(Translations::$plugin->translator->translate('app', 'Order status changed to %s'), $orderResponse->status)
            );
        }

        $order->status = $orderResponse->status;
    }

    /**
     * {@inheritdoc}
     */
    public function updateFile(JobFactory $jobFactory, Order $order, FileModel $file)
    {
        $fileInfoResponse = $this->acclaroApiClient->getFileInfo($order->serviceOrderId);

        // find the matching file
        foreach ($fileInfoResponse as $fileInfo) {
            if ($fileInfo->fileid == $file->serviceFileId) {
                break;
            }

            $fileInfo = null;
        }

        if (empty($fileInfo->targetfile)) {
            return;
        }

        $targetFileId = $fileInfo->targetfile;

        $fileStatusResponse = $this->acclaroApiClient->getFileStatus($order->serviceOrderId, $targetFileId);

        $file->status = $fileStatusResponse->status;

        // download the file
        $target = $this->acclaroApiClient->getFile($order->serviceOrderId, $targetFileId);

        if ($target) {
            $file->target = $target;

            $element = Craft::$app->elements->getElementById($file->elementId, null, $file->sourceSite);

            if ($element instanceof GlobalSet) {
                $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($file->draftId, $file->targetSite);
            } else {
                $draft = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);
            }

            Translations::$plugin->jobFactory->dispatchJob(UpdateDraftFromXml::class, $element, $draft, $target, $file->sourceSite, $file->targetSite);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function sendOrder(Order $order)
    {
        $orderResponse = $this->acclaroApiClient->createOrder(
            $order->title,
            $order->comments,
            $order->requestedDueDate ? $order->requestedDueDate->format(DateTime::ISO8601) : '',
            $order->id,
            $order->wordCount
        );

        $order->serviceOrderId = (!is_null($orderResponse)) ? $orderResponse->orderid : '';
        $order->status = (!is_null($orderResponse)) ? $orderResponse->status : '';

        $orderCallbackResponse = $this->acclaroApiClient->requestOrderCallback(
            $order->serviceOrderId,
            Translations::$plugin->urlGenerator->generateOrderCallbackUrl($order)
        );

        $tempPath = Craft::$app->path->getTempPath();

        foreach ($order->files as $file) {
            $element = Craft::$app->elements->getElementById($file->elementId);

            $sourceSite = Translations::$plugin->siteRepository->normalizeLanguage(Craft::$app->getSites()->getSiteById($file->sourceSite)->language);
            $targetSite = Translations::$plugin->siteRepository->normalizeLanguage(Craft::$app->getSites()->getSiteById($file->targetSite)->language);

            if ($element instanceof GlobalSetModel) {
                $filename = ElementHelper::createSlug($element->name).'-'.$targetSite.'.xml';
            } else {
                $filename = $element->slug.'-'.$targetSite.'.xml';
            }

            $path = $tempPath.'/'.$filename;

            $stream = fopen($path, 'w+');

            fwrite($stream, $file->source);

            $fileResponse = $this->acclaroApiClient->sendSourceFile(
                $order->serviceOrderId,
                $sourceSite,
                $targetSite,
                $file->id,
                $path
            );

            // var_dump($fileResponse);
            // var_dump(isset($fileResponse->errorCode));
            // if (isset($fileResponse->errorCode)) {
            //     // Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Error Code: '.$fileResponse->errorCode.' '. $fileResponse->errorMessage));
            //     throw new HttpException(400, Translations::$plugin->translator->translate('app', 'Error Code: '.$fileResponse->errorCode.' '. $fileResponse->errorMessage));
            // } else {
                // var_dump('$fileResponse');
                // var_dump($fileResponse);
                // var_dump($file);
                // die;
                $file->serviceFileId = $fileResponse->fileid;
                $file->status = $fileResponse->status;

                $fileCallbackResponse = $this->acclaroApiClient->requestFileCallback(
                    $order->serviceOrderId,
                    $file->serviceFileId,
                    Translations::$plugin->urlGenerator->generateFileCallbackUrl($file)
                );

                $this->acclaroApiClient->addReviewUrl(
                    $order->serviceOrderId,
                    $file->serviceFileId,
                    $file->previewUrl
                );
            // }

            fclose($stream);

            unlink($path);
        }

        $submitOrderResponse = $this->acclaroApiClient->submitOrder($order->serviceOrderId);

        $order->status = $submitOrderResponse->status;
    }

    public function getOrderUrl(Order $order)
    {
        $subdomain = $this->sandboxMode ? 'apisandbox' : 'my';

        return sprintf('https://%s.acclaro.com/portal/vieworder.php?id=%s', $subdomain, $order->serviceOrderId);
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
}