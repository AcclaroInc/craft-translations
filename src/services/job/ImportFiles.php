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
use craft\base\Element;

use craft\queue\BaseJob;
use acclaro\translations\Translations;

class ImportFiles extends BaseJob
{

    public $xmlPath;
    public $orderId;
    public $order;
    public $totalFiles;

    public function execute($queue)
    {
        $this->order = Translations::$plugin->orderRepository->getOrderById($this->orderId);
        $dir = new \DirectoryIterator($this->xmlPath);

        $currentFile = 0;
        foreach ($dir as $xml)
        {
            $this->setProgress($queue, $currentFile++ / $this->totalFiles);
            //Process XML Files
            $this->processFile($xml, $this->xmlPath);
        }
    }

    protected function defaultDescription()
    {
        return 'Updating Translation Drafts';
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
                    $this->order->logActivity(Translations::$plugin->translator->translate('app', $xml." file you are trying to import is empty."));
                    Translations::$plugin->orderRepository->saveOrder($this->order);
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
                            $this->order->logActivity(Translations::$plugin->translator->translate('app', "We found errors on $xml : "  . $errors));
                            Translations::$plugin->orderRepository->saveOrder($this->order);
                            return;
                        }
                    }
                }
                catch(Exception $e)
                {
                    $this->order->logActivity(Translations::$plugin->translator->translate('app', $e->getMessage()));
                    Translations::$plugin->orderRepository->saveOrder($this->order);
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
                    $this->order->logActivity(Translations::$plugin->translator->translate('app', $xml ." does not match any known entries."));
                    Translations::$plugin->orderRepository->saveOrder($this->order);
                    return;
                }

                // Don't process published files
                if ($draft_file->status === 'published')
                {
                    $this->order->logActivity(Translations::$plugin->translator->translate('app', "This entry was already published."));
                    Translations::$plugin->orderRepository->saveOrder($this->order);
                    return;
                }

                //Translation Service
                $translationService = Translations::$plugin->translatorFactory->makeTranslationService($this->order->translator->service, $this->order->translator->getSettings());

                $fileUpdated = true;

                try {
                    $translationService->updateIOFile($this->order, $draft_file, $xml_content);
                } catch(Exception $e) {
                    $fileUpdated = false;
                    $this->order->logActivity(Translations::$plugin->translator->translate('app', 'Could not update '. $xml. ' Error: ' .$e->getMessage()));
                    Translations::$plugin->orderRepository->saveOrder($this->order);
                }

                if ($fileUpdated) {
                    $draft_file->status = 'complete';
                } else {
                    $draft_file->status = 'in progress';
                }

                //If Successfully saved
                $success = Translations::$plugin->fileRepository->saveFile($draft_file);

                if ($success)
                {
                    $this->order->logActivity(
                        sprintf(Translations::$plugin->translator->translate('app', "File %s imported successfully!"), $xml)
                    );

                    //Verify All files on this order were successfully imported.
                    if ($this->isOrderCompleted())
                    {
                        //Save Order with status complete
                        $translationService->updateOrder($this->order);
                    }

                    Translations::$plugin->orderRepository->saveOrder($this->order);
                }
            }
            else
            {
                //Invalid
                $this->order->logActivity(Translations::$plugin->translator->translate('app', "File $xml is invalid, please try again with a valid xml file."));
                Translations::$plugin->orderRepository->saveOrder($this->order);
            }
        }
    }

    /**
     * Verify if the all entries per order have been completed
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