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

class UpdateEntries extends BaseJob
{
    public $orderId;
    public $elementIds;

    public function execute($queue)
    {
        $order = Translations::$plugin->orderRepository->getOrderById($this->orderId);
        $files = $order->getFiles();

        $transaction = Craft::$app->db->getTransaction() === null ? Craft::$app->db->beginTransaction() : null;

        $filesCount = count($files);

        $totalElements = $filesCount;
        $currentElement = 0;
        $publishedFilesCount = 0;

        foreach ($files as $file) {
            $this->setProgress($queue, $currentElement++ / $totalElements);

            if (!in_array($file->elementId, $this->elementIds)) {
                continue;
            }

            $publishedFilesCount++;

            if ($file->status === 'published') {
                continue;
            }

            $element = Craft::$app->getElements()->getElementById($file->elementId, null, $file->sourceSite);

            if ($element instanceof GlobalSetModel) {
                $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($file->draftId);

                // keep original global set name
                $draft->name = $element->name;

                $success = Translations::$plugin->globalSetDraftRepository->publishDraft($draft);

                $uri = Translations::$plugin->urlGenerator->generateFileUrl($element, $file);
            } else {
                $draft = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);

                $success = Translations::$plugin->draftRepository->publishDraft($draft);

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
                if ($transaction !== null) {
                    $transaction->rollback();
                }

                // Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Couldn’t publish draft.'));
                $this->order->logActivity(Translations::$plugin->translator->translate('app', 'Couldn’t publish '. $draft->name));

                // $this->redirect($uri, 302, true);

                return;
            }

            $file->draftId = 0;
            $file->status = 'published';

            Translations::$plugin->fileRepository->saveFile($file);
        }

        if ($publishedFilesCount === $filesCount) {
            $order->status = 'published';

            $order->logActivity(Translations::$plugin->translator->translate('app', 'Entries published'));

            Translations::$plugin->orderRepository->saveOrder($order);
        }

        if ($transaction !== null) {
            $transaction->commit();
        }
    }

    protected function defaultDescription()
    {
        return 'Updating translation entries';
    }
}