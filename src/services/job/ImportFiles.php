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
use craft\elements\Asset;
use craft\queue\BaseJob;
use acclaro\translations\Translations;

class ImportFiles extends BaseJob
{

    public $assets;
    public $orderId;
    public $order;
    public $totalFiles;

    public function execute($queue)
    {
        $this->order = Translations::$plugin->orderRepository->getOrderById($this->orderId);

        $currentFile = 0;
        foreach ($this->assets as $assetId) {
            $asset = Craft::$app->getAssets()->getAssetById($assetId);

            $this->setProgress($queue, $currentFile++ / $this->totalFiles);
            //Process XML Files
            $this->processFile($asset);

            Craft::$app->getElements()->deleteElement($asset);
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
    public function processFile( Asset $asset, $order = null )
    {
        if (empty($this->order)) {
            $this->order = $order;
        }

        // DEV: Since some Asset Volumes could disallow XML files, we're
        // working with files using a 'txt' extension added when the files
        // were uploaded. Could alternatively just validate that the
        // selected volume in Settings has the xml permission before saving.
        if ($asset->getExtension() === 'txt')
        {
            $xml_content = $asset->getContents();

            // check if the file is empty
            if (empty($xml_content)) {
                $this->order->logActivity(Translations::$plugin->translator->translate('app', $asset->getFilename()." file you are trying to import is empty."));
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
                        $this->order->logActivity(Translations::$plugin->translator->translate('app', "We found errors on $asset->getFilename() : "  . $errors));
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
                {   //Get File
                    $draft_file = Translations::$plugin->fileRepository->getFileByDraftId($draftId, $file->elementId);
                }
            }

            //Validate If the draft was found
            if (is_null($draft_file))
            {
                $this->order->logActivity(Translations::$plugin->translator->translate('app', $asset->getFilename() ." does not match any known entries."));
                Translations::$plugin->orderRepository->saveOrder($this->order);
                return;
            }

            if($this->checkResname($dom, $draft_file)){
                $this->order->logActivity(Translations::$plugin->translator->translate('app', "File failed to import due to the resname mismatches in the XML."));
                Translations::$plugin->orderRepository->saveOrder($this->order);
                $draft_file->status = 'failed';
                Translations::$plugin->fileRepository->saveFile($draft_file);
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

            $fileUpdated = $isDraftSave = true;

            try {
                $isDraftSave = $translationService->updateIOFile($this->order, $draft_file, $xml_content, $asset->getFilename());
            } catch(Exception $e) {
                $fileUpdated = false;
                $this->order->logActivity(Translations::$plugin->translator->translate('app', 'Could not update '. $asset->getFilename(). ' Error: ' .$e->getMessage()));
                Translations::$plugin->orderRepository->saveOrder($this->order);
            }

            if (!$isDraftSave) {
                $draft_file->status = 'failed';
            } else if ($fileUpdated) {
                $draft_file->status = 'complete';
            } else {
                $draft_file->status = 'in progress';
            }

            //If Successfully saved
            $success = Translations::$plugin->fileRepository->saveFile($draft_file);

            if ($success)
            {
                if ($isDraftSave) {
                    $this->order->logActivity(
                        sprintf(Translations::$plugin->translator->translate('app', "File %s imported successfully!"), $asset->getFilename())
                    );

                    //Verify All files on this order were successfully imported.
                    if ($this->isOrderCompleted())
                    {
                        //Save Order with status complete
                        $translationService->updateOrder($this->order);
                    }
                }

                Translations::$plugin->orderRepository->saveOrder($this->order);
            }
        }
        else
        {
            //Invalid
            $this->order->logActivity(Translations::$plugin->translator->translate('app', "File {$asset->getFilename()} is invalid, please try again with a valid xml file."));
            Translations::$plugin->orderRepository->saveOrder($this->order);
        }
    }

    /**
     * @param $dom
     * @param $draft_file
     * @return array|void
     * @throws Exception
     */
    public function checkResname($dom, $draft_file) {

        $targetFields = [];
        $fileContents = $dom->getElementsByTagName('content');
        foreach ($fileContents as $node)
        {
            $targetFields[$node->getAttribute('resname')] = $node->getAttribute('resname');
        }

        try
        {
            if (!$dom->loadXML( $draft_file->source ))
            {
                $errors = $this->reportXmlErrors();
                if($errors)
                {
                    $this->order->logActivity(Translations::$plugin->translator->translate('app', "We found errors on source xml : "  . $errors));
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

        $sourceFields = [];
        $sourceContents = $dom->getElementsByTagName('content');
        foreach ($sourceContents as $node)
        {
            $sourceFields[$node->getAttribute('resname')] = $node->getAttribute('resname');
        }

        return array_diff($targetFields, $sourceFields);
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
