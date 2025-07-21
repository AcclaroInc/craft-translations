<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\repository;

use Craft;
use Exception;
use craft\helpers\FileHelper;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;
use craft\helpers\ElementHelper;
use acclaro\translations\Constants;
use acclaro\translations\Translations;
use craft\elements\db\ElementQueryInterface;
use acclaro\translations\services\QueueHelper;
use acclaro\translations\elements\StaticTranslations;
use acclaro\translations\models\StaticTranslationsModel;
use acclaro\translations\records\StaticTranslationsRecord;
use acclaro\translations\services\job\SyncStaticTranslations;

class StaticTranslationsRepository
{
    protected $defaultColumns = [
        'id',
        'siteId',
        'original',
        'translation'
    ];

    /**
     * @param ElementQueryInterface $query
     * @return array
     */
    public function get(ElementQueryInterface $query)
    {
        $translations = [];
        $elementId = 0;
        $category = 'site';

        if (!empty($query->source) && !is_array($query->source)) {
            $query->source = [$query->source];
        }

        foreach ($query->source as $filePath) {
            // Check if source path is folder or file
            if (is_dir($filePath)) {
                $options = [
                    'recursive' => true,
                    'only' => ['*.php','*.html','*.twig','*.js','*.json','*.atom','*.rss'],
                    'except' => ['vendor/', 'node_modules/']
                ];
                $files = FileHelper::findFiles($filePath, $options);
                foreach ($files as $file) {

                    // process file
                    $elements = $this->getFileStrings($filePath, $file, $query, $category, $elementId);
                    $translations = array_merge($translations, $elements);
                }
            } elseif (file_exists($filePath)) {

                // process file
                $elements = $this->getFileStrings($filePath, $filePath, $query, $category, $elementId);
                $translations = array_merge($translations, $elements);
            }
        }

        return $translations;
    }

    /**
     * Search in file
     *
     * @param $path
     * @param $file
     * @param ElementQueryInterface $query
     * @param $category
     * @param $elementId
     * @return array
     */
    private function getFileStrings($path, $file, ElementQueryInterface $query, $category, &$elementId)
    {
        $translations = [];
        $contents     = file_get_contents($file);
        $extension    = pathinfo($file, PATHINFO_EXTENSION);

        $fileOptions = $this->getExpressions($extension);

        if (!isset($fileOptions['regex'])) return [];

        foreach ($fileOptions['regex'] as $regex) {
            if (preg_match_all($regex, $contents, $matches)) {
                $position = $fileOptions['position'];
                if(isset($matches[$position])){
                    foreach ($matches[$position] as $original) {
                        $translateId = ElementHelper::normalizeSlug($original);
                        $view = Craft::$app->getView();
                        $site = Craft::$app->getSites()->getSiteById($query->siteId);
                        $translation = Craft::t($category, $original, [], $site->language);

                        $field = $view->renderTemplate('_includes/forms/text', [
                            'id' => $translateId,
                            'name' => 'translation['.$original.']',
                            'value' => $translation,
                            'placeholder' => $translation,
                        ]);

                        $element = new StaticTranslations([
                            'id' => $elementId,
                            'translateId' => ElementHelper::normalizeSlug($original),
                            'original' => $original,
                            'translation' => $translation,
                            'source' => $path,
                            'file' => $file,
                            'siteId' => $query->siteId,
                            'field' => $field,
                        ]);

                        $elementId++;
                        if ($query->status && $query->status != $element->getTranslateStatus()) {
                            continue;
                        }
                        if ($query->search && !stristr($element->original, $query->search) && !stristr($element->translation, $query->search)) {
                            continue;
                        }

                        if ($query->id)
                        {
                            foreach ($query->id as $id) {
                                if ($element->id == $id) {
                                    $translations[$element->original] = $element;
                                }
                            }
                        }
                        else{
                            $translations[$element->original] = $element;
                        }
                    }
                }
            }
        }

        return $translations;
    }

    /**
     * @param $ext
     * @return array
     */
    public function getExpressions($ext) {
        $settings = Translations::getInstance()->settings;
        if(!empty($settings->twigSearchFilterSingleQuote)) {
            $twigSearchFilterSingleQuote = $settings->twigSearchFilterSingleQuote;
            $twigSearchFilterDoubleQuote = $settings->twigSearchFilterDoubleQuote;
            $targetStringPosition = $settings->targetStringPosition;
        } else {
            $twigSearchFilterSingleQuote = '/\'((?:[^\']|\\\\\')*)\'\s*\|\s*t(?:ranslate)?\b/';
            $twigSearchFilterDoubleQuote = '/"((?:[^"]|\\\\")*)"\s*\|\s*t(?:ranslate)?\b/';
            $targetStringPosition = 1;
        }

        $exp = [];
        switch ($ext) {
            case 'php':
                $exp = [
                    'position' => '3',
                    'regex' => [
                        '/Craft::(t|translate)\(.*?\'(.*?)\'.*?\,.*?\'(.*?)\'.*?\)/',
                        '/Craft::(t|translate)\(.*?"(.*?)".*?\,.*?"(.*?)".*?\)/',
                    ]
                ];
                break;
            case 'twig':
            case 'html':
            case 'atom':
            case 'rss':
                $exp = [
                    'position' => $targetStringPosition,
                    'regex' => [
                        $twigSearchFilterSingleQuote, $twigSearchFilterDoubleQuote
                    ]
                ];
                break;
            case 'js':
                $exp = [
                    'position' => '3',
                    'regex' => [
                        '/Craft\.(t|translate)\(.*?\'(.*?)\'.*?\,.*?\'(.*?)\'.*?\)/',
                        '/Craft::(t|translate)\(.*?"(.*?)".*?\,.*?"(.*?)".*?\)/',
                    ]
                ];
                break;
        }

        return $exp;
    }

