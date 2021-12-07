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
use craft\web\Controller;
use craft\helpers\Path;
use craft\helpers\FileHelper;
use craft\helpers\App as CraftApp;
use craft\base\VolumeInterface;
use yii\web\NotFoundHttpException;

use acclaro\translations\elements\Order;
use acclaro\translations\Translations;
use acclaro\translations\services\job\DeleteDrafts;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class SettingsController extends Controller
{
    /**
     * @return mixed
     */
    public function actionIndex()
    {
        $this->renderTemplate('translations/settings/index');
    }
    
    /**
     * @return mixed
     */
    public function actionSettingsCheck()
    {
        $this->requireLogin();
        if (!Translations::$plugin->userRepository->userHasAccess('translations:settings')) {
            return $this->redirect(Constants::URL_TRANSLATIONS, 302, true);
        }

        $variables = array();
        $supportedFieldTypes = [
            'craft\fields\Tags',
            'craft\fields\Table',
            'craft\fields\Assets',
            'craft\fields\Matrix',
            'craft\fields\Number',
            'craft\fields\Entries',
            'craft\fields\Dropdown',
            'craft\fields\PlainText',
            'craft\fields\Categories',
            'craft\fields\Checkboxes',
            'craft\fields\MultiSelect',
            'craft\fields\RadioButtons',
            'benf\neo\Field',
            'typedlinkfield\fields\LinkField',
            'craft\redactor\Field',
            'fruitstudios\linkit\fields\LinkitField',
            'luwes\codemirror\fields\CodeMirrorField',
            'verbb\supertable\fields\SuperTableField',
            'nystudio107\seomatic\fields\SeoSettings',
            'lenz\linkfield\fields\LinkField',
            'newism\fields\fields\Telephone',
            'newism\fields\fields\Address',
            'newism\fields\fields\Email',
            'newism\fields\fields\Embed',
            'newism\fields\fields\PersonName',
            'newism\fields\fields\Gender',
            'ether\seo\fields\SeoField'
        ];

        $unrelatedFieldTypes = [
            'craft\fields\Color',
            'craft\fields\Date',
            'craft\fields\Email',
            'craft\fields\Lightswitch',
            'craft\fields\Time',
            'craft\fields\Url',
            'craft\fields\Users'
        ];

        $variables['settings'] = [];

        $variables['settings']['craftVersion'] = Craft::$app->getVersion();
        $variables['settings']['phpVersion'] = CraftApp::phpVersion();
        $variables['settings']['DOMEnabled'] = extension_loaded('dom');
        $variables['settings']['isMultisite'] = Craft::$app->getIsMultiSite();
        $variables['settings']['sections'] = Craft::$app->getSections()->getAllSections();

        foreach (Craft::$app->getFields()->getAllFieldTypes() as $key => $fieldType) {
            if (in_array($fieldType, $supportedFieldTypes)) {
                $isSupported = 'true';
            } elseif (in_array($fieldType, $unrelatedFieldTypes)) {
                $isSupported = 'unrelated';
            } else {
                $isSupported = 'false';
            }

            $class = $fieldType;
            $class = new $class;

            $variables['settings']['fields'][$key] = [
                'class' => $fieldType,
                'displayName' => $class::displayName() ? $class::displayName() : $fieldType,
                'isSupported' => $isSupported,
            ];
        }

        $this->renderTemplate('translations/settings/settings-check', $variables);
    }

    /**
     * @return mixed
     */
    public function actionSendLogs()
    {
        $this->requireLogin();
        if (!Translations::$plugin->userRepository->userHasAccess('translations:settings')) {
            return $this->redirect(Constants::URL_TRANSLATIONS, 302, true);
        }

        $variables['settings'] = [];

        $variables['settings']['craftVersion'] = Craft::$app->getVersion();
        $variables['settings']['phpVersion'] = CraftApp::phpVersion();
        $variables['settings']['DOMEnabled'] = extension_loaded('dom')  ? 'true' : 'false';
        $variables['settings']['isMultisite'] = Craft::$app->getIsMultiSite() ? 'true' : 'false';

        foreach (Craft::$app->plugins->getAllPlugins() as $key => $plugin) {
            $variables['settings']['plugins'][$key] = $plugin->getVersion();
        }

        $variables['settings']['plugins'] = json_encode($variables['settings']['plugins']);

        $this->renderTemplate('translations/settings/send-logs', $variables);
    }

    public function actionDeleteAllOrders()
    {
        $this->requireLogin();
        if (!Translations::$plugin->userRepository->userHasAccess('translations:settings:clear-orders')) {
            return;
        }

        $orders = Order::find()->ids();

        try {
            foreach ($orders as $key => $orderId) {
                $order = Translations::$plugin->orderRepository->getOrderById($orderId);
        
                if ($order) {
                    $drafts = [];
                    foreach ($order->getFiles() as $file) {
                        $drafts[] = $file->draftId;
                    }
                    if ($drafts) {
                        Craft::$app->queue->push(new DeleteDrafts([
                            'description' => Constants::JOB_DELETING_DRAFT,
                            'drafts' => $drafts,
                        ]));
                    }
        
                    Craft::$app->getElements()->deleteElementById($orderId);
                }
            }

            Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Orders cleared.'));
        } catch (\Throwable $th) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Unable to clear orders.'));
        }
    }

    public function actionDownloadLogs()
    {
        $this->requireLogin();
        if (!Translations::$plugin->userRepository->userHasAccess('translations:settings')) {
            return $this->redirect(Constants::URL_TRANSLATIONS, 302, true);
        }

        $zipName = 'logs';
        $zipDest = Craft::$app->path->getTempPath().'/'. $zipName .'_'.time().'.' . Constants::FILE_FORMAT_ZIP;
        $errors = array();

        // Create zip
        $zip = new \ZipArchive();

        // Open zip
        if ($zip->open($zipDest, $zip::CREATE) !== true)
        {
            $errors[] = 'Unable to create zip file: '.$zipDest;
            Craft::log( '['. __METHOD__ .'] Unable to create zip file: '.$zipDest, LogLevel::Error, 'translations' );
            return false;
        }

        $logFiles = array_diff(scandir(Craft::$app->path->getLogPath()), array('.', '..'));
        
        foreach ($logFiles as $key => $file) {
            $file_contents = file_get_contents(Craft::$app->path->getLogPath() .'/'. $file);

            if (!$zip->addFromString($file, $file_contents))
            {
                $errors[] = 'There was an error adding the file '.$file.' to the zip: '.$zipName;
                Craft::log( '['. __METHOD__ .'] There was an error adding the file '.$file.' to the zip: '.$zipName, LogLevel::Error, 'translations' );
            }
        }

        // Close zip
        $zip->close();

        if(count($errors) > 0)
        {
            return $errors;
        }

        if (!is_file($zipDest) || !Path::ensurePathIsContained($zipDest)) {
            throw new NotFoundHttpException(Craft::t('app', 'Invalid file name: {filename}', [
                'filename' => $zipDest
			]));
        }
        
        Craft::$app->getResponse()->sendFile($zipDest, null, ['inline' => true]);

        return FileHelper::unlink($zipDest);
    }

    public function actionConfigurationOptions()
    {
        $this->requireLogin();
        if (!Translations::$plugin->userRepository->userHasAccess('translations:settings:clear-orders')) {
            return;
        }

        $settings = Translations::getInstance()->settings;
        $variables['chkDuplicateEntries'] = $settings->chkDuplicateEntries;
        $variables['uploadVolume'] = $settings->uploadVolume;
        $variables['twigSearchFilterSingleQuote'] = !empty($settings->twigSearchFilterSingleQuote) ? $settings->twigSearchFilterSingleQuote : "";
        $variables['twigSearchFilterDoubleQuote'] = !empty($settings->twigSearchFilterDoubleQuote) ? $settings->twigSearchFilterDoubleQuote : "";
        $variables['targetStringPosition'] = !empty($settings->targetStringPosition) ? $settings->targetStringPosition : "";

        $allVolumes = Craft::$app->getVolumes()->getAllVolumes();

        $variables['volumeOptions'] = array_map(function (VolumeInterface $volume) {
        	return [
        		'label' => $volume->name,
        		'value' => $volume->id,
        	];
        }, $allVolumes);

        // Add default temp uploads option
        array_unshift($variables['volumeOptions'], [
            'label' => 'Temp Uploads',
            'value' => 0,
        ]);

        $this->renderTemplate('translations/settings/configuration-options', $variables);
    }

    public function actionSaveConfigurationOptions()
    {
        $this->requireLogin();
        if (!Translations::$plugin->userRepository->userHasAccess('translations:settings:clear-orders')) {
            return;
        }

        $request = Craft::$app->getRequest();
        $duplicateEntries = $request->getParam('chkDuplicateEntries');
        $selectedVolume = $request->getParam('uploadVolume');
        $twigSearchFilterSingleQuote = $request->getParam('twigSearchFilterSingleQuote');
        $twigSearchFilterDoubleQuote = $request->getParam('twigSearchFilterDoubleQuote');
        $targetStringPosition = $request->getParam('targetStringPosition');

        try {

            $pluginService = Craft::$app->getPlugins();
            $plugin  = $pluginService->getPlugin(Constants::PLUGIN_HANDLE);
            if (!$pluginService->savePluginSettings($plugin, ['chkDuplicateEntries' => $duplicateEntries, 'uploadVolume' => $selectedVolume, 'twigSearchFilterSingleQuote' => $twigSearchFilterSingleQuote, 'twigSearchFilterDoubleQuote' => $twigSearchFilterDoubleQuote, 'targetStringPosition' => $targetStringPosition])) {
                Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Unable to save setting.'));
            } else {
                Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Setting saved.'));
            }

        } catch (\Throwable $th) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Unable to save setting.'));
        }
    }
}
