<?php

namespace acclaro\translations\controllers;

use acclaro\translations\Translations;
use Craft;

class AssetController extends BaseController
{
    // Asset Draft CRUD Methods
    // =========================================================================

    public function actionEditDraft(array $variables = array())
    {
        $data = Craft::$app->getRequest()->resolve()[1];

        if (empty($data['elementId'])) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Param “{name}” doesn’t exist.', array('name' => 'elementId')));
            return;
        }

        $assetId = $data['elementId'];
        $asset = Craft::$app->assets->getAssetById($assetId);

        $variables['filename'] = $asset->getFilename(false);
        $variables['element'] = $asset;

        $draft = Translations::$plugin->assetDraftRepository->getDraftById($data['draftId']);
        $variables['draft'] = $draft;

        $variables['file'] = Translations::$plugin->fileRepository->getFileByDraftId($draft->draftId);

        $variables['dimensions'] = $asset->dimensions;
        $variables['assetUrl'] = $asset->url;
        $variables['canReplaceFile'] = $asset->isEditable;
        $variables['previewHtml'] = $asset->editorHtml;
        $variables['volume'] = $asset->volume;
        $variables['formattedSize'] = $asset->formattedSize;
        $variables['formattedSizeInBytes'] = $asset->formattedSizeInBytes;

        $this->renderTemplate('translations/assets/_editDraft', $variables);
    }

    public function actionSaveDraft()
    {
        // TODO: add save draft logic

    }

    public function actionPublishDraft()
    {
        // TODO: add publish draft logic

    }

    public function actionDeleteDraft()
    {
        // TODO: add delete draft logic

    }
}
