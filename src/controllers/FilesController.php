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
use yii\web\HttpException;
use craft\web\UploadedFile;
use craft\helpers\FileHelper;
use craft\elements\GlobalSet;
use craft\base\VolumeInterface;
use craft\helpers\ElementHelper;
use yii\web\NotFoundHttpException;
use acclaro\translations\services\App;
use acclaro\translations\Translations;
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
                $element = Craft::$app->elements->getElementById($file->elementId);

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

		$count = 0;
        $total_files = count($this->order->files);

		try
		{	
			// Make sure a file was uploaded
			if ($file && $file->size > 0)
			{
				if (!in_array($file->extension, $this->_allowedTypes))
				{
					$this->showUserMessages("Invalid extention: The plugin only support [ZIP, XML] files.");
				}

				$fileName = Assets::prepareAssetName($file->name, true, true);
				$folderPath = Craft::$app->path->getTempAssetUploadsPath().'/';
                FileHelper::clearDirectory($folderPath);
 				
				//If is a Zip File
				if ($file->extension === 'zip')
				{
					//Unzip File ZipArchive
					$zip = new \ZipArchive();
					if (move_uploaded_file($file->tempName, $folderPath.$fileName))
					{
						if ($zip->open($folderPath.$fileName))
						{
							$xmlPath = $folderPath.$orderId;
							
							$zip->extractTo($xmlPath);
							
							$fileName = preg_replace('/\\.[^.\\s]{3,4}$/', '', $fileName);
							
							$files = FileHelper::findFiles($folderPath.$orderId);

							foreach ($files as $key => $file) {
								rename($file, $folderPath.$orderId.'/'.pathinfo($file)['basename']);
							}

							FileHelper::removeDirectory($folderPath.$orderId.'/'.$fileName);
							
							$zip->close();

							$dir = new \DirectoryIterator($xmlPath);
							
							foreach ($dir as $xml) 
							{
                                //Process XML Files
							    $this->processFile($xml, $xmlPath);								
						    } 
					    }
					    else
					    {
					    	$this->showUserMessages("Unable to unzip ". $file->name ." Operation not permitted or Decompression Failed ");
					    }
					}
					else
					{
						$this->showUserMessages("Unable to upload file: $fileName");
					}
				}
				elseif ($file->extension === 'xml')
				{
					$xmlPath = $folderPath.$orderId;

					mkdir($xmlPath, 0777, true);
					
					//Upload File
					if( move_uploaded_file($file->tempName, $xmlPath.'/'.$fileName))
					{
						//Process XML Files
						$dir = new \DirectoryIterator($xmlPath);
						foreach ($dir as $xml) 
						{
							$this->processFile($xml, $xmlPath);	
						}
					}
					else
						$this->showUserMessages("Unable to upload file: $fileName");
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

    /**
    * Process each file entry per orden
    * Validates 
	*/
    public function processFile( $xml, $path )
    {
    	//Ignore __MAXOSX & ../ ./ Dir
		if ($xml->getFileName() !== '__MACOSX' && !$xml->isDot())
		{
			if ($xml->getExtension() === 'xml' && $xml->isReadable())
			{
                 $translated_file = $path . '/' . $xml;
	     		
	     		$xml_content = file_get_contents( $translated_file );

	     		// check if the file is empty
                if (empty($xml_content)) {
                    $this->showUserMessages($xml." file you are trying to import is empty.");
                    return false;
                }

	     		$dom = new \DOMDocument('1.0', 'utf-8');

	     		try
	     		{
	     			//Turn LibXml Internal Errors Reporting On!
	     			libxml_use_internal_errors(true);
	     			if (!$dom->loadXML( $xml_content )) 
	     			{
	     				$errors = $this->reportXmlErrors();
	     				if($errors)
	     				{
	     					$this->showUserMessages("We found errors on $xml : "  . $errors);
	     					return;
	     				}
	     			}
	     		}
	     		catch(Exception $e)
	     		{ 
	     			$this->showUserMessages(Translations::$plugin->translator->translate('app', $e->getMessage()));
                 }

	     		//Get DraftId & Lang Nodes From Document
	 			$draftId = false;
	 			$draftElements = $dom->getElementsByTagName('meta');
				
	 			//Source & Target Sites
	 			$sites = $dom->getElementsByTagName('sites');
	 			$sites = isset($sites[0]) ? $sites[0] : $sites;
	 			$sourceSite = (string)$sites->getAttribute('source-site');
				$targetSite = (string)$sites->getAttribute('target-site');

				//Source & Target Languages
	 			$langs = $dom->getElementsByTagName('langs');
	 			$langs = isset($langs[0]) ? $langs[0] : $langs;
	 			$sourceLanguage = (string)$langs->getAttribute('source-language');
	 			$targetLanguage = (string)$langs->getAttribute('target-language');
	 			
                 //Iterate Over Draft XML Nodes
	 			foreach ($draftElements as $node) 
	 			{ 	
                    $name = (string) $node->getAttribute('name');
                    $value = (string) $node->getAttribute('content');

                    if ($name === 'draftId')
                    {
                        $draftId = (int) $value;
                    }
				} 

                $draft_file = null;

				foreach ($this->order->files as $file) 
				{
	                if ($draftId === $file->draftId)
                    {	//Get File
                        $draft_file = Translations::$plugin->fileRepository->getFileByDraftId($draftId);
	                }
                }

	            //Validate If the draft was found
	            if (is_null($draft_file))
		    	{
		    		$this->showUserMessages("The file you are trying to import does not contain a match for this entry.");
		    		return;	
		    	}

	            // Don't process published files
		        if ($draft_file->status === 'published') 
		        {
		            $this->showUserMessages("This entry was already published.");
		    		return;	
		        }

		        //Translation Service
		    	$translationService = Translations::$plugin->translationFactory->makeTranslationService($this->order->translator->service, $this->order->translator->getSettings());

		        $translationService->updateIOFile(Translations::$plugin->jobFactory, $this->order, $draft_file, $xml_content);

		        $draft_file->status = 'complete';

		        //If Successfully saved
		        $success = Translations::$plugin->fileRepository->saveFile($draft_file);

		        if ($success)
		        {
		        	$this->showUserMessages("File $xml imported successfully!", true);
		        	
		        	$this->order->logActivity(
		                sprintf(Translations::$plugin->translator->translate('app', "File %s imported successfully!"), $xml)
		            );

		        	//Verify All files on this order were successfully imported.
		            if ($this->isOrderCompleted())
		        	{
		        		//Save Order with status complete
		        		 $translationService->updateOrder(Translations::$plugin->jobFactory, $this->order);
			    	}

		        	Translations::$plugin->orderRepository->saveOrder($this->order);
		        }
			}
			else
			{
				//Invalid
				$this->showUserMessages("File $xml is invalid, please try again with a valid xml file.");
			}		
		}
    }

    /**
	* Verify if the all entries per orden have been completed
    * @return boolean
	*/
    public function isOrderCompleted()
    {
    	$files = Translations::$plugin->fileRepository->getFilesByOrderId($this->order->id);
    	foreach ($files as $file)
    	{
    		if ($file->status !== 'complete')
			{
				return false;
			}
    	} 
    	return true;
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
