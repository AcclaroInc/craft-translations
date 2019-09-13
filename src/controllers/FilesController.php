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
use DateTime;
use ZipArchive;
use craft\helpers\Path;
use craft\web\Controller;
use craft\helpers\Assets;
use craft\models\Section;
use yii\web\HttpException;
use craft\web\UploadedFile;
use craft\helpers\FileHelper;
use craft\elements\GlobalSet;
use craft\base\VolumeInterface;
use craft\helpers\ElementHelper;
use yii\web\NotFoundHttpException;
use acclaro\translations\services\App;
use acclaro\translations\Translations;
use acclaro\translations\services\job\ImportFiles;
use acclaro\translations\services\repository\SiteRepository;

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
	private $_allowedTypes = array('zip', 'xml');

    public function actionCreateExportZip()
    {
        $params = Craft::$app->getRequest()->getRequiredBodyParam('params');

        $order = Translations::$plugin->orderRepository->getOrderById($params['orderId']);
        $files = Translations::$plugin->fileRepository->getFilesByOrderId($params['orderId'], null);

        $siteRepository = new SiteRepository(Craft::$app);
        $tempPath = Craft::$app->path->getTempPath();
        $errors = array();

        $orderAttributes = $order->getAttributes();

        //Filename Zip Folder
        $zipName = $orderAttributes['id'].'_'.$orderAttributes['sourceSite'];
        
        // Set destination zip
        $zipDest = Craft::$app->path->getTempPath().'/'.$zipName.'_'.time().'.zip';
        
        // Create zip
        $zip = new ZipArchive();


        // Open zip
        if ($zip->open($zipDest, $zip::CREATE) !== true)
        {
            $errors[] = 'Unable to create zip file: '.$zipDest;
            Craft::log('Unable to create zip file: '.$zipDest, LogLevel::Error);
            return false;
        }

        //Iterate over each file on this order
        if ($order->files)
        {
            foreach ($order->files as $file)
            {
                // skip failed files
                if ($file->status == 'failed') continue;

                $element = Craft::$app->elements->getElementById($file->elementId, null, $file->sourceSite);

                $targetSite = $file->targetSite;

                if ($element instanceof GlobalSet)
                {
                    $filename = $file->elementId . '-' . ElementHelper::createSlug($element->name).'-'.$targetSite.'.xml';
                } else
                {
                    $filename = $file->elementId . '-' . $element->slug.'-'.$targetSite.'.xml';
                }

                $path = $tempPath.$filename;

                
                // $fileContent = new \SimpleXMLElement($file->source);

                
                if (!$zip->addFromString($filename, $file->source))
                {
                    $errors[] = 'There was an error adding the file '.$filename.' to the zip: '.$zipName;
                    Craft::log('There was an error adding the file '.$filename.' to the zip: '.$zipName, LogLevel::Error);
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
        $this->requireAdmin();
        $this->requirePostRequest();

        //Track error and success messages.
        $message = "";

        // Upload the file and drop it in the temporary folder
        $file = UploadedFile::getInstanceByName('zip-upload');

        //Get Order Data
        $orderId = Craft::$app->getRequest()->getParam('orderId');

        $this->order = Translations::$plugin->orderRepository->getOrderById($orderId);

        $total_files = (count($this->order->files) * count($this->order->getTargetSitesArray()));

        try
        {
            // Make sure a file was uploaded
            if ($file && $file->size > 0) {
                if (!in_array($file->extension, $this->_allowedTypes)) {
                    $this->showUserMessages("Invalid extention: The plugin only support [ZIP, XML] files.");
                }

                $fileName = Assets::prepareAssetName($file->name, true, true);
                $folderPath = Craft::$app->path->getTempAssetUploadsPath().'/';
                FileHelper::clearDirectory($folderPath);

                //If is a Zip File
                if ($file->extension === 'zip') {
                    //Unzip File ZipArchive
                    $zip = new \ZipArchive();
                    if (move_uploaded_file($file->tempName, $folderPath.$fileName)) {

                        if ($zip->open($folderPath.$fileName)) {
                            $xmlPath = $folderPath.$orderId;

                            $zip->extractTo($xmlPath);

                            $fileName = preg_replace('/\\.[^.\\s]{3,4}$/', '', $fileName);

                            $files = FileHelper::findFiles($folderPath.$orderId);

                            foreach ($files as $key => $file) {
                                rename($file, $folderPath.$orderId.'/'.pathinfo($file)['basename']);
                            }

                            FileHelper::removeDirectory($folderPath.$orderId.'/'.$fileName);

                            $zip->close();

                            $job = Craft::$app->queue->push(new ImportFiles([
                                'description' => 'Updating translation drafts',
                                'orderId' => $orderId,
                                'totalFiles' => $total_files,
                                'xmlPath' => $xmlPath,
                            ]));

                            if ($job) {
                                $params = [
                                    'id' => (int) $job,
                                    'notice' => 'Done updating translation drafts',
                                    'url' => 'translations/orders/detail/'. $orderId
                                ];
                                Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
                            }
                            // $this->redirect('translations/orders/detail/'. $orderId, 302, true);
                            $this->showUserMessages("File uploaded successfully: $fileName", true);
                        } else {
                            $this->showUserMessages("Unable to unzip ". $file->name ." Operation not permitted or Decompression Failed ");
                        }
                    } else {
                        $this->showUserMessages("Unable to upload file: $fileName");
                    }
                } elseif ($file->extension === 'xml') {
                    $xmlPath = $folderPath.$orderId;

                    mkdir($xmlPath, 0777, true);

                    //Upload File
                    if( move_uploaded_file($file->tempName, $xmlPath.'/'.$fileName)) {

                        // This generally executes too fast for page to refresh
                        $job = Craft::$app->queue->push(new ImportFiles([
                            'description' => 'Updating translation drafts',
                            'orderId' => $orderId,
                            'totalFiles' => $total_files,
                            'xmlPath' => $xmlPath,
                        ]));
                        
                        if ($job) {
                            $params = [
                                'id' => (int) $job,
                                'notice' => 'Done updating translation drafts',
                                'url' => 'translations/orders/detail/'. $orderId
                            ];
                            Craft::$app->getView()->registerJs('$(function(){ Craft.Translations.trackJobProgressById(true, false, '. json_encode($params) .'); });');
                        }

                        $this->showUserMessages("File uploaded successfully: $fileName", true);
                    } else {
                        $this->showUserMessages("Unable to upload file: $fileName");
                    }
                } else {
                    $this->showUserMessages("Invalid extention: The plugin only support [ZIP, XML] files.");
                }
            } else {
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
        
        // Get the file
        $fileId = Craft::$app->getRequest()->getParam('fileId');

        $response = Translations::$plugin->draftRepository->applyTranslationDraft($fileId);

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
    * Show Flash Notificaitons and Erros to the trasnlator
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


}
