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

use acclaro\translations\Constants;
use Craft;
use DateTime;
use ZipArchive;
use craft\helpers\Path;
use craft\web\Controller;
use craft\helpers\Assets;
use craft\models\Section;
use yii\web\HttpException;
use craft\web\UploadedFile;
use craft\elements\Category;
use craft\helpers\FileHelper;
use craft\elements\GlobalSet;
use craft\base\VolumeInterface;
use craft\helpers\ElementHelper;
use yii\web\NotFoundHttpException;
use acclaro\translations\services\App;
use acclaro\translations\Translations;
use acclaro\translations\services\job\ImportFiles;
use acclaro\translations\services\repository\SiteRepository;
use craft\elements\Asset;
use craft\errors\UploadFailedException;
use craft\helpers\ArrayHelper;
use yii\base\ErrorException;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class FilesController extends Controller
{
    protected $allowAnonymous = ['actionImportFile', 'actionExportFile', 'actionCreateExportZip'];

    /**
     * @var int
     */
    protected $pluginVersion;

    /**
    * @var Order
    */
    protected $order;

    protected $variables;

    /**
	 * Allowed types of site images.
	 *
	 * @var array
	 */
	private $_allowedTypes = array('zip', 'xml', 'json', 'csv');

    public function actionCreateExportZip()
    {
        $params = Craft::$app->getRequest()->getRequiredBodyParam('params');
        
        $fileFormat = $params['format'] ?? Constants::DEFAULT_FILE_EXPORT_FORMAT;

        $order = Translations::$plugin->orderRepository->getOrderById($params['orderId']);
        $files = Translations::$plugin->fileRepository->getFilesByOrderId($params['orderId'], null);

        $siteRepository = new SiteRepository(Craft::$app);
        $tempPath = Craft::$app->path->getTempPath();
        $errors = array();

        $orderAttributes = $order->getAttributes();

        //Filename Zip Folder
        $zipName = $this->getZipName($orderAttributes);
        
        // Set destination zip
        $zipDest = Craft::$app->path->getTempPath() . '/' . $zipName . '.zip';
        
        // Create zip
        $zip = new ZipArchive();

        // Open zip
        if ($zip->open($zipDest, $zip::CREATE) !== true)
        {
            $errors[] = 'Unable to create zip file: '.$zipDest;
            Craft::log( '['. __METHOD__ .'] Unable to create zip file: '.$zipDest, LogLevel::Error, 'translations' );
            return false;
        }

        //Iterate over each file on this order
        if ($order->files)
        {
            foreach ($order->files as $file)
            {
                // skip failed files
                if ($file->status == 'canceled' ) continue;

                $element = Craft::$app->elements->getElementById($file->elementId, null, $file->sourceSite);

                $targetSite = $file->targetSite;

                if ($element instanceof GlobalSet) {
                    $filename = $file->elementId . '-' . ElementHelper::normalizeSlug($element->name) .
                        '-' . $targetSite . '.' . $fileFormat;
                } else {
                    $filename = $file->elementId . '-' . $element->slug . '-' . $targetSite . '.' . $fileFormat;
                }

                $path = $tempPath . $filename;
                
                $fileContent = Translations::$plugin->elementToFileConverter->convert($element, $fileFormat,
                    [
                        'sourceSite'    => $file->sourceSite,
                        'targetSite'    => $file->targetSite,
                        'wordCount'     => $file->wordCount,
                    ]
                );

                if (!$zip->addFromString($filename, $fileContent)) {
                    $errors[] = 'There was an error adding the file '.$filename.' to the zip: '.$zipName;
                    Craft::log( '['. __METHOD__ .'] There was an error adding the file '.$filename.' to the zip: '.$zipName, LogLevel::Error, 'translations' );
                }
            }
        }
        // return $this->asJson(['file', $zip]);

        // Close zip
        $zip->close();

        if(count($errors) > 0)
        {
            return $errors;
        }

        return $this->asJson([
            'translatedFiles' => $zipDest
        ]);
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

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!Translations::$plugin->userRepository->userHasAccess('translations:orders:import')) {
            return;
        }

        //Track error and success messages.
        $message = "";

        $file = UploadedFile::getInstanceByName('zip-upload');

        //Get Order Data
        $orderId = Craft::$app->getRequest()->getParam('orderId');

        $this->order = Translations::$plugin->orderRepository->getOrderById($orderId);

        $totalWordCount = ($this->order->wordCount * count($this->order->getTargetSitesArray()));

        $total_files = (count($this->order->files) * count($this->order->getTargetSitesArray()));

        try
        {
            // Make sure a file was uploaded
            if ($file && $file->size > 0) {
                if (!in_array($file->extension, $this->_allowedTypes)) {
                    Craft::$app->getSession()->set('fileImportError', 1);
                    $this->showUserMessages("Invalid extension: The plugin only support [ZIP, XML, JSON, CSV] files.");
                }

                //If is a Zip File
                if ($file->extension === Constants::FILE_FORMAT_ZIP) {
                    //Unzip File ZipArchive
                    $zip = new \ZipArchive();

                    $assetPath = $file->saveAsTempFile();

                    if ($zip->open($assetPath)) {
                        $xmlPath = $assetPath.$orderId;

                        $zip->extractTo($xmlPath);

                        $fileName = preg_replace('/\\.[^.\\s]{3,4}$/', '', Assets::prepareAssetName($file->name));

                        $files = FileHelper::findFiles($assetPath.$orderId);

                        $assetIds = [];
                        $fileInfo = null;

                        foreach ($files as $key => $file) {
                            if (! is_bool(strpos($file, '__MACOSX'))) {
                                unlink($file);

                                continue;
                            }

                            $filename = Assets::prepareAssetName($file);

                            if (! $fileInfo) {
                                $fileInfo = pathinfo($filename);
                            }

                            $uploadVolumeId = ArrayHelper::getValue(Translations::getInstance()->getSettings(), 'uploadVolume');

                            $folder = Craft::$app->getAssets()->getRootFolderByVolumeId($uploadVolumeId);

                            $pathInfo = pathinfo($file);

                            $compatibleFilename = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.txt';

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

                                return $this->asErrorJson(Craft::t('app', 'Failed to save the asset:') . ' ' . implode(";\n", $errors));
                            }

                            $assetIds[] = $asset->id;
                        }

                        FileHelper::removeDirectory($assetPath.$orderId.'/'.$fileName);

                        $zip->close();

                        // Process files via job or directly based on order "size"
                        if ($totalWordCount > Constants::WORD_COUNT_LIMIT) {
                            $job = Craft::$app->queue->push(new ImportFiles([
                                'description' => 'Updating translation drafts',
                                'orderId' => $orderId,
                                'totalFiles' => $total_files,
                                'assets' => $assetIds,
                                'fileFormat' => $fileInfo['extension']
                            ]));

                            if ($job) {
                                $queueOrders = Craft::$app->getSession()->get('queueOrders');
                                $queueOrders[$job] = $orderId;
                                Craft::$app->getSession()->set('queueOrders', $queueOrders);
                                $params = [
                                    'id' => (int) $job,
                                    'notice' => 'Done updating translation drafts',
                                    'url' => Constants::URL_ORDER_DETAIL . $orderId
                                ];
                                Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
                            }
                        } else {
                            $fileSvc = new ImportFiles();
                            foreach ($assetIds as $key => $id) {
                                $a = Craft::$app->getAssets()->getAssetById($id);
                                $fileSvc->processFile($a, $this->order, $fileInfo['extension']);
                                Craft::$app->getElements()->deleteElement($a);
                            }
                        }
                        $this->showUserMessages("File uploaded successfully: $fileName", true);
                    } else {
                        Craft::$app->getSession()->set('fileImportError', 1);
                        $this->showUserMessages("Unable to unzip ". $file->name ." Operation not permitted or Decompression Failed ");
                    }
                } else {
                    $filename = Assets::prepareAssetName($file->name);

                    $uploadVolumeId = ArrayHelper::getValue(Translations::getInstance()->getSettings(), 'uploadVolume');

                    $folder = Craft::$app->getAssets()->getRootFolderByVolumeId($uploadVolumeId);

                    $compatibleFilename = $file->tempName . '.txt';

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

                        return $this->asErrorJson(Craft::t('app', 'Failed to save the asset:') . ' ' . implode(";\n", $errors));
                    }

                    // Process files via job or directly based on order "size"
                    if ($totalWordCount > Constants::WORD_COUNT_LIMIT) {
                        $job = Craft::$app->queue->push(new ImportFiles([
                            'description' => 'Updating translation drafts',
                            'orderId' => $orderId,
                            'totalFiles' => $total_files,
                            'assets' => [$asset->id],
                            'fileFormat' => $file->extension
                        ]));

                        if ($job) {
                            $queueOrders = Craft::$app->getSession()->get('queueOrders');
                            $queueOrders[$job] = $orderId;
                            Craft::$app->getSession()->set('queueOrders', $queueOrders);
                            $params = [
                                'id' => (int) $job,
                                'notice' => 'Done updating translation drafts',
                                'url' => Constants::URL_ORDER_DETAIL . $orderId
                            ];
                            Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
                        }
                        $this->showUserMessages("File uploaded successfully: {$file->name}", true);
                    } else {
                        $fileSvc = new ImportFiles();
                        $a = Craft::$app->getAssets()->getAssetById($asset->id);
                        $res = $fileSvc->processFile($a, $this->order, $file->extension);
                        Craft::$app->getElements()->deleteElement($a);

                        if($res !== false){
                            $this->showUserMessages("File uploaded successfully: {$file->name}", true);
                        } else {
                            Craft::$app->getSession()->set('fileImportError', 1);
                            $this->showUserMessages("File import error. Please check the order activity log for details.");
                        }
                    }
                }
            } else {
                Craft::$app->getSession()->set('fileImportError', 1);
                $this->showUserMessages("The file you are trying to import is empty.");
            }
        }
        catch (Exception $exception)
        {
            $this->returnErrorJson($exception->getMessage());
        }
    }

    public function actionApplyTranslationDraft()
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        
        // Get the fileId param
        $fileId = Craft::$app->getRequest()->getParam('fileId');
        if (!$fileId) {
            $this->showUserMessages("File not found.");
            return;
        }

        // Get the file
        $file = Translations::$plugin->fileRepository->getFileById($fileId);
        if (!$file) {
            $this->showUserMessages("File not found.");
            return;
        }

        // Get the element
        $element = Craft::$app->getElements()->getElementById($file->elementId, null, $file->sourceSite);
        if (!$element) {
            $this->showUserMessages("Entry not found for file.");
            return;
        }

        $order = Translations::$plugin->orderRepository->getOrderById($file->orderId);
        if (!$order) {
            $this->showUserMessages("Order not found.");
            return;
        }

        if ($element instanceof GlobalSet) {
            $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($file->draftId);
            
            $draft->name = $element->name;
            $draft->site = $file->targetSite;

            if ($draft) {
                $response = Translations::$plugin->globalSetDraftRepository->publishDraft($draft);
                $message = 'Draft applied for '. '"'. $draft->name .'"';
            } else {
                $response = false;
            }

            $uri = Translations::$plugin->urlGenerator->generateFileUrl($element, $file);
        } else if ($element instanceof Category) {
            $draft = Translations::$plugin->categoryDraftRepository->getDraftById($file->draftId);

            $draft->name = $element->title;
            $draft->site = $file->targetSite;

            if ($draft) {
                $response = Translations::$plugin->categoryDraftRepository->publishDraft($draft);
                $message = 'Draft applied for '. '"'. $draft->name .'"';
            } else {
                $response = false;
            }

            $uri = Translations::$plugin->urlGenerator->generateFileUrl($element, $file);
        } else {
            $draft = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);

            if ($draft) {
                $response = Translations::$plugin->draftRepository->applyTranslationDraft($file->id, $file, $draft);
                $message = 'Draft applied for '. '"'. $element->title .'"';
            } else {
                $response = false;
            }

            $uri = Translations::$plugin->urlGenerator->generateFileUrl($element, $file);
        }

        if ($response) {
            $order->logActivity(Translations::$plugin->translator->translate('app', $message));

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
        }

        $file->draftId = 0;
        $file->status = 'published';

        Translations::$plugin->fileRepository->saveFile($file);

        $files = $order->getFiles();
        $filesCount = count($files);
        $publishedFilesCount = 0;

        foreach ($files as $key => $f) {
            if ($f->status === 'published') {
                $publishedFilesCount++;
            }
        }

        if ($publishedFilesCount === $filesCount) {
            $order->status = 'published';
        }

        Translations::$plugin->orderRepository->saveOrder($order);

        if (
            $response &&
            $request->getAcceptsJson()
        ) {
            return $this->asJson([
                'success' => true,
            ]);
        }
    }

	/**
    * Show Flash Notifications and Errors to the translator
	*/
    public function showUserMessages($message, $isSuccess = false)
    {
    	if ($isSuccess)
    	{
			Craft::$app->session->setNotice(Craft::t('app', $message));
    	}
    	else
    	{
    		Craft::$app->session->setError(Craft::t('app', $message));
    	}	
    }

    /**
    * Report and Validate XML imported files
	* @return string
    */
    public function reportXmlErrors()
    {
    	$errors = array();
    	$libErros = libxml_get_errors();
    	
    	$msg = false;
    	if ($libErros && isset($libErros[0]))
    	{
    		$msg = $libErros[0]->code . ": " .$libErros[0]->message;
    	}

    	return $msg;
    }

    /**
     * @param $order
     * @return string
     */
    public function getZipName($order) {

        $title = str_replace(' ', '_', $order['title']);
        $title = preg_replace('/[^A-Za-z0-9\-_]/', '', $title);
        $len = 50;
        $title = (strlen($title) > $len) ? substr($title,0,$len) : $title;
        $zip_name =  $title.'_'.$order['id'];

        return $zip_name;
    }

}
