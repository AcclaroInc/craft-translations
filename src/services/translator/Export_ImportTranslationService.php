<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\translator;

use Craft;
use DateTime;
use Exception;
use craft\elements\Entry;
use craft\elements\Category;
use craft\elements\GlobalSet;
use acclaro\translations\services\App;
use acclaro\translations\elements\Order;
use acclaro\translations\models\FileModel;
use acclaro\translations\Translations;
use acclaro\translations\services\api\AcclaroApiClient;

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
    public function updateOrder(Order $order)
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

    public function updateFile(Order $order, FileModel $file){
        return;
    }

    public function updateIOFile(Order $order, FileModel $file, $target, $file_name)
    { 
        if (empty($target)) 
        {
            return;
        }

        $file->status = 'complete';
        $file->dateDelivered = new \DateTime();
       
        if ($target) 
        {
            $file->target = $target;

            $element = Craft::$app->elements->getElementById($file->elementId, null, $file->sourceSite);

            if ($element instanceof GlobalSet) {
                $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($file->draftId, $file->targetSite);
            } else if ($element instanceof Category) {
                $draft = Translations::$plugin->categoryDraftRepository->getDraftById($file->draftId, $file->targetSite);

                $category = Craft::$app->getCategories()->getCategoryById($draft->categoryId, $draft->site);
                $draft->groupId = $category->groupId;

            } else {
                $draft = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);
            }

            return $this->updateDraftFromXml($element, $draft, $target, $file->sourceSite, $file->targetSite, $order, $file_name);
        }
    }

    public function updateDraftFromXml($element, $draft, $xml, $sourceSite, $targetSite, $order, $file_name)
    {
        $targetData = Translations::$plugin->elementTranslator->getTargetDataFromXml($xml);

        if ($draft instanceof Entry || $draft instanceof Category) {
            if (isset($targetData['title'])) {
                $draft->title = $targetData['title'];
            }

            if (isset($targetData['slug'])) {
                $draft->slug = $targetData['slug'];
            }
        }
        $draft->siteId = $targetSite;
        
        // echo '<pre>';
        // echo "//======================================================================<br>// return targetData updateDraftFromXml()<br>//======================================================================<br>";
        // var_dump($targetData);
        // // var_dump($draft);
        // echo '</pre>';
        // die;
        $post = Translations::$plugin->elementTranslator->toPostArrayFromTranslationTarget($element, $sourceSite, $targetSite, $targetData);
        // $post = Translations::$plugin->elementTranslator->toPostArrayFromTranslationTarget($draft, $sourceSite, $targetSite, $targetData);
        // echo '<pre>';
        // echo "//======================================================================<br>// return post updateDraftFromXml()<br>//======================================================================<br>";
        // var_dump($post);
        // echo '</pre>';
        // die;
        
        $draft->setFieldValues($post);

        // save the draft
        if ($draft instanceof Entry) {
            $res = Translations::$plugin->draftRepository->saveDraft($draft);
            if (!$res) {
                $order->logActivity(
                    sprintf(Translations::$plugin->translator->translate('app', 'Unable to save draft, please review your XML file %s'), $file_name)
                );

                return false;
            }
        } elseif ($draft instanceof GlobalSet) {
            Translations::$plugin->globalSetDraftRepository->saveDraft($draft);
        } elseif ($draft instanceof Category) {
            Translations::$plugin->categoryDraftRepository->saveDraft($draft, $post);
            
            // $behavior = $draft->getBehavior('draft');
            // $behavior->mergingChanges = true;
            // Craft::$app->getElements()->saveElement($draft, false, false);
            // $behavior->mergingChanges = false;
            // Craft::$app->getContent()->saveContent($draft);
            // echo '<pre>';
            // echo "//======================================================================<br>// return draft after saveDraft()<br>//======================================================================<br>";
            // echo '<pre>';
            // // var_dump($post);
            // var_dump($draft->getBehavior('customFields'));
            // echo '</pre>';
            
            // $content = Translations::$plugin->elementTranslator->toPostArray($draft);
            // echo '<pre>';
            // echo "//======================================================================<br>// return content after toPostArray()<br>//======================================================================<br>";
            // echo '<pre>';
            // var_dump($content);
            // echo '</pre>';
            
            // die;
        }

        return true;
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
