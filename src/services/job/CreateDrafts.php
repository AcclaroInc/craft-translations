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

use craft\base\Element;
use craft\elements\User;
use craft\queue\BaseJob;
use acclaro\translations\Translations;

class CreateDrafts extends BaseJob
{

    public $mySetting;
    public $orderId;
    public $wordCounts;
    public $defaultCreator;

    public function execute($queue)
    {
        $order = Translations::$plugin->orderRepository->getOrderById($this->orderId);

        $elements = ($order->getElements() instanceof Element) ? $order->getElements()->all() : (array) $order->getElements();
        $totalElements = (count($elements) * count($order->getTargetSitesArray()));
        $currentElement = 0;
        $this->defaultCreator = User::find()
            ->admin()
            ->orderBy(['elements.id' => SORT_ASC])
            ->one();

        foreach ($order->getTargetSitesArray() as $key => $site) {
            foreach ($elements as $element) {

                $this->setProgress($queue, $currentElement++ / $totalElements);

                Translations::$plugin->draftRepository->createDrafts($element, $order, $site, $this->wordCounts);
            }
        }

        // Only send order to translation service when not Manual
        if ($order->translator->service !== 'export_import') {
            $translator = $order->getTranslator();

            $translationService = Translations::$plugin->translatorFactory->makeTranslationService($translator->service, $translator->getSettings());

            $translationService->sendOrder($order);
        } else {
            $order->status = 'in progress';
            $order->dateOrdered = new DateTime();
            //echo ' status '.$order->status; die;

            $success = Craft::$app->getElements()->saveElement($order);
            if (!$success) {
                Craft::info('Couldn’t save the order :: '.$this->orderId);
                Craft::error('Couldn’t save the order', __METHOD__);
            }
        }
    }

    protected function defaultDescription()
    {
        return 'Creating translation drafts';
    }

}