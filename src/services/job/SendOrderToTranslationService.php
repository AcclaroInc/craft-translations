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
use DateTime;
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\elements\Order;
use acclaro\translationsforcraft\TranslationsForCraft;

class SendOrderToTranslationService implements JobInterface
{
    /**
     * @var \acclaro\translationsforcraft\elements\Order;
     */
    protected $order;

    /**
     * @param \acclaro\translationsforcraft\elements\Order                 $order
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
        $translator = $this->order->getTranslator();

        $translationService = TranslationsForCraft::$plugin->translationFactory->makeTranslationService($translator->service, $translator->getSettings());

        $translationService->sendOrder($this->order);

        $this->order->dateOrdered = new DateTime();

        TranslationsForCraft::$plugin->orderRepository->saveOrder($this->order);

        foreach ($this->order->files as $file) {
            TranslationsForCraft::$plugin->fileRepository->saveFile($file);
        }
    }
}