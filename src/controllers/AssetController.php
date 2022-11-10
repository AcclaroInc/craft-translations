<?php

namespace acclaro\translations\controllers;

use Craft;
use acclaro\translations\Translations;
use acclaro\translations\Constants;
use acclaro\translations\services\repository\AssetDraftRepository;

class AssetController extends BaseController
{
    protected $service;

    public function __construct($id, $module = null)
    {
        parent::__construct($id, $module);

        $this->service = new AssetDraftRepository();
    }

    /**
     * Edit an asset draft
     *
     * @param array $variables
     * @return void
     */
    public function actionEditDraft(array $variables = array())
    {
        $data = Craft::$app->getRequest()->resolve()[1];
        $siteService = Craft::$app->getSites();

        /** @var craft\elements\User $currentUser */
        $currentUser = Craft::$app->getUser()->getIdentity();

        $variables['canEdit'] = $currentUser->can('translations:orders:create');

        $site = $siteService->getSiteByHandle($data['site'] ?? $siteService->getCurrentSite()->handle);

        if (empty($data['elementId'])) {
            $this->setError('Param “elementId” doesn’t exist.');
            return;
        }

        $assetId = $data['elementId'];
        $asset = Craft::$app->assets->getAssetById($assetId, $site->id);

        $variables['filename'] = $asset->getFilename(false);
        $variables['assetId'] = $assetId;
        $variables['asset'] = $asset;
        $variables['selectedSubnavItem'] = 'orders';

        $draft = Translations::$plugin->assetDraftRepository->getDraftById($data['draftId']);
        $variables['element'] = $draft;

        $variables['selectedSite'] = isset($data['site']) ? $site : $siteService->getSiteById($draft->site);

        $variables['file'] = Translations::$plugin->fileRepository->getFileByDraftId($draft->draftId);

        $variables['dimensions'] = $asset->dimensions;
        $variables['assetUrl'] = $asset->url;
        $variables['author'] = Craft::$app->getUsers()->getUserById((int) $asset->uploaderId);
        // $variables['canReplaceFile'] = $asset->isEditable;
        $variables['title'] = $asset->title;
        $variables['isRevision'] = $asset->getIsRevision();
        $variables['previewHtml'] = $asset->previewHtml;
        $variables['volume'] = $asset->volume;
        $variables['formattedSize'] = $asset->formattedSize;
        $variables['formattedSizeInBytes'] = $asset->formattedSizeInBytes;

        $variables['saveSourceAction'] = 'translations/asset/save-draft';
        $variables['deleteSourceAction'] = 'translations/asset/delete-draft';
        $variables['publishSourceAction'] = 'translations/asset/publish-draft';
        $variables['canEdit'] = true;
        $variables['canUpdateSource'] = true;

        $this->renderTemplate('translations/assets/_editDraft', $variables);
    }

    /**
     * Save an asset draft record
     *
     * @return void
     */
    public function actionSaveDraft()
    {
        $this->requirePostRequest();

        $assetId = $this->request->getParam('assetId');
        $siteId = $this->request->getParam('siteId');
        $asset = $this->service->getAssetById($assetId, $siteId);

        if (!$asset) {
            $this->setError("No Asset exists with the ID '{$assetId}'.");
            return;
        }

        $draftId = $this->request->getParam('draftId');
        if ($draftId) {
            $draft = Translations::$plugin->assetDraftRepository->getDraftById($draftId);

            if (!$draft) {
                $this->setError("No draft exists with the ID '{$draftId}'.");
                return;
            }
        } else {
            $draft = Translations::$plugin->assetDraftRepository->makeNewDraft();
        }

        $draft->id = $asset->id;
        $draft->title = $this->request->getParam('title') ?? $asset->title;
        $draft->site = $siteId;

        $fields = $this->request->getParam('fields') ?? [];

        if ($fields) {
            $draft->setFieldValues($fields);
        }

        $this->service->saveDraft($draft);

        if (Translations::$plugin->assetDraftRepository->saveDraft($draft, $fields)) {
            $this->setSuccess('Draft saved.');

            $this->redirect($draft->getCpEditUrl(), 302, true);
        } else {
            $this->setError('Couldn’t save draft.');

            Craft::$app->urlManager->setRouteParams(array(
                'asset' => $draft
            ));
        }
    }

    /**
     * Publish an asset draft record
     *
     * @return void
     */
    public function actionPublishDraft()
    {
        $this->requirePostRequest();

        $draftId = Craft::$app->getRequest()->getParam('draftId');
        $assetId = Craft::$app->getRequest()->getParam('assetId');
        $draft = Translations::$plugin->assetDraftRepository->getDraftById($draftId);

        if (!$draft) {
            $this->setError("No draft exists with the ID '{$draftId}'.");
            return;
        }

        $asset = Craft::$app->assets->getAssetById($assetId, $draft->site);

        if (!$asset) {
            $this->setError("No asset exists with the ID '{$draft->assetId}'.");
            return;
        }

        $draft->title = $this->request->getParam('title') ?? $asset->title;
        $draft->newFilename = $this->request->getParam('filename');

        $fields = $this->request->getParam('fields') ?? [];

        if ($fields) {
            $draft->setFieldValues($fields);
        }

        // restore the original name
        $draft->name = $asset->title;

        $file = Translations::$plugin->fileRepository->getFileByDraftId($draftId, $asset->id);

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            if ($file) {
                $order = Translations::$plugin->orderRepository->getOrderById($file->orderId);

                $file->status = Constants::ORDER_STATUS_PUBLISHED;
                $file->draftId = 0;

                Translations::$plugin->fileRepository->saveFile($file);

                $order->status = Translations::$plugin->orderRepository->getNewStatus($order);
                Translations::$plugin->orderRepository->saveOrder($order);
            }

            if (Translations::$plugin->assetDraftRepository->publishDraft($draft)) {
                $this->redirect($asset->getCpEditUrl(), 302, true);

                $this->setSuccess('Draft published.');
                $transaction->commit();

                return Translations::$plugin->assetDraftRepository->deleteDraft($draft);
            } else {
                $this->setError('Couldn’t publish draft.');
                $transaction->rollBack();

                // Send the draft back to the template
                Craft::$app->urlManager->setRouteParams(array(
                    'asset' => $draft
                ));
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
        }
    }

    /**
     * Delete an asset draft record
     *
     * @return void
     */
    public function actionDeleteDraft()
    {
        $this->requirePostRequest();

        $draftId = Craft::$app->getRequest()->getParam('draftId');
        $draft = Translations::$plugin->assetDraftRepository->getDraftById($draftId);

        if (!$draft) {
            $this->setError("No draft exists with the ID '{$draftId}'.");
            return;
        }

        $asset = Translations::$plugin->assetDraftRepository->getAssetById($draft->assetId);
        $url = $asset->getCpEditUrl();

        Translations::$plugin->assetDraftRepository->deleteDraft($draft);

        $file = Translations::$plugin->fileRepository->getFileByDraftId($draftId, $asset->id);

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

        return $this->redirect($url, 302, true);
    }
}
