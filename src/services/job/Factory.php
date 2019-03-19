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
use Exception;
use ReflectionClass;
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\TranslationsForCraft;

class Factory
{
    public function makeJob($class)
    {
        $args = array_slice(func_get_args(), 1);

        switch ($class) {
            case UpdateDraftFromXml::class:
                $args[] = Craft::$app;
                $args[] = TranslationsForCraft::$plugin->draftRepository;
                $args[] = TranslationsForCraft::$plugin->globalSetDraftRepository;
                $args[] = TranslationsForCraft::$plugin->elementTranslator;
                $args[] = $this;
               
                break;
            case SyncOrders::class:
                // $args[] = Craft::$app;
                // $args[] = TranslationsForCraft::$plugin->orderRepository;
                // $args[] = $this;
                break;
            case SyncOrder::class:
                $args[] = Craft::$app;
                $args[] = TranslationsForCraft::$plugin->orderRepository;
                $args[] = TranslationsForCraft::$plugin->fileRepository;
                $args[] = TranslationsForCraft::$plugin->translationFactory;
                $args[] = $this;
                break;
            case SendOrderToTranslationService::class:
                $args[] = Craft::$app;
                $args[] = TranslationsForCraft::$plugin->orderRepository;
                $args[] = TranslationsForCraft::$plugin->fileRepository;
                $args[] = TranslationsForCraft::$plugin->translationFactory;
                break;
            case CreateOrderTranslationDrafts::class:
                $args[] = Craft::$app;
                $args[] = TranslationsForCraft::$plugin->draftRepository;
                $args[] = TranslationsForCraft::$plugin->entryRepository;
                $args[] = TranslationsForCraft::$plugin->globalSetDraftRepository;
                $args[] = TranslationsForCraft::$plugin->elementTranslator;
                break;
            case ExportOrderFiles::class:
                $args[] = Craft::$app;
                $args[] = TranslationsForCraft::$plugin->orderRepository;
                $args[] = TranslationsForCraft::$plugin->fileRepository;
                $args[] = TranslationsForCraft::$plugin->translationFactory;
                $args[] = $this;
                break;
            // case ImportOrderFiles::class:
            //     $args[] = Craft::$app;
            //     $args[] = TranslationsForCraft::$plugin->orderRepository;
            //     $args[] = TranslationsForCraft::$plugin->fileRepository;
            //     $args[] = TranslationsForCraft::$plugin->translationFactory;
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