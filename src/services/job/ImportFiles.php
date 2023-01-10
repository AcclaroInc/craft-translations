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

use Craft;
use Exception;
use craft\queue\BaseJob;
use craft\elements\Asset;
use acclaro\translations\Constants;
use acclaro\translations\Translations;

class ImportFiles extends BaseJob
{
    public $assets;
    public $orderId;
    /** @var \acclaro\translations\elements\Order $order */
    public $order;
    public $totalFiles;
    public $fileFormat;
    public $discardElements;
    private $_allowAppliedChanges = null;

    /**
     * Map of assetId v/s file name
     *
     * @var array[assetId => uploaded file's original name]
     */
    public $fileNames;

    public function execute($queue): void
    {
        $this->order = Translations::$plugin->orderRepository->getOrderById($this->orderId);

        $currentFile = 0;
        foreach ($this->assets as $assetId) {
            $asset = Craft::$app->getAssets()->getAssetById($assetId);

            $this->setProgress($queue, $currentFile++ / $this->totalFiles);
            //Process Files
            $this->processFile($asset, $this->order, $this->fileFormat, $this->fileNames, $this->discardElements);

            Craft::$app->getElements()->deleteElement($asset);
        }
    }

    protected function defaultDescription(): ?string
    {
        return Constants::JOB_IMPORTING_FILES;
    }

