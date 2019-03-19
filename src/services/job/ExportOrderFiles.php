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
use ReflectionClass;
use craft\helpers\Path;
use craft\elements\GlobalSet;
use craft\helpers\FileHelper;
use craft\helpers\ElementHelper;
use acclaro\translations\services\App;
use acclaro\translations\Translations;
use acclaro\translations\services\repository\SiteRepository;

class ExportOrderFiles implements JobInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle()
    {
        $siteRepository = new SiteRepository(Craft::$app);
        $tempPath = Craft::$app->path->getTempPath();
        $error = array();

        $orderAttributes = $this->order->getAttributes();

        //Filename Zip Folder
        $zipName = $orderAttributes['id'].'_'.$orderAttributes['sourceSite'];

        // Set destination zip
        $zipDest = Craft::$app->path->getTempPath().$zipName.'_'.time().'.zip';

        // Create zip
        $zip = new \ZipArchive();


        // Open zip
        if ($zip->open($zipDest, $zip::CREATE) !== true)
        {
            $error[] = 'Unable to create zip file: '.$zipDest;
            Craft::log('Unable to create zip file: '.$zipDest, LogLevel::Error);
            return false;
        }

        //Iterate over each file on this order
        if ($this->order->files)
        {
            foreach ($this->order->files as $file)
            {
                $element = Craft::$app->elements->getElementById($file->elementId);

                $targetSite = $file->targetSite;
                if ($element instanceof GlobalSet)
                {
                    $filename = $file->elementId . '-'.ElementHelper::createSlug($element->name).'-'.$targetSite.'.xml';
                }
                else
                {
                    $filename = $file->elementId . '-'.$element->slug.'-'.$targetSite.'.xml';
                }

                $path = $tempPath.$filename;

                $fileContent = file_get_contents($file->source);

                if (!$zip->addFromString($filename, $file->source))
                {
                    $error[] = 'There was an error adding the file '.$filename.' to the zip: '.$zipName;
                    Craft::log('There was an error adding the file '.$filename.' to the zip: '.$zipName, LogLevel::Error);
                }
            }
        }

        // Close zip
        $zip->close();

        return array('translatedFiles' => FileHelper::getFiles($zipDest, false));
    }
}