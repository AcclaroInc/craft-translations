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

use craft\queue\BaseJob;
use craft\elements\Entry;
use acclaro\translations\Translations;

class ApplyDrafts extends BaseJob
{
    public $orderId;
    public $elementIds;

    public function execute($queue)
    {
        $order = Translations::$plugin->orderRepository->getOrderById($this->orderId);
        $files = $order->getFiles();

        $filesCount = count($files);

        $totalElements = (count($this->elementIds) * count($order->getTargetSitesArray()));
        $currentElement = 0;
        $publishedFilesCount = 0;

        foreach ($files as $file) {
            if (!in_array($file->elementId, $this->elementIds)) {
                continue;
            }

            $publishedFilesCount++;

            if ($file->status !== 'complete') {
                continue;
            }

            $this->setProgress($queue, $currentElement++ / $totalElements);
            Craft::info('23fo2in2FJ: '. $currentElement .' | '. $totalElements);

            $element = Craft::$app->getElements()->getElementById($file->elementId, null, $file->sourceSite);

            if ($element instanceof GlobalSetModel) {
                $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($file->draftId);

                // keep original global set name
                $draft->name = $element->name;

                if ($draft) {
                    $success = Translations::$plugin->globalSetDraftRepository->publishDraft($draft);
                } else {
                    $success = false;
                }

                $uri = Translations::$plugin->urlGenerator->generateFileUrl($element, $file);
            } else {
                $draft = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);

                if ($draft) {
                    $success = Translations::$plugin->draftRepository->applyTranslationDraft($file->id);
                } else {
                    $success = false;
                }

                $uri = Translations::$plugin->urlGenerator->generateFileUrl($element, $file);
            }

            if ($success) {
                $oldTokenRoute = json_encode(array(
                    'action' => 'entries/view-shared-entry',
                    'params' => array(
                        'draftId' => $file->draftId,
                    ),
                ));

                $newTokenRoute = json_encode(array(
                    'action' => 'entries/view-shared-entry',
                    'params' => array(
                        'entryId' => $draft->id,
                        'locale' => $file->targetSite,
                    ),
                ));

                Craft::$app->db->createCommand()->update(
                    'tokens',
                    array('route' => $newTokenRoute),
                    'route = :oldTokenRoute',
                    array(':oldTokenRoute' => $oldTokenRoute)
                );
            } else {
                $order->logActivity(Translations::$plugin->translator->translate('app', 'Couldn’t apply draft for '. '"'. $element->title .'"'));
                Translations::$plugin->orderRepository->saveOrder($order);

                continue;
            }

            $file->draftId = 0;
            $file->status = 'published';

            Translations::$plugin->fileRepository->saveFile($file);
        }

        Craft::info('aas3i23jf: '. $publishedFilesCount .' | '. $filesCount);
        if ($publishedFilesCount === $filesCount) {
            $order->status = 'published';

            $order->logActivity(Translations::$plugin->translator->translate('app', 'Drafts applied'));

            Translations::$plugin->orderRepository->saveOrder($order);
        }
    }

    protected function defaultDescription()
    {
        return 'Applying translation drafts';
    }
}