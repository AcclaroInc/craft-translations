<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\repository;

use Craft;
use DateTime;
use Exception;
use craft\db\Query;
use craft\helpers\Db;
use craft\records\Element;
use craft\helpers\UrlHelper;
use craft\elements\GlobalSet;
use craft\elements\db\ElementQuery;
use acclaro\translations\Translations;
use acclaro\translations\elements\Order;
use acclaro\translations\records\OrderRecord;
use acclaro\translations\services\job\SyncOrder;
use acclaro\translations\services\api\AcclaroApiClient;
use acclaro\translations\services\job\acclaro\SendOrder;

class OrderRepository
{
    /**
     * @param  int|string $orderId
     * @return \acclaro\translations\elements\Order|null
     */
    public function getOrderById($orderId)
    {
        return Craft::$app->elements->getElementById($orderId);
    }

    /**
     * @param  int|string $orderId
     * @return \acclaro\translations\elements\Order|null
     */
    public function getOrderByIdWithTrashed($orderId)
    {
        return Element::findOne(['id' => $orderId]);
    }

    /**
     * @return \craft\elements\db\ElementQuery
     */
    public function getDraftOrders()
    {
        $results = Order::find()->andWhere(Db::parseParam('translations_orders.status', 'new'))->all();
        return $results;
    }

    /**
     * @return int
     */
    public function getOrdersCount()
    {
        $orderCount = Order::find()->count();
        return $orderCount;
    }

    /**
     * @return \craft\elements\db\ElementQuery
     */
    public function getInProgressOrders()
    {
        $inProgressOrders = Order::find()
            ->andWhere(Db::parseParam('translations_orders.status', array(
                'getting quote', 'needs approval', 'in preparation', 'in progress'
            )))
            ->all();
            
        return $inProgressOrders;
    }
    
    /**
     * @return \craft\elements\db\ElementQuery
     */
    public function getInProgressOrdersByTranslatorId($translatorId)
    {
        $pendingOrders = Order::find()
            ->andWhere(Db::parseParam('translations_orders.translatorId', $translatorId))
            ->all();

        return $pendingOrders;
    }
    
    /**
     * @return \craft\elements\db\ElementQuery
     */
    public function getCompleteOrders()
    {
        $results = Order::find()->andWhere(Db::parseParam('translations_orders.status', 'complete'))->all();
        return $results;
    }
    
    public function getOrderStatuses()
    {
        return array(
            'new' => 'new',
            'getting quote' => 'getting quote',
            'needs approval' => 'needs approval',
            'in preparation' => 'in preparation',
            'in progress' => 'in progress',
            'complete' => 'complete',
            'canceled' => 'cancelled',
            'published' => 'published',
        );
    }

    /**
     * @return \acclaro\translations\elements\Order
     */
    public function makeNewOrder($sourceSite = null)
    {
        $order = new Order();

        $order->status = 'new';
        
        $order->sourceSite = $sourceSite ?: Craft::$app->sites->getPrimarySite()->id;
        
        return $order;
    }
    
    /**
     * @param \acclaro\translations\elements\Order $order
     * @throws \Exception
     * @return bool
     */
    public function saveOrder($order = null)
    {
        $isNew = !$order->id;

        if (!$isNew) {
            $record = OrderRecord::findOne($order->id);

            if (!$record) {
                throw new Exception('No order exists with that ID.');
            }
        } else {
            $record = new OrderRecord();
        }

        $record->setAttributes($order->getAttributes(), false);

        if (!$record->validate()) {
            $order->addErrors($record->getErrors());

            return false;
        }

        if ($order->hasErrors()) {
            return false;
        }

        $transaction = Craft::$app->db->getTransaction() === null ? Craft::$app->db->beginTransaction() : null;

        try {
            if ($record->save(false)) {
                if ($transaction !== null) {
                    $transaction->commit();
                }

                return true;
            }
        } catch (Exception $e) {
            if ($transaction !== null) {
                $transaction->rollback();
            }

            throw $e;
        }

        return false;
    }

