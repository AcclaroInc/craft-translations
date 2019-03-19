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
use DateTime;
use acclaro\translations\services\App;
use acclaro\translations\elements\Order;
use acclaro\translations\Translations;

class SendOrderToTranslationService implements JobInterface
{
    /**
     * @var \acclaro\translations\elements\Order;
     */
    protected $order;

    /**
     * @param \acclaro\translations\elements\Order                 $order
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

        $translationService = Translations::$plugin->translationFactory->makeTranslationService($translator->service, $translator->getSettings());

        $translationService->sendOrder($this->order);

        $this->order->dateOrdered = new DateTime();

        Translations::$plugin->orderRepository->saveOrder($this->order);

        foreach ($this->order->files as $file) {
            Translations::$plugin->fileRepository->saveFile($file);
        }
    }
}