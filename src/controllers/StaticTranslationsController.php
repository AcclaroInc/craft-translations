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
use yii\web\UploadedFile;
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

        Translations::$plugin->staticTranslationsRepository->set($lang, $translations);

        return $this->asSuccess($this->getSuccessMessage('Static Translations saved.'), [
            'success' => true,
            'errors' => []
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

        $siteId = Craft::$app->request->getRequiredBodyParam('siteId');
        $source = Craft::$app->request->getRequiredBodyParam('sourceKey');
        $source = str_replace('*', '/', $source);

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

        $data = '"' .Translations::$plugin->translator->translate('app', "Source: $primaryLang->displayName ($primary->language)") . '","' . Translations::$plugin->translator->translate('app', "Target: $langName ($site->language)") . "\"\r\n";
        foreach ($translations as $row) {
            $trans = StringHelper::convertToUTF8($row->translation);
            $data .= '"' . $row->original . '","' . $trans . "\"\r\n";
        }

        $file = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . 'StaticTranslations-'.$site->language.'-'.date('Ymdhis') . '.' . Constants::FILE_FORMAT_CSV;
        $fd = fopen($file, "w");
        fputs($fd, $data);
        fclose($fd);

        return $this->asJson([
            'success' => true,
            'filePath' => $file
        ]);
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
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionImport(){

        $this->requireLogin();
        $this->requirePostRequest();

        try {

            $siteId = Craft::$app->getRequest()->getRequiredBodyParam('siteId');
            $site = Craft::$app->getSites()->getSiteById($siteId);

            // Upload the file and drop it in the temporary folder
            $file = UploadedFile::getInstanceByName('trans-import');

            // validate file
            if (!$this->validateFile($file)) {
                $this->setError('Invalid file type.');
            } else {

                $rows = [];
                $handle = fopen($file->tempName, 'r');

                while (($row = fgetcsv($handle)) !== false) {
                    if (isset($row[0]) && isset($row[1])) {
                        $rows[$row[0]] = $row[1];
                    }
                }
                fclose($handle);

                if ($rows) {
                    Translations::$plugin->staticTranslationsRepository->set($site->language, $rows);
                    $this->setSuccess('Translations imported successfully.');
                } else {
                    $this->setError('No translation imported.');
                }
            }
        }  catch (\Exception $e) {
            $this->setError($e->getMessage());
        }

    }

    /**
     * @param $file
     * @return bool
     */
    public function validateFile($file)
    {
        if ($file->getExtension() !== Constants::FILE_FORMAT_CSV) {
            return false;
        }

        if (!in_array($file->type, Constants::STATIC_TRANSLATIONS_SUPPORTED_MIME_TYPES)) {
            return false;
        }

        return true;
    }

}
