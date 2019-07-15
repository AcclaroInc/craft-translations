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
use craft\queue\BaseJob;
use yii\web\HttpException;
use craft\elements\GlobalSet;
use acclaro\translations\Translations;
use acclaro\translations\services\job\SendOrderToTranslationService;
use acclaro\translations\services\job\CreateOrderTranslationDrafts;

class OrderJob extends BaseJob
{

    public $mySetting;
    public $orderId;
    public $wordCounts;

    public function execute($queue)
    {

        Craft::info('OrderJob Execute Start!!');
        Craft::info(json_encode($queue));
        Craft::info(' ORDER ID:  '.$this->orderId);

        $order = Translations::$plugin->orderRepository->getOrderById($this->orderId);

        if (!$order) {
            Craft::info('Invalid Order');
            throw new HttpException(400, Translations::$plugin->translator->translate('app', 'Invalid Order'));
        }

        // Check supported languages
        if ($order->getTranslator()->service !== 'export_import') {
            Craft::info('Acclaro API Order');
            $translationService = Translations::$plugin->translationFactory->makeTranslationService($order->getTranslator()->service, $order->getTranslator()->getSettings());

            if ($translationService->getLanguages()) {
                $sourceLanguage = Translations::$plugin->siteRepository->normalizeLanguage(Craft::$app->getSites()->getSiteById($order->sourceSite)->language);
                $unsupported = false;
                $unsupportedLangs = [];
                $supportedLanguagePairs = [];

                foreach ($translationService->getLanguagePairs($sourceLanguage) as $key => $languagePair) {
                    $supportedLanguagePairs[] = $languagePair->target['code'];
                }

                foreach (json_decode($order->targetSites) as $key => $siteId) {
                    $site = Craft::$app->getSites()->getSiteById($siteId);
                    $language = Translations::$plugin->siteRepository->normalizeLanguage($site->language);

                    if (!in_array($language, array_column($translationService->getLanguages(), 'code'))) {
                        $unsupported = true;
                        $unsupportedLangs[] = array(
                            'language' => $site->name .' ('.$site->language.')'
                        );
                    }

                    if (!in_array($language, $supportedLanguagePairs)) {
                        $unsupported = true;
                        $unsupportedLangs[] = array(
                            'language' => $site->name .' ('.$site->language.')'
                        );
                    }
                }

                if ($unsupported || !empty($unsupportedLangs)) {
                    $order->status = 'failed';
                    $success = Craft::$app->getElements()->saveElement($order);
                    if (!$success) {
                        Craft::error('Couldn’t save the order', __METHOD__);
                    }
                    Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'The following language pair(s) are not supported: '.implode(', ', array_column($unsupportedLangs, 'language')).' Contact Acclaro for assistance.'));
                    // return; // @todo This might be a better idea than failing the order
                    return $this->redirect('translations/orders', 302, true);
                }

            } else {
                // var_dump('Could not fetch languages');
            }
        }

        $order->logActivity(sprintf(Translations::$plugin->translator->translate('app', 'Order Submitted to %s'), $order->translator->getName()));

        Craft::$app->queue->push(new OrderTranslationDrafts([
            'description' => 'Creating Order Translation Drafts',
            'orderId' => $order->getId(),
            'wordCounts' => $this->wordCounts,
        ]));

        // Only send order to translation service when not Manual
        if ($order->translator->service !== 'export_import') {
            Translations::$plugin->jobFactory->dispatchJob(SendOrderToTranslationService::class, $order);
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

        Craft::info('OrderJob Execute Ends Id :: '.$this->orderId);

    }

    protected function defaultDescription()
    {
        return 'Creating Translation Order';
    }
}