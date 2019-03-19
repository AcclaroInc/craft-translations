<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\services\job;

use Craft;
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\elements\Order;
use acclaro\translationsforcraft\TranslationsForCraft;

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
        $translationService = TranslationsForCraft::$plugin->translationFactory->makeTranslationService($this->order->translator->service, $this->order->translator->getSettings());
        
        // Don't update manual orders
        if ($this->order->translator->service === 'export_import') {
            return;
        }

        $translationService->updateOrder(TranslationsForCraft::$plugin->jobFactory, $this->order);

        TranslationsForCraft::$plugin->orderRepository->saveOrder($this->order);

        foreach ($this->order->files as $file) {

            // Let's make sure we're not updating published files
            if ($file->status == 'published') {
                continue;
            }

            $translationService->updateFile(TranslationsForCraft::$plugin->jobFactory, $this->order, $file);

            TranslationsForCraft::$plugin->fileRepository->saveFile($file);
        }
    }
}