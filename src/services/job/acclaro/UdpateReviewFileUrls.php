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
use Exception;

use craft\queue\BaseJob;
use craft\elements\Entry;
use acclaro\translations\Translations;
use acclaro\translations\services\api\AcclaroApiClient;

class UdpateReviewFileUrls extends BaseJob
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

        foreach ($order->files as $file) {
            $this->setProgress($queue, $currentElement++ / $totalElements);

            try {
                $acclaroApiClient->addReviewUrl(
                    $order->serviceOrderId,
                    $file->serviceFileId,
                    $file->previewUrl
                );
            } catch (\Throwable $e) {
                throw $e;
            }
        }
    }

    protected function defaultDescription()
    {
        return 'Updating Acclaro review urls';
    }
}