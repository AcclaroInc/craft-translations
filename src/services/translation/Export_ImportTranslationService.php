<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\services\translation;

use Craft;
use DateTime;
use Exception;
use craft\elements\GlobalSet;
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\elements\Order;
use acclaro\translationsforcraft\models\FileModel;
use acclaro\translationsforcraft\TranslationsForCraft;
use acclaro\translationsforcraft\services\api\AcclaroApiClient;
use acclaro\translationsforcraft\services\job\UpdateDraftFromXml;
use acclaro\translationsforcraft\services\job\Factory as JobFactory;

class Export_ImportTranslationService implements TranslationServiceInterface
{
    /**
     * @var \acclaro\translationsforcraft\services\api\AcclaroApiClient
     */
    protected $apiClient;

    /**
     * @var boolean
     */
    protected $sandboxMode = false;

    /**
     * @param array $settings
     */
    public function __construct(
        array $settings
    ) {
        $this->sandboxMode = !empty($settings['sandboxMode']);
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate()
    {
        return;
    }

    /**
     * {@inheritdoc}
     */
    public function updateOrder(JobFactory $jobFactory, Order $order)
    {
        if ($order->status !== 'complete') {
            $order->logActivity(
                sprintf(TranslationsForCraft::$plugin->translator->translate('app', 'Order status changed to %s'), 'complete')
            );
        }

        $order->status = 'complete';
    }

     /**
     * {@inheritdoc}
     */

    public function updateFile(JobFactory $jobFactory, Order $order, FileModel $file){
        return;
    }

    public function updateIOFile(JobFactory $jobFactory, Order $order, FileModel $file, $target)
    { 
        if (empty($target)) 
        {
            return;
        }

        $file->status = 'complete';
       
        if ($target) 
        {
            $file->target = $target;

            $element = Craft::$app->elements->getElementById($file->elementId, null, $file->sourceSite);

            if ($element instanceof GlobalSet) {
                $draft = TranslationsForCraft::$plugin->globalSetDraftRepository->getDraftById($file->draftId, $file->targetSite);
            } else {
                $draft = TranslationsForCraft::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);
            }

            $jobFactory->dispatchJob(UpdateDraftFromXml::class, $element, $draft, $target, $file->sourceSite, $file->targetSite);

        }
    }

    /**
     * {@inheritdoc}
     */
    public function sendOrder(Order $order)
    {
        return;
    }

    public function editOrderName(Order $order, $name)
    {

        $order->title = $name;

        return true;
    }
}