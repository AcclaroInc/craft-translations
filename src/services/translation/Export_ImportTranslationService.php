<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\translation;

use Craft;
use DateTime;
use Exception;
use craft\elements\GlobalSet;
use acclaro\translations\services\App;
use acclaro\translations\elements\Order;
use acclaro\translations\models\FileModel;
use acclaro\translations\Translations;
use acclaro\translations\services\api\AcclaroApiClient;
use acclaro\translations\services\job\UpdateDraftFromXml;
use acclaro\translations\services\job\Factory as JobFactory;

class Export_ImportTranslationService implements TranslationServiceInterface
{
    /**
     * @var \acclaro\translations\services\api\AcclaroApiClient
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
                sprintf(Translations::$plugin->translator->translate('app', 'Order status changed to %s'), 'complete')
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
                $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($file->draftId, $file->targetSite);
            } else {
                $draft = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);
            }

            $this->updateDraftFromXml($element, $draft, $target, $file->sourceSite, $file->targetSite);

        }
    }

    public function updateDraftFromXml($element, $draft, $xml, $sourceSite, $targetSite)
    {

        Craft::info('UpdateIOFile -> UpdateDraftFromXml Execute Start!!');

        $targetData = Translations::$plugin->elementTranslator->getTargetDataFromXml($xml);

        if ($draft instanceof EntryDraft) {
            if (isset($targetData['title'])) {
                $draft->title = $targetData['title'];
            }

            if (isset($targetData['slug'])) {
                $draft->slug = $targetData['slug'];
            }
        }

        $post = Translations::$plugin->elementTranslator->toPostArrayFromTranslationTarget($element, $sourceSite, $targetSite, $targetData);

        $draft->setFieldValues($post);

        $draft->siteId = $targetSite;

        // save the draft
        if ($draft instanceof EntryDraft) {
            Translations::$plugin->draftRepository->saveDraft($draft);
        } elseif ($draft instanceof GlobalSetDraftModel) {
            Translations::$plugin->globalSetDraftRepository->saveDraft($draft);
        }

        Craft::info('UpdateIOFile -> UpdateDraftFromXml Execute Ends');

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