    public function deleteOrder($orderId)
    {
        return Craft::$app->elements->deleteElementById($orderId);
    }

    /**
     * @return int
     */
    public function getAcclaroOrdersCount()
    {
        $orderCount = 0;
        $translators = Translations::$plugin->translatorRepository->getAcclaroApiTranslators();
        if ($translators) {
            $orderCount = Order::find()
                ->andWhere(Db::parseParam('translations_orders.translatorId', $translators))
                ->andWhere(Db::parseParam('translations_orders.status', array(
                    'getting quote', 'needs approval', 'in preparation', 'in progress'
                )))
                ->count();
        }

        return $orderCount;
    }

    /**
     * @param $order
     * @param $queue
     * @throws Exception
     */
    public function syncOrder($order, $queue=null) {

        $totalElements = count($order->files);
        $currentElement = 0;

        $translationService = Translations::$plugin->translatorFactory->makeTranslationService($order->translator->service, $order->translator->getSettings());

        // Don't update manual orders
        if ($order->translator->service === 'export_import') {
            return;
        }

        $translationService->updateOrder($order);

        Translations::$plugin->orderRepository->saveOrder($order);

        $syncOrderSvc = new SyncOrder();
        foreach ($order->files as $file) {
            if ($queue) {
                $syncOrderSvc->updateProgress($queue, $currentElement++ / $totalElements);
            }
            // Let's make sure we're not updating published files
            if ($file->status == 'published' || $file->status == 'canceled') {
                continue;
            }

            $translationService->updateFile($order, $file);

            Translations::$plugin->fileRepository->saveFile($file);
        }
    }

    /**
     * @param $order
     * @param $settings
     * @param null $queue
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function sendAcclaroOrder($order, $settings, $queue=null) {

        $acclaroApiClient = new AcclaroApiClient(
            $settings['apiToken'],
            !empty($settings['sandboxMode'])
        );

        $totalElements = count($order->files);
        $currentElement = 0;
        $orderUrl = UrlHelper::siteUrl() .'admin/translations/orders/detail/'.$order->id;
        $orderUrl = "Craft Order: <a href='$orderUrl'>$orderUrl</a>";
        $comments = $order->comments ? $order->comments .' | '.$orderUrl : $orderUrl;

        $orderResponse = $acclaroApiClient->createOrder(
            $order->title,
            $comments,
            $order->requestedDueDate,
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

        $sendOrderSvc = new SendOrder();
        foreach ($order->files as $file) {
            if ($queue) {
                $sendOrderSvc->updateProgress($queue, $currentElement++ / $totalElements);
            }

            $element = Craft::$app->elements->getElementById($file->elementId, null, $file->sourceSite);

            $sourceSite = Translations::$plugin->siteRepository->normalizeLanguage(Craft::$app->getSites()->getSiteById($file->sourceSite)->language);
            $targetSite = Translations::$plugin->siteRepository->normalizeLanguage(Craft::$app->getSites()->getSiteById($file->targetSite)->language);

            if ($element instanceof GlobalSetModel) {
                $filename = ElementHelper::createSlug($element->name).'-'.$targetSite.'.xml';
            } else {
                $filename = $element->slug.'-'.$targetSite.'.xml';
            }

            $path = $tempPath .'/'. $file->elementId .'-'. $filename;

            $stream = fopen($path, 'w+');

            fwrite($stream, $file->source);

            $fileResponse = $acclaroApiClient->sendSourceFile(
                $order->serviceOrderId,
                $sourceSite,
                $targetSite,
                $file->id,
                $path
            );

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
    
    /**
     * saveOrderName
     *
     * @param  mixed $orderId
     * @param  mixed $name
     * @return void
     */
    public function saveOrderName($orderId, $name) {
        
        $order = $this->getOrderById($orderId);
        $order->title = $name;
        Craft::$app->getElements()->saveElement($order);

        return true;
    }
}