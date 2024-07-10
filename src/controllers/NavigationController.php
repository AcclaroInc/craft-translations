<?php

namespace acclaro\translations\controllers;

use acclaro\translations\Constants;
use Craft;
use acclaro\translations\Translations;

class NavigationController extends BaseController
{
    /**
     * Edit Node
     *
     * @param array $variables
     * @return void
     */
    public function actionEditDraft(array $variables = array())
    {
        $variables = $this->request->resolve()[1];
        $nodeId = $variables['nodeId'];
        $siteId = $variables['site'];
        if (!$nodeId) {
            $this->setError('Node ID is missing.');
            return;
        }

        $node = Translations::$plugin->navigationDraftRepository->getDraftById($variables['draftId']);
        if (!$node) {
            $this->setError('Node not found.');
            return;
        }
        $this->renderTemplate('translations/nodes/_editDraft', [
            'data' => $node,
            'draft' => $variables
        ]);
    }

    /**
     * Save Node
     *
     * @return void
     */
    public function actionSaveDraft()
    {
        $this->requirePostRequest();

        $draftId = Craft::$app->getRequest()->getBodyParam('draftId');
        $fields = Craft::$app->getRequest()->getBodyParam('fields');
        $changedFields = Craft::$app->getRequest()->getBodyParam('changedFields');

        // Ensure draft exists
        if ($draftId) {
            $draft = Translations::$plugin->navigationDraftRepository->getDraftById($draftId);

            if (!$draft) {
                $this->setError("No draft exists with the ID '{$draftId}'.");
                return;
            }
        } else {
            $this->setError("Draft ID not provided.");
            return;
        }

        // Convert changedFields to an array if it's an object
        if ($changedFields) {
            $changedFieldsFlattened = json_decode($changedFields, true);
            foreach ($changedFieldsFlattened as $changedField) {
                $this->replaceValues($fields, $changedField);
            }
        }

        if (Translations::$plugin->navigationDraftRepository->saveDraft($draft, $fields)) {
            $this->setSuccess('Node saved.');
            return $this->redirect($draft->getCpEditUrl(), 302);
        } else {
            $this->setError('Couldn’t save draft.');
            Craft::$app->getUrlManager()->setRouteParams(['draft' => $draft]);
        }
    }

    /**
     * Helper function to recursively replace values in the fields array
     */
    private function replaceValues(array &$fields, array $changedFields)
    {
        foreach ($fields as $key => &$value) {
            if (is_array($value)) {
                $this->replaceValues($value, $changedFields);
            } elseif (isset($changedFields[$key]) && $value == $changedFields[$key]['oldValue']) {
                $value = $changedFields[$key]['currentValue'];
            }
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
        $changedFields = Craft::$app->getRequest()->getBodyParam('changedFields');
        $draft = Translations::$plugin->navigationDraftRepository->getDraftById($draftId);

        if (!$draft) {
            $this->setError("No draft exists with the ID '{$draftId}'.");
            return;
        }

        $nav = Translations::$plugin->navigationDraftRepository->getNavById($draft->navId, $draft->site);

        if (!$nav) {
            $this->setError("No global set exists with the ID '{$draft->id}'.");
            return;
        }

        $fields = $this->request->getParam('fields') ?? [];

        if ($changedFields) {
            $changedFieldsFlattened = json_decode($changedFields, true);
            foreach ($changedFieldsFlattened as $changedField) {
                $this->replaceValues($fields, $changedField);
            }
        }

        if ($fields) {
            // Get the keys of the object
            $domFields = array_keys($fields)[0];
            $draftFields = array_keys($draft->data['fields'])[0];
            if ($fields[$domFields] !== $draft->data['fields'][$draftFields]) {
                $this->setError("Please save fields before publishing draft '{$draft->id}'.");
                return;
            }
            $draft->setFieldValues($fields);
        }

        $file = Translations::$plugin->fileRepository->getFileByDraftId($draftId);
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

            if (Translations::$plugin->navigationDraftRepository->publishDraft($draft)) {
                $this->redirect("translations/orders/detail/$order->id?site=default", 302, true);

                $this->setSuccess('Draft published.');
                $transaction->commit();

                return Translations::$plugin->navigationDraftRepository->deleteDraft($draft);
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
        $navId = $this->request->getParam('navId');

        $draft = Translations::$plugin->navigationDraftRepository->getDraftById($draftId);

        if (!$draft) {
            $this->setError("No draft exists with the ID '{$draftId}'.");
            return;
        }
        $nav = Translations::$plugin->navigationDraftRepository->getNavById($navId);

        if (!$nav) {
            $this->setError("No global set exists with the ID '{$draft->id}'.");
            return;
        }

        Translations::$plugin->navigationDraftRepository->deleteDraft($draft);

        $file = Translations::$plugin->fileRepository->getFileByDraftId($draftId);
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

        return $this->redirect("translations/orders/detail/$order->id?site=default", 302, true);
    }
}
