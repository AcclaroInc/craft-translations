<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\controllers;

use Craft;
use craft\helpers\Path;
use craft\helpers\Assets;
use craft\elements\Asset;
use craft\web\UploadedFile;
use craft\elements\GlobalSet;
use craft\helpers\FileHelper;
use craft\helpers\ArrayHelper;
use craft\helpers\ElementHelper;
use yii\web\NotFoundHttpException;

use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\services\job\ImportFiles;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class FilesController extends BaseController
{
    protected array|int|bool $allowAnonymous = ['actionImportFile', 'actionExportFile', 'actionCreateExportZip'];

    /**
     * @var Order
     */
    protected $order;

    /**
	 * Allowed types of site images.
	 *
	 * @var array
	 */
	private $_allowedTypes = Constants::FILE_FORMAT_ALLOWED;

    public function actionCreateExportZip()
    {
        $params = Craft::$app->getRequest()->getRequiredBodyParam('params');

        $fileFormat = $params['format'] ?? Constants::FILE_FORMAT_XML;

        $order = Translations::$plugin->orderRepository->getOrderById($params['orderId']);

        $errors = array();

        $orderAttributes = $order->getAttributes();

        //Filename Zip Folder
        $zipName = $this->getZipName($orderAttributes);

        // Set destination zip
        $zipDest = Craft::$app->path->getTempPath() . '/' . $zipName . '.' . Constants::FILE_FORMAT_ZIP;

        // Create zip
        $zip = new \ZipArchive();

        // Open zip
        if ($zip->open($zipDest, $zip::CREATE) !== true)
        {
            $errors[] = 'Unable to create zip file: '.$zipDest;
            Translations::$plugin->logHelper->log('['. __METHOD__ .'] Unable to create zip file: '.$zipDest, Constants::LOG_LEVEL_ERROR);
            return false;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        //Iterate over each file on this order
        if ($order->files)
        {
            $hasMisalignment = $order->isTmMisaligned(false);
            foreach ($order->getFiles() as $file)
            {
                // skip failed files
                if ($file->isCanceled()) continue;

                $element = Translations::$plugin->elementRepository->getElementById($file->elementId, $file->sourceSite);

                $targetSite = $file->targetSite;

                if ($element instanceof GlobalSet) {
                    $fileName = $file->elementId . '-' . ElementHelper::normalizeSlug($element->name) .
                        '-' . $targetSite . '.' . $fileFormat;
                } else if ($element instanceof Asset) {
                    $assetFilename = $element->getFilename();
                    $fileInfo = pathinfo($element->getFilename());
                    $fileName = $file->elementId . '-' . basename($assetFilename,'.'.$fileInfo['extension']) . '-' . $targetSite . '.' . $fileFormat;
                } else {
                    $fileName = $file->elementId . '-' . $element->slug . '-' . $targetSite . '.' . $fileFormat;
                }

                if ($fileFormat === Constants::FILE_FORMAT_JSON) {
                    $fileContent = Translations::$plugin->elementToFileConverter->xmlToJson($file->source);
                } else if ($fileFormat === Constants::FILE_FORMAT_CSV) {
                    $fileContent = Translations::$plugin->elementToFileConverter->xmlToCsv($file->source);
                } else {
                    $fileContent = $file->source;
                }

                if ($order->includeTmFiles && $hasMisalignment) $fileName = "source/" . $fileName;

                if (! $fileContent || !$zip->addFromString($fileName, $fileContent)) {
                    $errors[] = 'There was an error adding the file '.$fileName.' to the zip: '.$zipName;
                    Translations::$plugin->logHelper->log( '['. __METHOD__ .'] There was an error adding the file '.$fileName.' to the zip: '.$zipName, Constants::LOG_LEVEL_ERROR );
                }

                /** Check if entry exists in target site for reference comparison */
                if ($hasMisalignment && Translations::$plugin->elementRepository->getElementById($file->elementId, $file->targetSite)) {
                    $tmFile = $file->getTmMisalignmentFile($fileFormat);
                    $fileName = $tmFile['fileName'];

                    if ($order->includeTmFiles && $file->hasTmMisalignments(true)) {

                        if (! $zip->addFromString("references/" . $fileName, $tmFile['fileContent'])) {
                            $errors[] = 'There was an error adding the file '.$fileName.' to the zip: '.$zipName;
                            Translations::$plugin->logHelper->log( '['. __METHOD__ .'] There was an error adding the file '.$fileName.' to the zip: '.$zipName, Constants::LOG_LEVEL_ERROR );
                        }
                    }

                    $file->reference = $tmFile['reference'];
                }

                if ($file->isNew() || $file->isModified()) {
                    $file->status = Constants::FILE_STATUS_IN_PROGRESS;
                }
                Translations::$plugin->fileRepository->saveFile($file);
            }

            if ($order->status !== ($newStatus = Translations::$plugin->orderRepository->getNewStatus($order))) {
                $order->status = $newStatus;
                $order->logActivity(sprintf('Order status changed to \'%s\'', $order->getStatusLabel()));
            }
        }

        // Close zip
        $zip->close();

        if(count($errors) > 0)
        {
            $transaction->rollBack();
            return $this->asFailure($this->getErrorMessage(implode('\n', $errors)));
        }

        Craft::$app->getElements()->saveElement($order, true, true, false);
        $transaction->commit();

        return $this->asSuccess(null, ['translatedFiles' => $zipDest]);
    }

    /**
     * Export Functionlity
	 * Sends the zip file created to the user
     */
    public function actionExportFile()
    {
        $filename = Craft::$app->getRequest()->getRequiredQueryParam('filename');
        if (!is_file($filename) || !Path::ensurePathIsContained($filename)) {
            throw new NotFoundHttpException(Craft::t('app', 'Invalid file name: {filename}', [
                'filename' => $filename
			]));
        }

        Craft::$app->getResponse()->sendFile($filename, null, ['inline' => true]);

        return FileHelper::unlink($filename);
    }

    public function actionImportFile()
    {
        $this->requireLogin();
        $this->requirePostRequest();

        if (!Translations::$plugin->userRepository->userHasAccess('translations:orders:import')) {
            return;
        }

        $file = UploadedFile::getInstanceByName('zip-upload');

        //Get Order Data
        $orderId = Craft::$app->getRequest()->getParam('orderId');
        $sourceChangedElements = explode(",", Craft::$app->getRequest()->getParam('elements'));

        $this->order = Translations::$plugin->orderRepository->getOrderById($orderId);

        $totalWordCount = ($this->order->wordCount * count($this->order->getTargetSitesArray()));

        $total_files = (count($this->order->files) * count($this->order->getTargetSitesArray()));

        try {
            // Make sure a file was uploaded
            if ($file && $file->size > 0) {
                if (!in_array($file->extension, $this->_allowedTypes)) {
                    Craft::$app->getSession()->set('fileImportError', 1);
                    $this->setError("'$file->name' is not a supported translation file type. Please submit a [ZIP, XML, JSON, CSV] file.");
                } else {
                    //If is a Zip File
                    if ($file->extension === Constants::FILE_FORMAT_ZIP) {

                        $extractedPath = $this->_extractFiles($file);

                        if ($extractedPath) {
                            $fileName = preg_replace('/\\.[^.\\s]{3,4}$/', '', Assets::prepareAssetName($file->name));

                            $files = FileHelper::findFiles($extractedPath);

                            $assetIds = [];
                            $fileNames = [];
                            $fileInfo = null;

                            foreach ($files as $key => $file) {
                                if (! is_bool(strpos($file, '__MACOSX')) || strpos($file, '/references/') > -1) {
                                    unlink($file);

                                    continue;
                                }

                                $filename = Assets::prepareAssetName($file);

                                $fileInfo = pathinfo($filename);

                                $uploadVolumeId = ArrayHelper::getValue(Translations::getInstance()->getSettings(), 'uploadVolume');

                                $folder = Craft::$app->getAssets()->getRootFolderByVolumeId($uploadVolumeId);

                                $pathInfo = pathinfo($file);

                                $compatibleFilename = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.' . Constants::FILE_FORMAT_TXT;

                                rename($file, $compatibleFilename);

                                $asset = new Asset();
                                $asset->tempFilePath = $compatibleFilename;
                                $asset->filename = $compatibleFilename;
                                $asset->newFolderId = $folder->id;
                                $asset->volumeId = $folder->volumeId;
                                $asset->avoidFilenameConflicts = true;
                                $asset->uploaderId = Craft::$app->getUser()->getId();
                                $asset->setScenario(Asset::SCENARIO_CREATE);

                                if (! Craft::$app->getElements()->saveElement($asset)) {
                                    $errors = $asset->getFirstErrors();

                                    Translations::$plugin->logHelper->log('Error: ' . implode(";\n", $errors), Constants::LOG_LEVEL_ERROR);
                                    return $this->asFailure(sprintf(
                                        "%s %s",
                                        $this->getErrorMessage('Failed to save the asset. '),
                                        implode(";\n", $errors)
                                    ));
                                }

                                $assetIds[] = $asset->id;
                                $fileInfo['basename'] ? $fileNames[$asset->id] = $fileInfo['basename'] : '';
                            }

                            FileHelper::removeDirectory($extractedPath.'/'.$fileName);

                            // Process files via job or directly based on order "size"
                            if ($totalWordCount > Constants::WORD_COUNT_LIMIT) {
                                $job = Craft::$app->queue->push(new ImportFiles([
                                    'description' => Constants::JOB_IMPORTING_FILES,
                                    'orderId' => $orderId,
                                    'totalFiles' => $total_files,
                                    'assets' => $assetIds,
                                    'fileFormat' => $fileInfo['extension'],
                                    'fileNames' => $fileNames,
                                    'discardElements' => $sourceChangedElements
                                ]));

                                if ($job) {
                                    $queueOrders = Craft::$app->getSession()->get('queueOrders');
                                    $queueOrders[$job] = $orderId;
                                    Craft::$app->getSession()->set('queueOrders', $queueOrders);
                                    Craft::$app->getSession()->set('importQueued', "1");
                                    $params = [
                                        'id' => (int) $job,
                                        'notice' => 'Done updating translation drafts',
                                        'url' => Constants::URL_ORDER_DETAIL . $orderId
                                    ];
                                    Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
                                }
                                $this->setNotice("File queued for import. Check activity log for any errors.");
                            } else {
                                $fileSvc = new ImportFiles();
                                $success = true;
                                foreach ($assetIds as $key => $id) {
                                    $a = Craft::$app->getAssets()->getAssetById($id);
                                    $res = $fileSvc->processFile($a, $this->order, $fileInfo['extension'], $fileNames, $sourceChangedElements);
                                    Craft::$app->getElements()->deleteElement($a);
                                    if ($res === false) $success = false;
                                }
                                if (! $success) {
                                    $this->setError("Error importing file. Please check activity log for details.");
                                } else {
                                    $this->setSuccess("File uploaded successfully");
                                }
                            }
                        } else {
                            Craft::$app->getSession()->set('fileImportError', 1);
                            $this->setError("Unable to unzip ". $file->name ." Operation not permitted or Decompression Failed.");
                        }
                    } else {
                        $filename = Assets::prepareAssetName($file->name);

                        $uploadVolumeId = ArrayHelper::getValue(Translations::getInstance()->getSettings(), 'uploadVolume');

                        $folder = Craft::$app->getAssets()->getRootFolderByVolumeId($uploadVolumeId);

                        $compatibleFilename = $file->tempName . '.' . Constants::FILE_FORMAT_TXT;

                        rename($file->tempName, $compatibleFilename);

                        $asset = new Asset();
                        $asset->tempFilePath = $compatibleFilename;
                        $asset->filename = $compatibleFilename;
                        $asset->newFolderId = $folder->id;
                        $asset->volumeId = $folder->volumeId;
                        $asset->avoidFilenameConflicts = true;
                        $asset->uploaderId = Craft::$app->getUser()->getId();
                        $asset->setScenario(Asset::SCENARIO_CREATE);

                        if (! Craft::$app->getElements()->saveElement($asset)) {
                            $errors = $asset->getFirstErrors();

                            return $this->asFailure(sprintf(
                                "%s %s",
                                $this->getErrorMessage('Failed to save the asset. '),
                                implode(";\n", $errors)
                            ));
                        }

                        $totalWordCount = Translations::$plugin->fileRepository->getUploadedFilesWordCount($asset, $file->extension);

                        // Process files via job or directly based on file "size"
                        if ($totalWordCount > Constants::WORD_COUNT_LIMIT) {
                            $job = Craft::$app->queue->push(new ImportFiles([
                                'description' => Constants::JOB_IMPORTING_FILES,
                                'orderId' => $orderId,
                                'totalFiles' => $total_files,
                                'assets' => [$asset->id],
                                'fileFormat' => $file->extension,
                                'fileNames' => [$asset->id => $file->name],
                                'discardElements' => $sourceChangedElements
                            ]));

                            if ($job) {
                                $queueOrders = Craft::$app->getSession()->get('queueOrders');
                                $queueOrders[$job] = $orderId;
                                Craft::$app->getSession()->set('queueOrders', $queueOrders);
                                Craft::$app->getSession()->set('importQueued', "1");
                                $params = [
                                    'id' => (int) $job,
                                    'notice' => 'Done updating translation drafts',
                                    'url' => Constants::URL_ORDER_DETAIL . $orderId
                                ];
                                Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
                            }
                            $this->setNotice("File '{$file->name}' queued for import. Check activity log for any errors.");
                        } else {
                            $fileSvc = new ImportFiles();
                            $a = Craft::$app->getAssets()->getAssetById($asset->id);
                            $res = $fileSvc->processFile($a, $this->order, $file->extension, [$asset->id => $file->name], $sourceChangedElements);
                            Craft::$app->getElements()->deleteElement($a);

                            if($res !== false){
                                $this->setSuccess("File uploaded successfully '{$file->name}'");
                            } else {
                                Craft::$app->getSession()->set('fileImportError', 1);
                                $this->setError("File import error. Please check the order activity log for details.");
                            }
                        }
                    }
                }
            } else {
                Craft::$app->getSession()->set('fileImportError', 1);
                $this->setError("The file you are trying to import is empty.");
            }
        } catch (\Exception $exception) {
            Translations::$plugin->logHelper->log($exception, Constants::LOG_LEVEL_ERROR);
            $this->setError($exception->getMessage());
        }
    }
    
    private function _extractFiles($uploadedFile) {
        $zip = new \ZipArchive();
        $assetPath = $uploadedFile->saveAsTempFile();
        $extractPath = Craft::$app->getPath()->getTempPath() . '/extracted/' . uniqid(pathinfo($uploadedFile->name, PATHINFO_FILENAME), true);
        FileHelper::createDirectory($extractPath);
        $zip->open($assetPath);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_contains($zip->getNameIndex($i), '..') || str_contains($zip->getNameIndex($i), '__MACOSX') || str_contains($zip->getNameIndex($i), '/references/')) {
                continue;
            }
            $zip->extractTo($extractPath, array($zip->getNameIndex($i)));
        }
        $zip->close();

        return $extractPath;
    }

    /**
     * Get Difference in File source and target
     *
     * @return void
     */
    public function actionGetFileDiff()
    {
        $success = false;
        $error = null;
        $data = ['previewClass' => 'disabled', 'originalUrl' => '', 'newUrl' => ''];

        $fileId = Craft::$app->getRequest()->getParam('fileId');
        if (!$fileId) {
            $error = "FileId not found.";
        } else {
            $file = Translations::$plugin->fileRepository->getFileById($fileId);
            $error = "File not found.";
            if ($file && (in_array($file->status, [Constants::FILE_STATUS_REVIEW_READY, Constants::FILE_STATUS_COMPLETE, Constants::FILE_STATUS_PUBLISHED]))) {
                try {
                    $element = Translations::$plugin->elementRepository->getElementById($file->elementId, $file->sourceSite);

                    $data['diff'] = Translations::$plugin->fileRepository->getSourceTargetDifferences($file->source, $file->target);

                    if ($file->status !== Constants::FILE_STATUS_REVIEW_READY) {
                        $data['previewClass'] = '';
                        $data['originalUrl'] = $element->url;
                        $data['newUrl'] = $file->previewUrl;
                    }
                    $data['fileId'] = $file->id;

                    $error = null;
                    $success = true;
                } catch(\Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->asJson([
            'success' => $success,
            'data' => $data,
            'error' => $this->getErrorMessage($error)
        ]);
    }

    /**
     * Create Zip of Translations memory alignment files
     */
    public function actionCreateTmExportZip() {
        $orderId = Craft::$app->getRequest()->getBodyParam('orderId');
        $format = Craft::$app->getRequest()->getBodyParam('format');
        $files = json_decode(Craft::$app->getRequest()->getBodyParam('files'), true);

        try {
            $order = Translations::$plugin->orderRepository->getOrderById($orderId);
            $orderAttributes = $order->getAttributes();

            //Filename Zip Folder
            $zipName = $this->getZipName($orderAttributes) . '_TM';

            // Set destination zip
            $zipDest = Craft::$app->path->getTempPath() . '/' . $zipName . '.' . Constants::FILE_FORMAT_ZIP;

            // Create zip
            $zip = new \ZipArchive();

            // Open zip
            if ($zip->open($zipDest, $zip::CREATE) !== true) {
                return $this->asFailure(sprintf("%s. {%s}", $this->getErrorMessage("Unable to create zip file"), $zipDest));
            }

            //Iterate over each file on this order
            if ($order->files) {
                foreach ($order->getFiles() as $file) {
                    if (! in_array($file->id, $files) || !$file->hasTmMisalignments()) continue;

                    $tmFile = $file->getTmMisalignmentFile($format);
                    $fileName = $tmFile['fileName'];
                    $fileContent = $tmFile['fileContent'];

                    if (! $fileContent || ! $zip->addFromString($fileName, $fileContent)) {
                        return $this->asFailure(sprintf(
                            $this->getErrorMessage('There was an error adding the file {%s} to the zip. {%s}'),
                            $fileName,
                            $zipName
                        ));
                    }

                    $file->reference = $tmFile['reference'];
                    Translations::$plugin->fileRepository->saveFile($file);
                }
            }

            // Close zip
            $zip->close();
        } catch(\Exception $e) {
            Translations::$plugin->logHelper->log('['. __METHOD__ .']' . $e->getMessage(), Constants::LOG_LEVEL_ERROR);
            return $this->asJson(['success' => false, 'message' => $this->getErrorMessage($e->getMessage())]);
        }

        return $this->asSuccess(null, ['tmFiles' => $zipDest]);
    }

    /**
     * Send Translation memory files to translation service provider
     */
    public function actionSyncTmFiles() {
        $orderId = Craft::$app->getRequest()->getBodyParam('orderId');
        $format = Craft::$app->getRequest()->getBodyParam('format');
        $files = json_decode(Craft::$app->getRequest()->getBodyParam('files'), true);
        $order = Translations::$plugin->orderRepository->getOrderById($orderId);

        //Iterate over each file on this order and only process if trackTargetChanges is enabled
        if ($order->files && $order->trackTargetChanges) {
            $translationService = $order->getTranslationService();

            foreach ($order->getFiles() as $file) {
                if (in_array($file->id, $files) && $file->hasTmMisalignments()) {
                    $translationService->sendOrderReferenceFile($order, $file, $format);
                }
            }
        }
        return $this->asJson(['success' => true]);
    }

    /**
     * Returns entry editor content for file preview
     */
    public function actionGetElementContent()
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $fileId = Craft::$app->getRequest()->getBodyParam('fileId');

        $html = "";

        try {
            $file = Translations::$plugin->fileRepository->getFileById($fileId);

            $element = $file->getElement(false);

            if ($file->isComplete() && $file->hasPreview() && $draft = $file->hasDraft()) {
                $element = $draft;
            }

            $form = $element->getFieldLayout()->createForm($element, true);
            $html = $form->render();
        } catch(\Exception $e) {
            Translations::$plugin->logHelper->log($e, Constants::LOG_LEVEL_ERROR);
            return $this->asFailure(null, ['message' => $this->getErrorMessage("Error loading preview html.")]);
        }

        return $this->asSuccess(null, ['html' => $html]);
    }

    // Private Methods
    /**
     * @param $order
     * @return string
     */
    private function getZipName($order) {

        $title = str_replace(' ', '_', $order['title']);
        $title = preg_replace('/[^A-Za-z0-9\-_]/', '', $title);
        $len = 50;
        $title = (strlen($title) > $len) ? substr($title,0,$len) : $title;
        $zip_name =  $title.'_'.$order['id'];

        return $zip_name;
    }
}
