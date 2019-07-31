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
use Exception;
use ReflectionClass;
use acclaro\translations\services\App;
use acclaro\translations\Translations;

class Factory
{
    public function makeJob($class)
    {
        $args = array_slice(func_get_args(), 1);

        switch ($class) {
            case SyncOrders::class:
                // $args[] = Craft::$app;
                // $args[] = Translations::$plugin->orderRepository;
                // $args[] = $this;
                break;
            case SyncOrder::class:
                $args[] = Craft::$app;
                $args[] = Translations::$plugin->orderRepository;
                $args[] = Translations::$plugin->fileRepository;
                $args[] = Translations::$plugin->translationFactory;
                $args[] = $this;
                break;
            case SendOrderToTranslationService::class:
                $args[] = Craft::$app;
                $args[] = Translations::$plugin->orderRepository;
                $args[] = Translations::$plugin->fileRepository;
                $args[] = Translations::$plugin->translationFactory;
                break;
            case CreateOrderTranslationDrafts::class:
                $args[] = Craft::$app;
                $args[] = Translations::$plugin->draftRepository;
                $args[] = Translations::$plugin->entryRepository;
                $args[] = Translations::$plugin->globalSetDraftRepository;
                $args[] = Translations::$plugin->elementTranslator;
                break;
            case ExportOrderFiles::class:
                $args[] = Craft::$app;
                $args[] = Translations::$plugin->orderRepository;
                $args[] = Translations::$plugin->fileRepository;
                $args[] = Translations::$plugin->translationFactory;
                $args[] = $this;
                break;
            // case ImportOrderFiles::class:
            //     $args[] = Craft::$app;
            //     $args[] = Translations::$plugin->orderRepository;
            //     $args[] = Translations::$plugin->fileRepository;
            //     $args[] = Translations::$plugin->translationFactory;
            //     $args[] = $this;
            //     break;
        }

        $reflectionClass = new ReflectionClass($class);

        return $reflectionClass->newInstanceArgs($args);
    }

    public function dispatchJob($class)
    {
        $job = call_user_func_array(array($this, 'makeJob'), func_get_args());

        return $job->handle();
    }
}