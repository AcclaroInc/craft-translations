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
use ZipArchive;
use craft\models\Site;
use craft\helpers\Path;
use craft\web\UploadedFile;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use yii\web\NotFoundHttpException;

use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\elements\StaticTranslations;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class StaticTranslationsController extends BaseController
{
    /**
     * @return mixed
     */
    public function actionIndex() {

        $variables = [];
        $variables['selectedSubnavItem'] = 'static-translations';

        $this->requireLogin();
        $this->renderTemplate('translations/static-translations/index', $variables);
    }

    /**
     * @return \yii\web\Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSave() {

        $this->requirePostRequest();

        $siteId = Craft::$app->request->getRequiredBodyParam('siteId');
        $site = Craft::$app->getSites()->getSiteById($siteId);
        $lang = $site->language;
        $translations = Craft::$app->request->getRequiredBodyParam('translation');

        try {
            Translations::$plugin->staticTranslationsRepository->set($lang, $translations);
        } catch (\Throwable $e) {
            return $this->asFailure($this->getErrorMessage($e->getMessage()), [
                'success' => false,
                'errors' => [$e->getMessage()]
            ]);
        }

        $job = Translations::$plugin->staticTranslationsRepository->fireStaticTranslationSync();
        return $this->asSuccess($this->getSuccessMessage('Static Translations saved.'), [
            'success' => true,
            'jobId' => (int) $job,
            'errors' => []
        ]);
    }

    /**
     * @return \yii\web\Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSync() {
        $this->requirePostRequest();

        $job = Translations::$plugin->staticTranslationsRepository->fireStaticTranslationSync();

        return $this->asSuccess($this->getSuccessMessage("Sync job added to queue"), [
            'success' => true,
            'jobId' => (int) $job
        ]);
    }

    /**
     * @return \yii\web\Response
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionExport() {

        $this->requirePostRequest();

        $siteIds = array_diff(
            Craft::$app->getSites()->getAllSiteIds(),
            [Craft::$app->getSites()->getPrimarySite()->id]
        );

        $source = Craft::$app->request->getRequiredBodyParam('sourceKey');
        $source = str_replace('*', '/', $source);

        if (count($siteIds) === 1) {
            $filePath = $this->_generateCsvForSite($siteIds[0], $source);
            return $this->asJson([
                'success' => true,
                'filePath' => $filePath,
            ]);
        } else if (count($siteIds) === 0) {
            return $this->asJson([
                'success' => false,
                'error' => 'This feature needs multisite setup.'
            ]);
        }

        // If multiple sites are selected, generate CSVs for each site and create a ZIP file
        $csvFiles = [];

        foreach ($siteIds as $siteId) {
            $csvFiles[] = $this->_generateCsvForSite($siteId, $source);
        }

        // Create ZIP
        $zipFileName = 'StaticTranslations-' . date('YmdHis') . '.zip';
        $zipFilePath = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . $zipFileName;

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE) !== true) {
            return $this->asJson([
                'success' => false,
                'error' => 'Unable to create ZIP file.'
            ]);
        }

        foreach ($csvFiles as $filePath) {
            $zip->addFile($filePath, basename($filePath));
        }

        $zip->close();

        return $this->asJson([
            'success' => true,
            'filePath' => $zipFilePath
        ]);
    }

    /**
     * Generate a CSV file for a given site
     *
     * @param int    $siteId  The site ID
     * @param string $source  The source language
     *
     * @return string The path to the generated CSV file
     */
    private function _generateCsvForSite(int $siteId, string $source): string {
        $elementQuery = StaticTranslations::find();
        $elementQuery->status = null;
        $elementQuery->source = [$source];
        $elementQuery->search = Craft::$app->request->getBodyParam('search', null);
        $elementQuery->siteId = $siteId;

        $translations = Translations::$plugin->staticTranslationsRepository->get($elementQuery);

        $site = Craft::$app->getSites()->getSiteById($siteId);
        $lang = Craft::$app->getI18n()->getLocaleById($site->language);

        $primary = Craft::$app->getSites()->getPrimarySite();
        $primaryLang = Craft::$app->getI18n()->getLocaleById($primary->language);
        $langName = ucfirst(StringHelper::convertToUTF8($lang->displayName));

        $data = '"' . Translations::$plugin->translator->translate('app', "Source: $primaryLang->displayName ($primary->language)") . '","' .
                Translations::$plugin->translator->translate('app', "Target: $langName ($site->language)") . "\"\r\n";

        foreach ($translations as $row) {
            $trans = StringHelper::convertToUTF8($row->translation);
            $data .= '"' . $row->original . '","' . $trans . "\"\r\n";
        }

        $csvFilePath = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . 'StaticTranslations-' . $siteId . '-' . $site->language . '-' . date('YmdHis') . '.csv';
        file_put_contents($csvFilePath, $data);

        return $csvFilePath;
    }

    /**
     * Export Functionality
     * Sends the csv file created to the user
     */
    public function actionExportFile()
    {
        $filename = Craft::$app->getRequest()->getQueryParam('filename');
        if (!is_file($filename) || !Path::ensurePathIsContained($filename)) {
            throw new NotFoundHttpException(Craft::t('app', 'Invalid file name: {filename}', [
                'filename' => $filename
            ]));
        }

        return Craft::$app->getResponse()->sendFile($filename, null, ['inline' => true]);
    }

    /**
     * Import Functionality
     * Handles the import of static translations from a CSV or ZIP file
     *
     * @return void
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionImport(){
        $this->requireLogin();
        $this->requirePostRequest();
        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $file = UploadedFile::getInstanceByName('trans-import');

            if ($this->validateFile($file) && $this->isZip($file)) {
                $extractedPath = $this->_extractFiles($file);
                $files = FileHelper::findFiles($extractedPath);
                $importedCount = 0;

                foreach ($files as $tempFile) {
                    $entry = basename($tempFile);

                    // Skip system entries and non-files
                    if (! is_bool(strpos($entry, '__MACOSX')) || in_array($entry, ['.', '..']) || !is_file($tempFile)) {
                        continue;
                    }

                    if (pathinfo($entry, PATHINFO_EXTENSION) === Constants::FILE_FORMAT_CSV) {
                        // Validate filename pattern and extract siteId
                        if (!preg_match('/StaticTranslations\-([0-9]+)\-[a-zA-Z\-]+\-\d+\.csv$/', $entry, $matches)) {
                            throw new \Exception("Invalid filename format: '$entry'. Expected pattern: StaticTranslations-{siteId}-{lang}-{timestamp}.csv");
                        }

                        $siteId = (int) $matches[1];
                        $site = Craft::$app->getSites()->getSiteById($siteId);

                        if (!$site) {
                            throw new \Exception("No site found for site ID '$siteId' in file '$entry'.");
                        }

                        if (!$this->_processCsvFile($tempFile, $site)) {
                            throw new \Exception("File '$entry' has no valid translation rows.");
                        }
    
                        $importedCount++;
                    }
                }
                FileHelper::removeDirectory($extractedPath);
                if ($importedCount === 0) {
                    throw new \Exception('No valid translation files found in the ZIP.');
                }

                Translations::$plugin->staticTranslationsRepository->fireStaticTranslationSync();
                $transaction->commit();

                $this->setSuccess("Translations imported for {$importedCount} site(s).");
            } elseif ($this->validateFile($file) && $this->isCsv($file)) {
                $siteId = Craft::$app->getRequest()->getRequiredBodyParam('siteId');
                $site = Craft::$app->getSites()->getSiteById($siteId);

                if (!$site) {
                    throw new \Exception("Invalid site ID '$siteId'.");
                }

                if (!$this->_processCsvFile($file->tempName, $site)) {
                    throw new \Exception('No valid translation rows found in the file.');
                }

                Translations::$plugin->staticTranslationsRepository->fireStaticTranslationSync();
                $transaction->commit();

                $this->setSuccess('Translations imported successfully.');
            } else {
                $this->setError('Invalid file type.');
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            Craft::error("Static import failed: " . $e->getMessage(), __METHOD__);
            $this->setError($e->getMessage());
        }
    }

    /**
     * Process the CSV file and save translations for the given site
     *
     * @param string $filePath The path to the CSV file
     * @param Site $site The site for which translations are being processed
     * @return bool True if translations were successfully processed, false otherwise
     */
    private function _processCsvFile(string $filePath, Site $site): bool {
        $rows = [];
        $handle = fopen($filePath, 'r');

        // Skip header
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if (isset($row[0]) && isset($row[1])) {
                $rows[$row[0]] = $row[1];
            }
        }

        fclose($handle);

        if ($rows) {
            Translations::$plugin->staticTranslationsRepository->set($site->language, $rows);
            return true;
        }

        return false;
    }

    /**
     * @param $file
     * @return bool
     */
    public function validateFile($file)
    {
        if (!in_array($file->type, Constants::STATIC_TRANSLATIONS_SUPPORTED_MIME_TYPES)) {
            return false;
        }

        return true;
    }

    private function isCsv($file): bool {
        return $file->getExtension() === Constants::FILE_FORMAT_CSV;
    }

    private function isZip($file): bool {
        return $file->getExtension() === Constants::FILE_FORMAT_ZIP;
    }

    private function _extractFiles($uploadedFile) {
        $zip = new \ZipArchive();
        $assetPath = $uploadedFile->saveAsTempFile();
        $extractPath = Craft::$app->getPath()->getTempPath() . '/extracted/' . uniqid(pathinfo($uploadedFile->name, PATHINFO_FILENAME), true);
        FileHelper::createDirectory($extractPath);
        $zip->open($assetPath);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_contains($zip->getNameIndex($i), '..') || str_contains($zip->getNameIndex($i), '__MACOSX')) {
                continue;
            }
            $zip->extractTo($extractPath, array($zip->getNameIndex($i)));
        }
        $zip->close();

        FileHelper::deleteFileAfterRequest($assetPath);

        return $extractPath;
    }
}
