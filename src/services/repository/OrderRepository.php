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
use Exception;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Db;
use craft\elements\Tag;
use craft\records\Element;
use craft\elements\Asset;
use craft\elements\Category;
use craft\helpers\UrlHelper;
use craft\elements\GlobalSet;
use craft\commerce\elements\Product;
use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\elements\Order;
use acclaro\translations\records\OrderRecord;
use acclaro\translations\services\job\acclaro\SendOrder;

class OrderRepository
{
    /**
     * @param  int|string $orderId
     * @return \acclaro\translations\elements\Order|null
     */
    public function getOrderById($orderId)
    {
        return Translations::$plugin->elementRepository->getElementById($orderId);
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
        $results = Order::find()->andWhere(Db::parseParam('translations_orders.status', Constants::ORDER_STATUS_PENDING))->all();
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

    public function isTranslationOrder($elementId)
    {
        return Order::findOne(['id' => $elementId]);
    }

    /**
     * @return \craft\elements\db\ElementQuery
     */
    public function getOpenOrders()
    {
        $openOrders = Order::find()
            ->andWhere(Db::parseParam('translations_orders.status', array(
                Constants::ORDER_STATUS_IN_PROGRESS,
                Constants::ORDER_STATUS_IN_REVIEW,
                Constants::ORDER_STATUS_IN_PREPARATION,
                Constants::ORDER_STATUS_GETTING_QUOTE,
                Constants::ORDER_STATUS_NEEDS_APPROVAL,
                Constants::ORDER_STATUS_COMPLETE,
                Constants::ORDER_STATUS_NEW,
            )))
            ->all();

        return $openOrders;
    }

    /**
     * @return \acclaro\translations\elements\Order[]
     */
    public function getInProgressOrders(): array
    {
        $inProgressOrders = Order::find()
            ->andWhere(Db::parseParam('translations_orders.status', array(
                Constants::ORDER_STATUS_GETTING_QUOTE,
                Constants::ORDER_STATUS_NEEDS_APPROVAL,
                Constants::ORDER_STATUS_IN_PREPARATION,
                Constants::ORDER_STATUS_IN_PROGRESS,
                Constants::ORDER_STATUS_NEW,
            )))
            ->all();

        return $inProgressOrders;
    }

    /**
     * @return array
     */
    public function getInProgressOrdersByTranslatorId($translatorId)
    {
        $pendingOrders = Order::find()
            ->andWhere(Db::parseParam('translations_orders.translatorId', $translatorId))
            ->all();

        return $pendingOrders;
    }

    /**
     * @return array
     */
    public function getCompleteOrders()
    {
        $results = Order::find()->andWhere(Db::parseParam('translations_orders.status', Constants::ORDER_STATUS_COMPLETE))->all();
        return $results;
    }

    public function getOrderStatuses()
    {
        return array(
            'new' => 'new',
            'pending' => 'pending',
            'getting quote' => 'getting quote',
            'needs approval' => 'needs approval',
            'in preparation' => 'in preparation',
            'in progress' => 'in progress',
            'complete' => 'complete',
            'canceled' => 'canceled',
            'published' => 'published',
        );
    }

    /**
     * @return \acclaro\translations\elements\Order
     */
    public function makeNewOrder($sourceSite = null)
    {
        $order = new Order();

        $order->status = Constants::ORDER_STATUS_PENDING;

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
                    Constants::ORDER_STATUS_GETTING_QUOTE,
                    Constants::ORDER_STATUS_NEEDS_APPROVAL,
                    Constants::ORDER_STATUS_IN_PREPARATION,
                    Constants::ORDER_STATUS_IN_PROGRESS,
                    Constants::ORDER_STATUS_NEW,
                )))
                ->count();
        }

        return $orderCount;
    }

    /**
     * @param \acclaro\translations\elements\Order $order
     * @param array $settings
     * @param array $tagIds
     */
    public function deleteOrderTags($order, $settings, $tagIds) {
        $translationService = $order->getTranslationService();
        foreach ($tagIds as $tagId) {
            $tag = Craft::$app->getTags()->getTagById($tagId);
            if ($tag) {
                $translationService->removeOrderTags($order->id, $tag->title);
            }
        }
    }

    /**
     * @param \acclaro\translations\elements\Order $order
     * @param $settings
     * @param null $queue
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function sendAcclaroOrder($order, $settings, $queue=null) {

        $translationService = $order->getTranslationService();

        $totalElements = count($order->files);
        $currentElement = 0;
        $orderUrl = UrlHelper::baseSiteUrl() .'admin/translations/orders/detail/'.$order->id;
        $orderUrl = "Craft Order: <a href='$orderUrl'>$orderUrl</a>";
        $comments = $order->comments ? $order->comments .' | '.$orderUrl : $orderUrl;
        $dueDate = $order->requestedDueDate;

        if($dueDate) {
            if (is_string($dueDate)) {
                $dueDate = new \DateTime($dueDate);
            }
            $dueDate = $dueDate->format('Y-m-d');
        }

        $orderResponse = $translationService->createOrder(
            $order->title,
            $comments,
            $dueDate,
            $order->wordCount
        );

        $orderData = [
            'acclaroOrderId'    => (!is_null($orderResponse)) ? $orderResponse->orderid : '',
            'orderId'      => $order->id
        ];

        $order->serviceOrderId = $orderData['acclaroOrderId'];

        $translationService->requestOrderCallback(
            $order->serviceOrderId,
            Translations::$plugin->urlGenerator->generateOrderCallbackUrl($order)
        );

        if ($order->tags) {
            $tags = [];
            foreach (json_decode($order->tags, true) as $tagId) {
                $tag = Craft::$app->getTags()->getTagById($tagId);
                if ($tag) {
                    array_push($tags, $tag->title);
                }
            }
            if (! empty($tags)) {
                $translationService->addOrderTags($orderResponse->orderid, implode(",", $tags));
            }
        }

        $orderReferenceFiles = [];

        $sendOrderSvc = new SendOrder();
        $translationService = $order->getTranslationService();
        foreach ($order->getFiles() as $file) {
            if ($queue) {
                $sendOrderSvc->updateProgress($queue, $currentElement++ / $totalElements);
            }

            $translationService->sendOrderFile($order, $file, $settings);

            if ($order->shouldIncludeTmFiles() && $file->hasTmMisalignments()) {
                array_push($orderReferenceFiles, $file);
            }
        }

        if ($order->requestQuote()) {
            $translationService->requestOrderQuote($order->serviceOrderId);
        } else {
            $translationService->submitOrder($order->serviceOrderId);
        }

        foreach ($orderReferenceFiles as $file) {
            $translationService->sendOrderReferenceFile($order, $file);
        }

        $order->status = $order->requestQuote() ? Constants::ORDER_STATUS_GETTING_QUOTE : Constants::ORDER_STATUS_NEW;

        $order->dateOrdered = new \DateTime();

        Craft::$app->getElements()->saveElement($order);
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

    public function getAllOrderTags() {
        $allOrderTags = [];

        $orderTags = (new Query())
            ->select(['id'])
            ->from([Table::ELEMENTS])
            ->where(['type' => Tag::class, 'fieldLayoutId' => null])
            ->column();

        foreach ($orderTags as $tagId) {
            $allOrderTags[] = Craft::$app->getTags()->getTagById($tagId);
        }

        return $allOrderTags;
    }

    public function orderTagExists($title) {
        $allOrderTags = $this->getAllOrderTags();

        foreach ($allOrderTags as $tag) {
            if (strtolower($tag->title) == strtolower($title)) {
                return $tag;
            }
        }
        return false;
    }

    /**
     * @param $elements
     * @return array
     */
    public function checkOrderDuplicates($elements)
    {
        $orderIds = [];
        foreach ($elements as $element) {
			$canonicalElement = $element->getIsDraft() ? $element->getCanonical() : $element;
            $elementIds = $this->getDraftIds($canonicalElement);
            $orders = $this->getOrdersByElement($elementIds);
            if ($orders) {
                $orderIds[$element->id] = $orders;
            }
        }

        return $orderIds;
    }

    /**
     * Get order status based on files statuses
     *
     * @param Order $order
     * @return string
     */
    public function getNewStatus($order)
    {
        $fileStatuses = [];
        $publishedFiles = 0;
		$files = Translations::$plugin->fileRepository->getFiles($order->id);

        foreach ($files as $file) {
            if ($file->status === Constants::FILE_STATUS_PUBLISHED) $publishedFiles++;

            if (! in_array($file->status, $fileStatuses)) {
                array_push($fileStatuses, $file->getStatusLabel());
            }
        }

        if ($publishedFiles == count(($files))) {
            return Constants::ORDER_STATUS_PUBLISHED;
        } else if (in_array('Modified', $fileStatuses)) {
            return Constants::ORDER_STATUS_MODIFIED;
        } else if (in_array('Ready to apply', $fileStatuses)) {
            return Constants::ORDER_STATUS_COMPLETE;
        } else if (in_array('Ready for review', $fileStatuses)) {
            return Constants::ORDER_STATUS_REVIEW_READY;
        } else if (in_array('In progress', $fileStatuses)) {
            return Constants::ORDER_STATUS_IN_PROGRESS;
        } else if (in_array('Failed', $fileStatuses)) {
            return Constants::ORDER_STATUS_FAILED;
        } else if (in_array('Canceled', $fileStatuses)) {
            return Constants::ORDER_STATUS_CANCELED;
        } else {
            // Default Status in case of any issue
            return Constants::ORDER_STATUS_NEW;
        }
    }

    /**
     * @param $file
     * @return string|null
     */
    public function getFileTitle($file) {

        $element = Translations::$plugin->elementRepository->getElementById($file->elementId, $file->sourceSite);

        if ($element instanceof GlobalSet) {
            $draftElement = Translations::$plugin->globalSetDraftRepository->getDraftById($file->draftId);
        } else if ($element instanceof Category) {
            $draftElement = Translations::$plugin->categoryRepository->getDraftById($file->draftId, $file->targetSite);
        } else if ($element instanceof Asset) {
            $draftElement = Translations::$plugin->assetDraftRepository->getDraftById($file->draftId);
        } else if ($element instanceof Product) {
            $draftElement = Translations::$plugin->commerceRepository->getDraftById($file->draftId);
        } else {
            $draftElement = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);
        }

        return $draftElement->title ?? $draftElement->name ?? $element->title;
    }

    /**
     * Checks if source entry of elements in order has changed
     *
     * @param Order $order
     * @return array $result
     */
    public function getIsSourceChanged($order): ?array
    {
        $originalIds = [];

        if ($files = $order->getFiles()) {
            foreach ($files as $file) {
                if ($file->isPublished() || ! $file->source || in_array($file->elementId, $originalIds)) continue;

                try {
                    $element = Craft::$app->getElements()->getElementById($file->elementId, null, $file->sourceSite);

                    /** Skip in case the source entry is deleted */
                    if (! $element) continue;

                    $wordCount = Translations::$plugin->elementTranslator->getWordCount($element);
                    $converter = Translations::$plugin->elementToFileConverter;

                    $currentContent = $converter->convert(
                        $element,
                        Constants::FILE_FORMAT_XML,
                        [
                            'sourceSite'    => $order->sourceSite,
                            'targetSite'    => $file->targetSite,
                            'wordCount'     => $wordCount,
                            'orderId'       => $order->id
                        ]
                    );

                    $sourceContent = json_decode($converter->xmlToJson($file->source), true);
                    $currentContent = json_decode($converter->xmlToJson($currentContent), true);

                    $sourceContent = json_encode(array_values($sourceContent['content']));
                    $currentContent = json_encode(array_values($currentContent['content']));

                    if (md5($sourceContent) !== md5($currentContent)) {
                        array_push($originalIds, $element->id);
                    }
                } catch (Exception $e) {
                    throw new Exception("Source entry changes check, Error: " . $e->getMessage(), 1);
                }
            }
        }

		return $originalIds;
	}

	/**
	 * Checks if target data of elements in order is different than what is delivered
	 *
	 * @param Order $order
	 * @return array $result
	 */
	public function getIsTargetChanged($order): ?array
	{
		$originalIds = [];

		foreach ($order->getFiles() as $file) {
            if (! $file->canBeCheckedForTargetChanges()) continue;

			if ($file->hasTmMisalignments()) array_push($originalIds, $file->elementId);
		}

		return $originalIds;
	}

    /**
     * @param  int|string $elementId
     * @return int[]
     */
    private function getOrdersByElement($elementId)
    {
        $query = (new Query())
            ->select('files.orderId')
            ->from([Constants::TABLE_ORDERS . ' orders'])
            ->innerJoin(Constants::TABLE_FILES . ' files', '[[files.orderId]] = [[orders.id]]')
            ->where(['files.elementId' => $elementId])
            ->andWhere(['orders.status' => [
                Constants::ORDER_STATUS_NEW,
                Constants::ORDER_STATUS_GETTING_QUOTE,
                Constants::ORDER_STATUS_NEEDS_APPROVAL,
                Constants::ORDER_STATUS_IN_PREPARATION,
                Constants::ORDER_STATUS_IN_PROGRESS,
                Constants::ORDER_STATUS_REVIEW_READY,
                Constants::ORDER_STATUS_COMPLETE
            ]])
            ->andWhere(['dateDeleted' => null])
            ->groupBy('orderId')
            ->all();

        $orderIds = [];

        foreach ($query as $key => $id) {
            $orderIds[] = $id['orderId'];
        }

        return $orderIds;
    }

    /**
     * Returns all element ids of drafts of a entry including canonical
     */
    private function getDraftIds($element)
    {
        $draftIds = [];
        $drafts = Craft::$app->getDrafts()->getEditableDrafts($element);

        foreach ($drafts as $draft) {
            if (Translations::$plugin->draftRepository->isTranslationDraft($draft->draftId)) {
                continue;
            }
            array_push($draftIds, $draft->id);
        }

        array_push($draftIds, $element->id);

        return $draftIds;
    }
}