    /**
     * @param $lang
     * @param array $fileContent
     * @return bool
     * @throws Exception
     */
    public function set($lang, array $fileContent)
    {
        try {
            // get translation file path
            $sitePath = $this->getTranslationsPath();
            $file = $sitePath.DIRECTORY_SEPARATOR.$lang.DIRECTORY_SEPARATOR.'site.php';

            if ($existingContent = @include($file)) {
                $fileContent = array_merge($existingContent, $fileContent);
            }

            $content = "<?php\r\n\r\nreturn ";
            $content .= var_export($fileContent, true);
            $content .= ';';
            $content = str_replace("  '", "\t'", $content);

            // write to file
            FileHelper::writeToFile($file, $content);
        }catch (\Exception $e) {
            throw new \Exception(Craft::t('app','Something went wrong'));
        }

        return true;
    }

    /**
     * Sync translations with database
     * @return [] ["success" : bool, "message": string]
     */
    public function syncWithDB($queue = null) {
        $response = [
            "success" => true,
            "message" => "Static Translations synced."
        ];
        $source = $this->getTemplatesPath();
        $source = str_replace('*', '/', $source);
        $siteIds = Craft::$app->getSites()->getAllSiteIds();
        $elementQuery = StaticTranslations::find();
        $elementQuery->status = null;
        $elementQuery->source = [$source];
        $elementQuery->search = null;
        $syncJob = new SyncStaticTranslations();
        $totalProcessed = 0;

        try {
            $restoreFromDB = !$this->hasTranslationFiles();

            foreach ($siteIds as $site) {
                if ($restoreFromDB) {
                    if (count($staticTranslationFromDB = $this->getStaticTranslations()) > 0) {
                        // Arranging per site basis to create one site file at once
                        $translationsPerSite = ArrayHelper::index($staticTranslationFromDB, null, 'siteId');
                        $this->restoreStaticTranslations($site, $translationsPerSite[$site]);
                    }
                } else {
                    $elementQuery->siteId = $site;
                    $translations = Translations::$plugin->staticTranslationsRepository->get($elementQuery);
                    $totalTranslations = count($translations) * count($siteIds);
    
                    foreach ($translations as $row) {
                        $target = StringHelper::convertToUTF8($row->translation);
                        $original = $row->original;
                        $this->createNewTranslation($site, $original, $target);
                        if ($queue) {
                            $syncJob->updateProgress($queue, $totalProcessed++/$totalTranslations);
                        }
                    }
                }
            }
            return $response;
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            Translations::$plugin->logHelper->log(
                'Error syncing static translations: ' . $errorMessage,
                Constants::LOG_LEVEL_ERROR
            );
            return [
                'message' => $errorMessage,
                'success' => false
            ];
        }
    }

    /**
     * Fire sync static translation job
     */
    public function fireStaticTranslationSync() {
        $job = QueueHelper::push(new SyncStaticTranslations());

        return $job;
    }

    /**
     * Return path where static translation files are stored.
     */
    public function getTranslationsPath(): string
    {
        return Craft::$app->getPath()->getSiteTranslationsPath();
    }

    /**
     * Return path where templates lives.
     */
    public function getTemplatesPath(): string
    {
        return Craft::$app->getPath()->getSiteTemplatesPath();
    }

    private function getNewTranslation()
    {
        return new StaticTranslationsModel();
    }

    private function createNewTranslation(string|int $siteId, string $original, string $target)
    {
        $translation = $this->getNewTranslation();
        $translation->siteId = $siteId;
        $translation->original = $original;
        $translation->translation = $target;

        return $translation->createOrUpdate();
    }

    private function hasTranslationFiles(): bool
    {
        try {
            $translations = scandir($this->getTranslationsPath());
            $filtered = array_diff($translations, ['.', '..']);
        } catch (Exception $e) {
            Translations::$plugin->logHelper->log(
                'Error checking for translation files: ' . $e->getMessage(),
                Constants::LOG_LEVEL_ERROR
            );
            return false;
        }
        return boolval($filtered);
    }

    private function getStaticTranslations()
    {
        $logs = StaticTranslationsRecord::find()->all();

        $activityLogs = [];

        foreach ($logs as $log) {
            $activityLogs[] = new StaticTranslationsModel($log->toArray($this->defaultColumns));
        }

        return $activityLogs;
    }

    private function restoreStaticTranslations($siteId, $translations)
    {
        $site = Craft::$app->getSites()->getSiteById($siteId);
        $fullPath = $this->getTranslationsPath() . DIRECTORY_SEPARATOR . $site->language;
        mkdir($fullPath, 0755, true);
        $arrayContent = [];
        foreach($translations as $translation) {
            $arrayContent[$translation->original] = $translation->translation;
        }
        $filePath = $fullPath . DIRECTORY_SEPARATOR . "site.php";
        $arrayContent = var_export($arrayContent, true);
        $fileContent = $fileContent = <<<PHP
        <?php

        return $arrayContent;
        PHP;

        file_put_contents($filePath, $fileContent);
    }
}
