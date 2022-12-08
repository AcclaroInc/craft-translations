<?php

/**
 * Translations for Craft plugin for Craft CMS 4.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\translator;

use acclaro\translations\Constants;
use acclaro\translations\elements\Order;
use acclaro\translations\models\FileModel;
use acclaro\translations\services\api\GoogleApiClient;

/**
 * @since 3.2.0
 */
class MachineTranslationService implements TranslationServiceInterface
{
    private $apiClient;

    private $subServices = [
        Constants::TRANSLATOR_MACHINE_GOOGLE => GoogleApiClient::class
    ];

    public function __construct(array $settings)
    {
        if (! array_key_exists($settings['provider'], $this->subServices)) {
            throw new \Exception("Machine translation for " . ucfirst($settings['provider']) . " is not supported.");
        }

        $class = $this->subServices[$settings['provider']];
        $this->apiClient = new $class($settings);
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function updateOrder(Order $order)
    {
        return;
    }

    /**
     * {@inheritdoc}
     */
    public function updateFile(Order $order, FileModel $file)
    {
        return;
    }

    /**
     * {@inheritdoc}
     */
    public function sendOrder(Order $order)
    {
        return;
    }
}