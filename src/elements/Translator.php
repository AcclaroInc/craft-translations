<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\elements;

use Craft;
use craft\base\Element;
use acclaro\translations\Constants;
use acclaro\translations\Translations;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class Translator extends Element
{
    protected $elementType = 'Translator';

    /**
     * @inheritdoc
     */
    public static function sources(string $context): array
    {
        return static::defineSources($context);;
    }

    /**
     * Properties
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key'           => 'all',
                'label'         => Translations::$plugin->translator->translate('app', 'All Translators'),
                'criteria'      => [],
                'defaultSort'   => ['label', 'desc']
            ]
        ];

        $allServices = Translations::$plugin->translatorRepository->getTranslatorServices();

        foreach ($allServices as $service) {
            $sources[] = [
                "key"       => $service['service'],
                "label"     => $service['service'] == Constants::TRANSLATOR_DEFAULT ? "Export/Import" : ucwords($service['service']),
                "criteria"  => [
                    "service"    => $service['service']
                ],
            ];
        }

        return $sources;
    }

    protected static function defineSortOptions(): array
    {
        return [
            'label' => Craft::t('app', 'Name'),
            'status' => Craft::t('app', 'Status'),
            'service' => Craft::t('app', 'Service'),
        ];
    }

    protected static function defineSearchableAttributes(): array
    {
        return [
            'id',
            'label',
            'service',
            'status'
        ];
    }

    protected static function defineTableAttributes(): array
    {
        $attributes = [
            'label' => ['label' => Translations::$plugin->translator->translate('app', 'Name')],
            'service' => ['label' => Translations::$plugin->translator->translate('app', 'Service')],
            'status' => ['label' => Translations::$plugin->translator->translate('app', 'Status')]
        ];

        return $attributes;
    }
}