    /**
     * Process each file entry per order
     * Validates
     */
    public function processFile(Asset $asset, $order = null , $fileFormat = null, $fileNames = null, $discardElements = [])
    {
        $this->order = $this->order ?: $order;

        $this->fileNames = $this->fileNames ?: $fileNames;

        $this->discardElements = $this->discardElements ?: $discardElements;

        $this->fileFormat = $this->fileFormat ?: $fileFormat;

        // DEV: Since some Asset Volumes could disallow XML files, we're
        // working with files using a 'txt' extension added when the files
        // were uploaded. Could alternatively just validate that the
        // selected volume in Settings has the xml permission before saving.
        if ($asset->getExtension() === Constants::FILE_FORMAT_TXT) {
            $file_content = $asset->getContents();

            // check if the file is empty
            if (empty($file_content)) {
                $this->orderLog(sprintf(
                    "File {%s} you are trying to import is empty.",
                    $this->assetName($asset)
                ));
                return false;
            }

            if ($this->fileFormat === Constants::FILE_FORMAT_JSON) {
                return $this->processJsonFile($asset, $file_content);
            } else if ($this->fileFormat === Constants::FILE_FORMAT_CSV) {
                return $this->processCsvFile($asset, $file_content);
            } else if ($this->fileFormat === Constants::FILE_FORMAT_XML) {
                return $this->processXmlFile($asset, $file_content);
            } else {
                $this->orderLog(sprintf(
                    "File {%s} is invalid, please try again with a valid zip/xml/json/csv file.",
                    $this->assetName($asset)
                ));
                return false;
            }
        } else {
            //Invalid
            $this->orderLog(sprintf(
                "File {%s} is invalid, please try again with a valid zip/xml/json/csv file.",
                $this->assetName($asset)
            ));
            return false;
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
            $this->orderLog(sprintf(
                "File {%s} you are trying to import has invalid content.",
                $this->assetName($asset)
            ));
            return false;
        }

        //Source & Target Sites
        $sourceSite = (string)$file_content['source-site'];
        $targetSite = (string)$file_content['target-site'];

        $elementId = $file_content['elementId'];

        if (in_array($elementId, $this->discardElements)) {
            $this->orderLog(sprintf(
                "File {%s} has source entry changes, please update source.",
                $this->assetName($asset)
            ));
            return false;
        }

        foreach ($this->order->getFiles() as $orderFile)
        {
            if (
                $elementId == $orderFile->elementId &&
                $sourceSite == $orderFile->sourceSite &&
                $targetSite == $orderFile->targetSite
            ) {
                //Get File
                $file = Translations::$plugin->fileRepository->getFileById($orderFile->id);
            }
        }

        //Validate If the file was found
        if (!isset($file) || is_null($file))
        {
            $this->orderLog(sprintf(
                "File {%s} does not match any known entries.",
                $this->assetName($asset)
            ));
            return false;
        }

        if ($this->verifyJsonKeysMismatch($file_content, $file->source)) {
            $this->orderLog(sprintf(
                "File {%s} failed to import due to the keys mismatches in the file.",
                $this->assetName($asset)
            ));
            $file->status = Constants::FILE_STATUS_FAILED;
            Translations::$plugin->fileRepository->saveFile($file);
            return false;
        }

        if ((!$this->canProcessPublishedOrder() && $file->isPublished()) || $file->isModified()) {
            $message = 'has already been published.';
            if ($file->isModified()) {
                $message = 'has been modified, please download again and try with latest files.';
            }

            $this->orderLog(sprintf("File {%s} %s", $this->assetName($asset), $message));
            return $file->isModified() ? false : '';
        }

        if ($file->isComplete() || $file->isPublished()) {
            $file->reference = null;
        }

        //Translation Service
        $translationService = Translations::$plugin->translatorFactory
            ->makeTranslationService(Constants::TRANSLATOR_DEFAULT, []);

        $file->target = Translations::$plugin->elementToFileConverter->jsonToXml($file_content);
        $file->status = Constants::FILE_STATUS_REVIEW_READY;
        $file->dateDelivered = new \DateTime();

        // If Successfully saved
        $success = Translations::$plugin->fileRepository->saveFile($file);

        if ($success)
        {
            //Save Order with status complete
            $translationService->updateOrder($this->order);

            $this->orderLog(sprintf("File {%s} imported successfully!", $this->assetName($asset)));

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
                    $this->orderLog(sprintf(
                        "We found errors in {%s} : {%s}", $this->assetName($asset) , $errors
                    ));
                    return false;
                }
            }
        } catch(Exception $e) {
            $this->orderLog('Error processing XML file. Error: ' . $e->getMessage());
            return false;
        }

        // Source & Target Sites
        $sites = $dom->getElementsByTagName('sites');
        if ($sites->length == 0) {
            $this->orderLog(sprintf("File {%s} is missing sites key.", $this->assetName($asset)));
            return false;
        }
        $sites = isset($sites[0]) ? $sites[0] : $sites;
        $sourceSite = (string)$sites->getAttribute('source-site');
        $targetSite = (string)$sites->getAttribute('target-site');

        // Meta ElementId
        $element = $dom->getElementsByTagName('meta');
        if ($element->length == 0) {
            $this->orderLog(sprintf("File {%s} is missing meta key.", $this->assetName($asset)));
            return false;
        }
        $element = isset($element[0]) ? $element[0] : $element;
        $elementId = (string)$element->getAttribute('elementId');

        if (in_array($elementId, $this->discardElements)) {
            $this->orderLog(sprintf(
                "File {%s} has source entry changes, please update source.",
                $this->assetName($asset)
            ));
            return false;
        }

        $orderId = (string)$element->getAttribute('orderId');

        foreach ($this->order->getFiles() as $orderFile)
        {
            if (
                $orderId == $orderFile->orderId &&
                $elementId == $orderFile->elementId &&
                $sourceSite == $orderFile->sourceSite &&
                $targetSite == $orderFile->targetSite
            ) {
                //Get File
                $file = Translations::$plugin->fileRepository->getFileById($orderFile->id);
            }
        }

        //Validate If the file was found
        if (!isset($file) || is_null($file)) {
            $this->orderLog(sprintf(
                "File {%s} does not match any known entries.",
                $this->assetName($asset)
            ));
            return false;
        }

        if ($this->matchXmlKeys($dom, $file)) {
            $this->orderLog(sprintf(
                "File {%s} failed to import due to the resname mismatches in the XML.",
                $this->assetName($asset)
            ));

            $file->status = Constants::FILE_STATUS_FAILED;
            Translations::$plugin->fileRepository->saveFile($file);
            return false;
        }

        // Don't process published files untill orders status is published
        if ((!$this->canProcessPublishedOrder() && $file->isPublished()) || $file->isModified()) {
            $message = 'has already been published.';
            if ($file->isModified()) {
                $message = 'has been modified, please download again and try with latest files.';
            }
            $this->orderLog(sprintf("File {%s} %s", $this->assetName($asset), $message));

            return $file->isModified() ? false : '';
        }

        if ($file->isComplete() || $file->isPublished()) {
            $file->reference = null;
        }

        //Translation Service
        $translationService = Translations::$plugin->translatorFactory
            ->makeTranslationService(Constants::TRANSLATOR_DEFAULT, []);


        $file->status = Constants::FILE_STATUS_REVIEW_READY;
        $file->target = $xml_content;
        $file->dateDelivered = new \DateTime();

        //If Successfully saved
        $success = Translations::$plugin->fileRepository->saveFile($file);

        if ($success) {
            //Save Order with new status
            $translationService->updateOrder($this->order);

            $this->orderLog(sprintf("File {%s} imported successfully!", $this->assetName($asset)));

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

        $elementId = $file_content['elementId'];

        if (in_array($elementId, $this->discardElements)) {
            $this->orderLog(sprintf(
                "File {%s} has source entry changes, please update source.",
                $this->assetName($asset)
            ));

            return false;
        }

        foreach ($this->order->getFiles() as $orderFile)
        {
            if (
                $elementId == $orderFile->elementId &&
                $sourceSite == $orderFile->sourceSite &&
                $targetSite == $orderFile->targetSite
            ) {
                //Get File
                $file = Translations::$plugin->fileRepository->getFileById($orderFile->id);
            }
        }

        //Validate If the file was found
        if (!isset($file) || is_null($file))
        {
            $this->orderLog(sprintf(
                "File {%s} does not match any known entries.",
                $this->assetName($asset)
            ));

            return false;
        }

        if ($this->verifyJsonKeysMismatch($file_content, $file->source)) {
            $this->orderLog(sprintf(
                "File {%s} failed to import due to the keys mismatches in the file.",
                $this->assetName($asset)
            ));

            $file->status = Constants::FILE_STATUS_FAILED;
            Translations::$plugin->fileRepository->saveFile($file);
            return false;
        }

        if ((!$this->canProcessPublishedOrder() && $file->isPublished()) || $file->isModified()) {
            $message = 'has already been published.';
            if ($file->isModified()) {
                $message = 'has been modified, please download again and try with latest files.';
            }
            $this->orderLog(sprintf("File {%s} %s", $this->assetName($asset), $message));

            return $file->isModified() ? false : '';
        }

        if ($file->isComplete() || $file->isPublished()) {
            $file->reference = null;
        }

        //Translation Service
        $translationService = Translations::$plugin->translatorFactory
            ->makeTranslationService(Constants::TRANSLATOR_DEFAULT, []);

        $file->target = Translations::$plugin->elementToFileConverter->jsonToXml($file_content);
        $file->status = Constants::FILE_STATUS_REVIEW_READY;
        $file->dateDelivered = new \DateTime();

        // If Successfully saved
        $success = Translations::$plugin->fileRepository->saveFile($file);

        if ($success)
        {
            //Update Order status
            $translationService->updateOrder($this->order);

            $this->orderLog(sprintf("File {%s} imported successfully!", $this->assetName($asset)));

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

        $xmlSource = $file->source;

        try {
            if (!$dom->loadXML( $xmlSource )) {
                $errors = $this->reportXmlErrors();
                if ($errors) {
                    $this->orderLog(sprintf("We found errors on source xml : ", $errors));
                    return true;
                }
            }
        } catch(Exception $e) {
            $this->orderLog('Invalid XML file. Error: ' . sprintf($e->getMessage()));
            return true;
        }

        $sourceFields = [];
        $sourceContents = $dom->getElementsByTagName('content');
        foreach ($sourceContents as $node) {
            $sourceFields[$node->getAttribute('resname')] = $node->getAttribute('resname');
        }

        return array_diff($targetFields, $sourceFields);
    }

    /**
     * Report and Validate XML imported files
     * @return string
     */
    private function reportXmlErrors()
    {
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
        $contentArray = explode("\n", $file_content, 2);

        if (count($contentArray) != 2) {
            $this->orderLog(sprintf(
                "File {%s} you are trying to import has invalid content.",
                $this->assetName($asset)
            ));

            return false;
        }

        $contentArray = str_replace('","', '"!@#$"', $contentArray);

        $keys = explode("!@#$", $contentArray[0]);
        $values = explode("!@#$", $contentArray[1]);

        if (count($keys) != count($values)) {
            $this->orderLog(sprintf(
                "File {%s} you are trying to import has header and value mismatch.",
                $this->assetName($asset)
            ));

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

    private function orderLog($message)
    {
        $this->order->logActivity(
            Translations::$plugin->translator->translate('app', $message)
        );
        Translations::$plugin->orderRepository->saveOrder($this->order);
    }

    /**
     * Get Asset's name currently being proccessed
     *
     * @param Asset $asset
     * @return string
     */
    private function assetName($asset)
    {
        return $this->fileNames[$asset->id] ?? $asset->getFilename();
    }

    private function canProcessPublishedOrder()
    {
        if (is_null($this->_allowAppliedChanges))
            $this->_allowAppliedChanges = $this->order->isPublished() && $this->order->hasDefaultTranslator();

        return $this->_allowAppliedChanges;
    }
}
