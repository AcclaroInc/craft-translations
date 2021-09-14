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

namespace acclaro\translations\services\job;

use acclaro\translations\Constants;
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
    public $fileFormat;

    public function execute($queue)
    {
        $this->order = Translations::$plugin->orderRepository->getOrderById($this->orderId);

        $currentFile = 0;
        foreach ($this->assets as $assetId) {
            $asset = Craft::$app->getAssets()->getAssetById($assetId);

            $this->setProgress($queue, $currentFile++ / $this->totalFiles);
            //Process Files
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
    public function processFile( Asset $asset, $order = null , $fileFormat = null)
    {
        if (empty($this->order)) {
            $this->order = $order;
        }

        if (! $this->fileFormat) {
            $this->fileFormat = $fileFormat;
        }

        // DEV: Since some Asset Volumes could disallow XML files, we're
        // working with files using a 'txt' extension added when the files
        // were uploaded. Could alternatively just validate that the
        // selected volume in Settings has the xml permission before saving.
        if ($asset->getExtension() === Constants::FILE_FORMAT_TXT) {
            $file_content = $asset->getContents();

            // check if the file is empty
            if (empty($file_content)) {
                $this->order->logActivity(Translations::$plugin->translator->translate('app', $asset->getFilename()." file you are trying to import is empty."));
                Translations::$plugin->orderRepository->saveOrder($this->order);
                return false;
            }

            if ($this->fileFormat === Constants::DEFAULT_FILE_EXPORT_FORMAT) {
                return $this->processJsonFile($asset, $file_content);
            } elseif ($this->fileFormat == Constants::FILE_FORMAT_CSV) {
                return $this->processCsvFile($asset, $file_content);
            } else {
                return $this->processXmlFile($asset, $file_content);
            }
        } else {
            //Invalid
            $this->order->logActivity(Translations::$plugin->translator->translate('app', "File {$asset->getFilename()} is invalid, please try again with a valid xml/json/csv file."));
            Translations::$plugin->orderRepository->saveOrder($this->order);
        }
    }

    /**
     * Process A JSON File
     *
     * @param [type] $asset File Asset
     * @param [type] $file_content uploaded file content
     * @return bool
     */
    public function processJsonFile($asset, $file_content)
    {
        $file_content = json_decode($file_content, true);

        if (! is_array($file_content)) {
            $this->order->logActivity(
                Translations::$plugin->translator->translate(
                    'app', $asset->getFilename()." file you are trying to import has invalid content."
                )
            );
            Translations::$plugin->orderRepository->saveOrder($this->order);
            return false;
        }

        //Source & Target Sites
        $sourceSite = (string)$file_content['source-site'];
        $targetSite = (string)$file_content['target-site'];

        //Source & Target Languages
        $sourceLanguage = (string)$file_content['source-language'];
        $targetLanguage = (string)$file_content['target-language'];

        $elementId = $file_content['elementId'];

        $order_file = null;

        foreach ($this->order->files as $file)
        {
            if (
                $elementId == $file->elementId &&
                $sourceSite == $file->sourceSite &&
                $targetSite == $file->targetSite
            ) {
                //Get File
                $order_file = Translations::$plugin->fileRepository->getFileById($file->id);
            }
        }

        //Validate If the file was found
        if (is_null($order_file))
        {
            $this->order->logActivity(
                Translations::$plugin->translator->translate(
                    'app', $asset->getFilename() ." does not match any known entries."
                )
            );
            Translations::$plugin->orderRepository->saveOrder($this->order);
            return;
        }

        if ($this->verifyJsonKeysMismatch($file_content, $order_file->source)) {
            $this->order->logActivity(Translations::$plugin->translator->translate(
                'app', "Failed to import due to the keys mismatches in the file."
            ));
            Translations::$plugin->orderRepository->saveOrder($this->order);
            $order_file->status = Constants::FILE_STATUS_FAILED;
            Translations::$plugin->fileRepository->saveFile($order_file);
            return false;
        }

        if ($order_file->status === Constants::FILE_STATUS_PUBLISHED)
        {
            $this->order->logActivity(
                Translations::$plugin->translator->translate('app', "This entry was already published.")
            );
            Translations::$plugin->orderRepository->saveOrder($this->order);
            return;
        }

        $translation_service = $this->order->translator->service;
        if ($translation_service === Translations::ACCLARO) {
            $translation_service = Translations::EXPORT_IMPORT;
        }

        //Translation Service
        $translationService = Translations::$plugin->translatorFactory
            ->makeTranslationService($translation_service, $this->order->translator->getSettings());


        $order_file->target = $file_content;
        $order_file->status = Constants::FILE_STATUS_COMPLETE;
        $order_file->dateDelivered = new \DateTime();

        // If Successfully saved
        $success = Translations::$plugin->fileRepository->saveFile($order_file);

        if ($success)
        {
            $this->order->logActivity(
                sprintf(Translations::$plugin->translator->translate(
                    'app', "File %s imported successfully!"
                ), $asset->getFilename())
            );

            //Verify All files on this order were successfully imported.
            if ($this->isOrderCompleted())
            {
                //Save Order with status complete
                $translationService->updateOrder($this->order);
            }

            Translations::$plugin->orderRepository->saveOrder($this->order);

            return true;
        } else {
            return false;
        }
    }

    /**
     * Process an XML File
     *
     * @param [type] $asset Asset of uploaded file
     * @param [type] $xml_content Content of uploaded file
     * @return bool
     */
    public function processXmlFile($asset, $xml_content)
    {
        $dom = new \DOMDocument('1.0', 'utf-8');

        try {
            //Turn LibXml Internal Errors Reporting On!
            libxml_use_internal_errors(true);
            if (!$dom->loadXML( $xml_content ))
            {
                $errors = $this->reportXmlErrors();
                if($errors)
                {
                    $this->order->logActivity(Translations::$plugin->translator->translate('app', "We found errors on $asset->getFilename() : "  . $errors));
                    Translations::$plugin->orderRepository->saveOrder($this->order);
                    return false;
                }
            }
        } catch(Exception $e) {
            $this->order->logActivity(Translations::$plugin->translator->translate('app', $e->getMessage()));
            Translations::$plugin->orderRepository->saveOrder($this->order);
        }

        // Source & Target Sites
        $sites = $dom->getElementsByTagName('sites');
        $sites = isset($sites[0]) ? $sites[0] : $sites;
        $sourceSite = (string)$sites->getAttribute('source-site');
        $targetSite = (string)$sites->getAttribute('target-site');

        // Source & Target Languages
        $langs = $dom->getElementsByTagName('langs');
        $langs = isset($langs[0]) ? $langs[0] : $langs;
        $sourceLanguage = (string)$langs->getAttribute('source-language');
        $targetLanguage = (string)$langs->getAttribute('target-language');
        
        // Meta ElementId
        $element = $dom->getElementsByTagName('meta');
        $element = isset($element[0]) ? $element[0] : $element;
        $elementId = (string)$element->getAttribute('elementId');

        foreach ($this->order->files as $file)
        {
            if (
                $elementId == $file->elementId &&
                $sourceSite == $file->sourceSite &&
                $targetSite == $file->targetSite
            ) {
                //Get File
                $draft_file = Translations::$plugin->fileRepository->getFileById($file->id);
            }
        }

        //Validate If the file was found
        if (is_null($draft_file)) {
            $this->order->logActivity(
                Translations::$plugin->translator->translate(
                    'app', $asset->getFilename() ." does not match any known entries."
                )
            );
            Translations::$plugin->orderRepository->saveOrder($this->order);
            return;
        }

        if ($this->matchXmlKeys($dom, $draft_file)) {
            $this->order->logActivity(Translations::$plugin->translator->translate(
                'app', "File failed to import due to the resname mismatches in the XML."
            ));
            Translations::$plugin->orderRepository->saveOrder($this->order);
            $draft_file->status = Constants::FILE_STATUS_FAILED;
            Translations::$plugin->fileRepository->saveFile($draft_file);
            return false;
        }

        // Don't process published files
        if ($draft_file->status === Constants::FILE_STATUS_PUBLISHED) {
            $this->order->logActivity(
                Translations::$plugin->translator->translate('app', "This entry was already published.")
            );
            Translations::$plugin->orderRepository->saveOrder($this->order);
            return;
        }

        $translation_service = $this->order->translator->service;
        if ($translation_service === Translations::ACCLARO) {
            $translation_service = Translations::EXPORT_IMPORT;
        }

        //Translation Service
        $translationService = Translations::$plugin->translatorFactory
            ->makeTranslationService($translation_service, $this->order->translator->getSettings());


        $draft_file->status = Constants::FILE_STATUS_COMPLETE;
        $draft_file->target = $xml_content;

        //If Successfully saved
        $success = Translations::$plugin->fileRepository->saveFile($draft_file);

        if ($success) {
            $this->order->logActivity(
                sprintf(Translations::$plugin->translator->translate(
                    'app', "File %s imported successfully!"
                ), $asset->getFilename())
            );

            //Verify All files on this order were successfully imported.
            if ($this->isOrderCompleted())
            {
                //Save Order with status complete
                $translationService->updateOrder($this->order);
            }

            Translations::$plugin->orderRepository->saveOrder($this->order);

            return $success;
        } else {
            return false;
        }
    }

    /**
     * Process Csv Files
     *
     * @param [type] $asset
     * @param [type] $file_content
     * @return bool
     */
    public function processCsvFile($asset, $file_contents)
    {
        $file_content = $this->csvToJson($asset, $file_contents);

        if (! $file_content) {
            return false;
        }
        
        //Source & Target Sites
        $sourceSite = (string)$file_content['source-site'];
        $targetSite = (string)$file_content['target-site'];

        //Source & Target Languages
        $sourceLanguage = (string)$file_content['source-language'];
        $targetLanguage = (string)$file_content['target-language'];

        $elementId = $file_content['elementId'];

        $order_file = null;

        foreach ($this->order->files as $file)
        {
            if (
                $elementId == $file->elementId &&
                $sourceSite == $file->sourceSite &&
                $targetSite == $file->targetSite
            ) {
                //Get File
                $order_file = Translations::$plugin->fileRepository->getFileById($file->id);
            }
        }

        //Validate If the file was found
        if (is_null($order_file))
        {
            $this->order->logActivity(
                Translations::$plugin->translator->translate(
                    'app', $asset->getFilename() ." does not match any known entries."
                )
            );
            Translations::$plugin->orderRepository->saveOrder($this->order);
            return false;
        }

        if ($this->verifyJsonKeysMismatch($file_content, $order_file->source)) {
            $this->order->logActivity(Translations::$plugin->translator->translate(
                'app', "Failed to import due to the keys mismatches in the file."
            ));
            Translations::$plugin->orderRepository->saveOrder($this->order);
            $order_file->status = Constants::FILE_STATUS_FAILED;
            Translations::$plugin->fileRepository->saveFile($order_file);
            return false;
        }

        if ($order_file->status === Constants::FILE_STATUS_PUBLISHED)
        {
            $this->order->logActivity(
                Translations::$plugin->translator->translate('app', "This entry was already published.")
            );
            Translations::$plugin->orderRepository->saveOrder($this->order);
            return;
        }

        $translation_service = $this->order->translator->service;
        if ($translation_service === Translations::ACCLARO) {
            $translation_service = Translations::EXPORT_IMPORT;
        }

        //Translation Service
        $translationService = Translations::$plugin->translatorFactory
            ->makeTranslationService($translation_service, $this->order->translator->getSettings());


        $order_file->target = $file_content;
        $order_file->status = Constants::FILE_STATUS_COMPLETE;
        $order_file->dateDelivered = new \DateTime();

        // If Successfully saved
        $success = Translations::$plugin->fileRepository->saveFile($order_file);

        if ($success)
        {
            $this->order->logActivity(
                sprintf(Translations::$plugin->translator->translate(
                    'app', "File %s imported successfully!"
                ), $asset->getFilename())
            );

            //Verify All files on this order were successfully imported.
            if ($this->isOrderCompleted())
            {
                //Save Order with status complete
                $translationService->updateOrder($this->order);
            }

            Translations::$plugin->orderRepository->saveOrder($this->order);

            return true;
        } else {
            return false;
        }
    }

    // ######################### Private Functions ###################################

    /**
     * @param $dom
     * @param $draft_file
     * @return array|void
     * @throws Exception
     */
    private function matchXmlKeys($dom, $file)
    {
        $targetFields = [];
        $fileContents = $dom->getElementsByTagName('content');
        foreach ($fileContents as $node) {
            $targetFields[$node->getAttribute('resname')] = $node->getAttribute('resname');
        }

        $element = Craft::$app->elements->getElementById($file->elementId, null, $file->sourceSite);

        $xmlSource = Translations::$plugin->elementToFileConverter->convert(
            $element,
            Constants::FILE_FORMAT_XML,
            [
                'sourceSite'    => $file->sourceSite,
                'targetSite'    => $file->targetSite,
                'wordCount'     => $file->wordCount,
            ]
        );

        try {
            if (!$dom->loadXML( $xmlSource )) {
                $errors = $this->reportXmlErrors();
                if ($errors) {
                    $this->order->logActivity(Translations::$plugin->translator->translate('app', "We found errors on source xml : "  . $errors));
                    Translations::$plugin->orderRepository->saveOrder($this->order);
                    return;
                }
            }
        } catch(Exception $e) {
            $this->order->logActivity(Translations::$plugin->translator->translate('app', $e->getMessage()));
            Translations::$plugin->orderRepository->saveOrder($this->order);
        }

        $sourceFields = [];
        $sourceContents = $dom->getElementsByTagName('content');
        foreach ($sourceContents as $node) {
            $sourceFields[$node->getAttribute('resname')] = $node->getAttribute('resname');
        }

        return array_diff($targetFields, $sourceFields);
    }

    /**
     * Verify if the all entries per order have been completed
     * @return boolean
     */
    private function isOrderCompleted()
    {
        $files = Translations::$plugin->fileRepository->getFilesByOrderId($this->order->id);
        foreach ($files as $file)
        {
            if ($file->status !== Constants::FILE_STATUS_COMPLETE)
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
    private function reportXmlErrors()
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
     * Converts given CSV file contents to json format
     *
     * @param [type] $asset
     * @param [string] $file_content
     * @return array
     */
    private function csvToJson($asset, $file_content)
    {
        $jsonData = [];
        $contentArray = explode("\n", $file_content);

        if (count($contentArray) != 2) {
            $this->order->logActivity(
                Translations::$plugin->translator->translate(
                    'app', $asset->getFilename()." file you are trying to import has invalid content."
                )
            );
            Translations::$plugin->orderRepository->saveOrder($this->order);
            return false;
        }

        $keys = explode(",", $contentArray[0]);
        $values = explode(",", $contentArray[1]);

        if (count($keys) != count($values)) {
            $this->order->logActivity(
                Translations::$plugin->translator->translate(
                    'app', $asset->getFilename()." file you are trying to import has header and value mismatch."
                )
            );
            Translations::$plugin->orderRepository->saveOrder($this->order);
            return false;
        }

        $metaKeys = [
            'source-site',
            'target-site',
            'source-language',
            'target-language',
            'elementId',
            'wordCount',
        ];

        foreach ($keys as $i => $key) {
            $key = trim($key, '"');
            $value = trim($values[$i], '"');

            if (in_array($key, $metaKeys)) {
                $jsonData[$key] = $value;
                continue;
            }
            $jsonData['content'][$key] = $value;
        }
        return $jsonData;
    }

    /**
     * Matches the keys from source and target file data
     *
     * @param [array] $targetContent
     * @param [string] $sourceContent
     * @return bool
     */
    private function verifyJsonKeysMismatch($targetContent, $sourceContent)
    {
        $data = ['target' => [], 'source' => []];

        $sourceContent = json_decode($sourceContent, true);

        if ($sourceContent['content'] ?? null) {
            foreach ($targetContent['content'] as $key => $value) {
                $data['target'][$key] = $key;
            }
    
            foreach ($sourceContent['content'] as $key => $value) {
                $data['source'][$key] = $key;
            }
        }

        return array_diff($data['target'], $data['source']);
    }
}
