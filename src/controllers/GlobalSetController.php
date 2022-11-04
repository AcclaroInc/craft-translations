<?php

/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows
 * for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\controllers;

use Craft;
use acclaro\translations\Constants;
use acclaro\translations\Translations;

class GlobalSetController extends BaseController
{
    /**
     * Edit Global Set Drafts
     *
     * @param array $variables
     * @return void
     */
    public function actionEditDraft(array $variables = array())
    {
        $variables = $this->request->resolve()[1];

        if (empty($variables['globalSetHandle'])) {
            $this->setError('Param “globalSetHandle” doesn’t exist.');
            return;
        }

        $variables['globalSets'] = array();

        $globalSets = Translations::$plugin->globalSetRepository->getAllSets();

        foreach ($globalSets as $globalSet) {
            if (Craft::$app->getUser()->checkPermission('editGlobalSet:'.$globalSet->id)) {
                $variables['globalSets'][$globalSet->handle] = $globalSet;
            }
        }

        if (!isset($variables['globalSets'][$variables['globalSetHandle']])) {
            $this->setError('Invalid global set handle');
            return;
        }

        $globalSet = $variables['globalSets'][$variables['globalSetHandle']];

        $variables['globalSetId'] = $globalSet->id;

        $variables['orders'] = array();

        foreach (Translations::$plugin->orderRepository->getDraftOrders() as $order) {
            if ($order->sourceSite === $globalSet->site) {
                $variables['orders'][] = $order;
            }
        }

        $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($variables['draftId']);

        $variables['drafts'] = Translations::$plugin->globalSetDraftRepository->getDraftsByGlobalSetId($globalSet->id, $draft->site);

        $variables['draft'] = $draft;

        $variables['file'] = Translations::$plugin->fileRepository->getFileByDraftId($draft->draftId, $globalSet->id);

        $this->renderTemplate('translations/globals/_editDraft', $variables);
    }

    /**
     * Save Global Set Drafts
     *
     * @param array $variables
     * @return void
     */
    public function actionSaveDraft()
    {
        $this->requirePostRequest();

        $site = $this->request->getBodyParam('site', Craft::$app->sites->getPrimarySite()->id);

        $globalSetId = $this->request->getParam('globalSetId');

        $globalSet = Translations::$plugin->globalSetRepository->getSetById($globalSetId, $site);

        if (!$globalSet) {
            $this->setError("No global set exists with the ID '{$globalSetId}'.");
            return;
        }

        $draftId = $this->request->getParam('draftId');

        if ($draftId) {
            $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($draftId);

            if (!$draft) {
                $this->setError("No draft exists with the ID '{$draftId}'.");
                return;
            }
        } else {
            $draft = Translations::$plugin->globalSetDraftRepository->makeNewDraft();
            $draft->id = $globalSetId;
            $draft->site = $site;
        }

        $fields = $this->request->getParam('fields') ?? [];

        if ($fields) {
            $draft->setFieldValues($fields);
        }

        if (Translations::$plugin->globalSetDraftRepository->saveDraft($draft, $fields)) {
            $this->setSuccess('Draft saved.');

            $this->redirect($draft->getCpEditUrl(), 302, true);
        } else {
            $this->setError('Couldn’t save draft.');

            Craft::$app->urlManager->setRouteParams(array(
                'globalSet' => $draft
            ));
        }
    }

    /**
     * Publish Global Set Drafts
     *
     * @param array $variables
     * @return void
     */
    public function actionPublishDraft()
    {
        $this->requirePostRequest();

        $draftId = $this->request->getParam('draftId');

        $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($draftId);

        if (!$draft) {
            $this->setError("No draft exists with the ID '{$draftId}'.");
            return;
        }

        $globalSet = Translations::$plugin->globalSetRepository->getSetById($draft->globalSetId, $draft->site);

        if (!$globalSet) {
            $this->setError("No global set exists with the ID '{$draft->id}'.");
            return;
        }

        //@TODO $this->enforceEditEntryPermissions($entry);

        $fields = $this->request->getParam('fields') ?? [];

        if ($fields) {
            $draft->setFieldValues($fields);
        }

        // restore the original name
        $draft->name = $globalSet->name;

        $file = Translations::$plugin->fileRepository->getFileByDraftId($draftId, $globalSet->id);

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            if ($file) {
                $order = Translations::$plugin->orderRepository->getOrderById($file->orderId);

                $file->status = Constants::FILE_STATUS_PUBLISHED;
                $file->draftId = 0;

                Translations::$plugin->fileRepository->saveFile($file);

                $order->status = Translations::$plugin->orderRepository->getNewStatus($order);
                Translations::$plugin->orderRepository->saveOrder($order);
            }

            if (Translations::$plugin->globalSetDraftRepository->publishDraft($draft)) {
                $this->redirect($globalSet->getCpEditUrl(), 302, true);

                $this->setSuccess('Draft published.');
                $transaction->commit();

                return Translations::$plugin->globalSetDraftRepository->deleteDraft($draft);
            } else {
                $this->setError('Couldn’t publish draft.');
                $transaction->rollBack();

                // Send the draft back to the template
                Craft::$app->urlManager->setRouteParams(array(
                    'draft' => $draft
                ));
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
        }
    }

    /**
     * Delete Global Set Drafts
     *
     * @param array $variables
     * @return void
     */
    public function actionDeleteDraft()
    {
        $this->requirePostRequest();

        $draftId = $this->request->getParam('draftId');

        $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($draftId);

        if (!$draft) {
            $this->setError("No draft exists with the ID '{$draftId}'.");
            return;
        }

        $globalSet = $draft->getGlobalSet();

        Translations::$plugin->globalSetDraftRepository->deleteDraft($draft);

        $file = Translations::$plugin->fileRepository->getFileByDraftId($draftId, $globalSet->id);

        if ($file) {
            $order = Translations::$plugin->orderRepository->getOrderById($file->orderId);

            $file->status = Constants::FILE_STATUS_CANCELED;
            $file->draftId = null;
            $file->dateDelivered = null;

            Translations::$plugin->fileRepository->saveFile($file);

            $order->status = Translations::$plugin->orderRepository->getNewStatus($order);
            Translations::$plugin->orderRepository->saveOrder($order);
        }

        $this->setSuccess('Draft deleted.');

        return $this->redirect($globalSet->getCpEditUrl(), 302, true);
    }
}