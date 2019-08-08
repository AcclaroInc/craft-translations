<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\job\acclaro;

use Craft;
use DateTime;
use Exception;

use craft\queue\BaseJob;
use craft\elements\GlobalSet;
use craft\elements\Entry;
use acclaro\translations\Translations;
use acclaro\translations\services\api\AcclaroApiClient;

class SendOrder extends BaseJob
{
    public $order;
    public $sandboxMode;
    public $settings;

    public function execute($queue)
    {
        $acclaroApiClient = new AcclaroApiClient(
            $this->settings['apiToken'],
            !empty($this->settings['sandboxMode'])
        );

        $order = $this->order;

        $totalElements = count($order->files);
        $currentElement = 0;

        $orderResponse = $acclaroApiClient->createOrder(
            $order->title,
            $order->comments,
            $order->requestedDueDate ? DateTime::createFromFormat(DateTime::ISO8601, $order->requestedDueDate) : '',
            $order->id,
            $order->wordCount
        );

        $order->serviceOrderId = (!is_null($orderResponse)) ? $orderResponse->orderid : '';
        $order->status = (!is_null($orderResponse)) ? $orderResponse->status : '';

        $orderCallbackResponse = $acclaroApiClient->requestOrderCallback(
            $order->serviceOrderId,
            Translations::$plugin->urlGenerator->generateOrderCallbackUrl($order)
        );

        $tempPath = Craft::$app->path->getTempPath();

        foreach ($order->files as $file) {
            $this->setProgress($queue, $currentElement++ / $totalElements);

            $element = Craft::$app->elements->getElementById($file->elementId, null, $file->sourceSite);

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

            $fileResponse = $acclaroApiClient->sendSourceFile(
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
                $file->serviceFileId = $fileResponse->fileid ? $fileResponse->fileid : $file->id;
                $file->status = $fileResponse->status;

                $fileCallbackResponse = $acclaroApiClient->requestFileCallback(
                    $order->serviceOrderId,
                    $file->serviceFileId,
                    Translations::$plugin->urlGenerator->generateFileCallbackUrl($file)
                );

                $acclaroApiClient->addReviewUrl(
                    $order->serviceOrderId,
                    $file->serviceFileId,
                    $file->previewUrl
                );
            // }

            fclose($stream);

            unlink($path);
        }

        $submitOrderResponse = $acclaroApiClient->submitOrder($order->serviceOrderId);

        $order->status = $submitOrderResponse->status;

        $order->dateOrdered = new DateTime();

        $success = Craft::$app->getElements()->saveElement($order);

        foreach ($order->files as $file) {
            Translations::$plugin->fileRepository->saveFile($file);
        }
    }

    protected function defaultDescription()
    {
        return 'Sending order to Acclaro';
    }
}