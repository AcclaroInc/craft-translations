<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\job;

use Craft;
use acclaro\translations\services\App;
use acclaro\translations\elements\Order;
use acclaro\translations\Translations;

class SyncOrder implements JobInterface
{
    /**
     * @var \Craft\Order
     */
    protected $order;

    /**
     * @param \Craft\Order  $order
     */
    public function __construct(
        Order $order
    ) {
        $this->order = $order;
    }

    /**
     * {@inheritdoc}
     */
    public function handle()
    {
        $translationService = Translations::$plugin->translationFactory->makeTranslationService($this->order->translator->service, $this->order->translator->getSettings());
        
        // Don't update manual orders
        if ($this->order->translator->service === 'export_import') {
            return;
        }

        $translationService->updateOrder(Translations::$plugin->jobFactory, $this->order);

        Translations::$plugin->orderRepository->saveOrder($this->order);

        foreach ($this->order->files as $file) {

            // Let's make sure we're not updating published files
            if ($file->status == 'published') {
                continue;
            }

            $translationService->updateFile(Translations::$plugin->jobFactory, $this->order, $file);

            Translations::$plugin->fileRepository->saveFile($file);
        }
    }
}