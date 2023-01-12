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

use acclaro\translations\Constants;
use craft\queue\BaseJob;
use acclaro\translations\Translations;

class UpdateReviewFileUrls extends BaseJob
{
    public $orderId;
    public $settings;

    public function execute($queue): void
    {
        $order = Translations::$plugin->orderRepository->getOrderById($this->orderId);
        $translationService = $order->getTranslationService();

        $totalElements = count($order->files);
        $currentElement = 0;

        foreach ($order->files as $file) {
            $this->setProgress($queue, $currentElement++ / $totalElements);
			if (! $file->isComplete()) continue;

            try {
                $translationService->addReviewUrl(
                    $order->serviceOrderId,
                    $file->serviceFileId,
                    $file->previewUrl
                );
            } catch (\Throwable $e) {
                throw $e;
            }
        }
    }

    protected function defaultDescription(): ?string
    {
        return Constants::JOB_ACCLARO_UPDATING_REVIEW_URL;
    }
}
