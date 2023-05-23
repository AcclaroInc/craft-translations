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
use DOMDocument;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\GlobalSet;

use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\models\FileModel;
use acclaro\translations\records\FileRecord;
use acclaro\translations\services\job\RegeneratePreviewUrls;
use craft\commerce\elements\Product;

class FileRepository
{
    protected $defaultColumns = [
        'id',
        'orderId',
        'elementId',
        'draftId',
        'sourceSite',
        'targetSite',
        'status',
        'wordCount',
        'source',
        'reference',
        'target',
        'previewUrl',
        'serviceFileId',
        'dateUpdated',
        'dateDelivered',
        'dateDeleted'
    ];

    /**
     * @param  int|string $fileId
     * @return \acclaro\translations\models\FileModel
     */
    public function getFileById($fileId)
    {
        $record = FileRecord::findOne($fileId);

        if (!$record) {
            return false;
        }

        $file = new FileModel($record->toArray($this->defaultColumns));

        return $file;
    }

    /**
     * @param  int|string $draftId
     * @param  int|string $elementId
     * @return \acclaro\translations\models\FileModel
     */
    public function getFileByDraftId($draftId, $elementId = null)
    {
        $attributes = array('draftId' => (int) $draftId);

        if ($elementId) {
            $attributes['elementId'] = $elementId;
        }

        $record = FileRecord::findOne($attributes);

        if (!$record) {
            return false;
        }

        $file = new FileModel($record->toArray($this->defaultColumns));

        return $file;
    }

    /**
     * @param  int|string $orderId
     * @return [ \acclaro\translations\models\FileModel ]
     */
    public function getFiles($orderId = null, $elementId = null, $targetSite = null)
    {
		$attributes = array('dateDeleted' => null);

		if ($orderId) $attributes['orderId'] = $orderId;
		if ($elementId) $attributes['elementId'] = $elementId;
        if ($targetSite) $attributes['targetSite'] = $targetSite;

        $records = FileRecord::find()->where($attributes)->orderBy('elementId')->all();

        $files = array();

        foreach ($records as $key => $record) {
            $files[$key] = new FileModel($record->toArray($this->defaultColumns));
        }

        return $files ? $files : array();
    }

    /**
     * @return array
     */
    public function getAllDraftIds()
    {

        $records = FileRecord::find()->where(['IS NOT', 'draftId', NULL])->all();

        $draftIds = [];

        foreach ($records as $key => $record) {
            if(!empty($record->draftId)){
                $draftIds[] = $record->draftId;
            }
        }

        return $draftIds;
    }

    /**
     * @return \acclaro\translations\models\FileModel
     */
    public function makeNewFile()
    {
        return new FileModel();
    }

    /**
     * @param  \acclaro\translations\models\FileModel $file
     * @throws \Exception
     * @return bool
     */
    public function saveFile(FileModel $file)
    {
        $isNew = !$file->id;

        if (!$isNew) {
            $record = FileRecord::findOne($file->id);

            if (!$record) {
                throw new Exception('No file exists with that ID.');
            }
            $record->setAttributes($file->getAttributes(), false);
        } else {
            $record = new FileRecord();
            $att = $file->getAttributes();
            if (empty($att['id'])) {
                unset($att['id']);
            }
            $record->setAttributes($att, false);
        }

        if (!$record->validate()) {
            $file->addErrors($record->getErrors());

            return false;
        }

        if ($file->hasErrors()) {
            return false;
        }

        $transaction = Craft::$app->db->getTransaction() === null ? Craft::$app->db->beginTransaction() : null;

        try {
            if ($record->save(false)) {
                if ($transaction !== null) {
                    $transaction->commit();
                }

                return true;
            }
        } catch (Exception $e) {
            if ($transaction !== null) {
                $transaction->rollback();
            }

            throw $e;
        }

        return false;
    }

    public function cancelOrderFile($file)
    {
        $file->status = Constants::FILE_STATUS_CANCELED;
        Translations::$plugin->fileRepository->saveFile($file);
    }

    /**
     * @param $draftId
     * @return false|int
     * @throws \Throwable
     */
    public function deleteByDraftId($draftId, $elementId=null)
    {
        $attributes = ['draftId' => (int) $draftId];
        if ($elementId) {
            $attributes['elementId'] = $elementId;
        }

        $record = FileRecord::findOne($attributes);

        if ($record && $record->draftId) {
            $element = Translations::$plugin->elementRepository->getElementByDraftId($record->draftId, $record->sourceSite);
            if ($element)
                Craft::$app->getElements()->deleteElement($element);
        }

        return $record->delete();
    }

    public function delete($orderId, $elementId = null, $targetSite = null)
    {
        $attributes = ['orderId' => (int) $orderId];

        if ($elementId) $attributes['elementId'] = (int) $elementId;
        if ($targetSite) $attributes['targetSite'] = (int) $targetSite;

        $records = FileRecord::find()->where($attributes)->all();

        foreach($records as $record) {
            if ($record && $record->draftId) {
                $file = new FileModel($record->toArray([
                    'id',
                    'orderId',
                    'elementId',
                    'draftId',
                    'sourceSite',
                    'targetSite',
                    'status',
                ]));

                if ($element = $file->hasDraft())
                    Craft::$app->getElements()->deleteElement($element);
            }
            $record->delete();
        }

        return true;
    }

    /**
     * @param \acclaro\translations\elements\Order $order
     * @param null $queue
     * @return bool
     * @throws \Throwable
     */
    public function regeneratePreviewUrls($order, $previewUrls, $files = [], $queue=null) {
        $totalElements = count($order->files);
        $currentElement = 0;

        $service = new RegeneratePreviewUrls();
        foreach ($order->getFiles() as $file) {

            if (!($file->isComplete() || in_array($file->id, $files))) continue;

            if ($queue) {
                $service->updateProgress($queue, $currentElement++ / $totalElements);
            }
            $transaction = Craft::$app->getDb()->beginTransaction();

            try {
                $draft = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);

                if ($draft) {
                    $file->previewUrl = $previewUrls[$file->id] ?? $draft->url;
                }

                Translations::$plugin->fileRepository->saveFile($file);
                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }
        }

        if ($order->translator->service === Constants::TRANSLATOR_ACCLARO) {
            $translationService = $order->getTranslationService();

            $translationService->updateReviewFileUrls($order);
        }

        return true;
    }

    public function getOrderFilesPreviewUrl($order): array
    {
        $filePreviewUrls = [];
        foreach ($order->files as $file) {
            if ($file->hasDraft() && $file->isComplete()) {
                $draft = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);

                if ($draft) {
                    $filePreviewUrls[$file->id] = Translations::$plugin->urlGenerator->generateElementPreviewUrl($draft, $file->targetSite);
                }
            }
        }

        return $filePreviewUrls;
    }

    /**
     * @param $order
     * @param array $wordcounts
     * @return bool
     */
    public function createOrderFiles($order, $wordCounts)
    {
        // ? Create File for each element per target language
        foreach ($order->getTargetSitesArray() as $key => $targetSite) {
            foreach ($order->getElements() as $element) {
                $wordCount = $wordCounts[$element->id] ?? 0;

                $file = $this->makeNewFile();

                $file->orderId = $order->id;
                $file->elementId = $element->id;
                $file->sourceSite = $order->sourceSite;
                $file->targetSite = $targetSite;

                $file->source = Translations::$plugin->elementToFileConverter->convert(
                    $element,
                    Constants::FILE_FORMAT_XML,
                    [
                        'sourceSite'    => $order->sourceSite,
                        'targetSite'    => $targetSite,
                        'wordCount'     => $wordCount,
                        'orderId'       => $order->id
                    ]
                );
                $file->wordCount = $wordCount;

                Translations::$plugin->fileRepository->saveFile($file);
            }
        }
        return true;
    }

    public function createOrderFile($order, $elementId, $targetSite)
    {
        $element = Translations::$plugin->elementRepository->getElementById($elementId);
        $wordCount = Translations::$plugin->elementTranslator->getWordCount($element) ?? 0;

        $file = $this->makeNewFile();

        $file->orderId = $order->id;
        $file->elementId = $element->id;
        $file->sourceSite = $order->sourceSite;
        $file->targetSite = $targetSite;
        $file->source = Translations::$plugin->elementToFileConverter->convert(
            $element,
            Constants::FILE_FORMAT_XML,
            [
                'sourceSite'    => $order->sourceSite,
                'targetSite'    => $targetSite,
                'wordCount'     => $wordCount,
                'orderId'       => $order->id,
            ]
        );
        $file->wordCount = $wordCount;

        return $file;
    }

    public function getUploadedFilesWordCount($asset, $format)
    {
        $fileContents = $asset->getContents();

        $elementId = Translations::$plugin->elementToFileConverter->getElementIdFromData($fileContents, $format);
        if (! $elementId) {
            return 0;
        }

        $element = Translations::$plugin->elementRepository->getElementById($elementId);

        if (! $element) return 0;

        return Translations::$plugin->elementTranslator->getWordCount($element);
    }

    public function getSourceTargetDifferences($source, $target)
    {
        $data = [];
        // Current entries XML
        $sourceContent = Translations::$plugin->elementTranslator->getTargetData($source, true);

        // Translated file XML
        $targetContent = Translations::$plugin->elementTranslator->getTargetData($target, true);

        foreach ($sourceContent as $key => $value) {
            if ($value != ($targetContent[$key] ?? '')) {
                $data[$key] = [
                    'source' => $value ?? '',
                    'target' => $targetContent[$key] ?? '',
                ];
            }
        }
        return $data;
    }

    public function getFileDiffHtml($data, $isDifference = null)
    {
        $copyIcon = '<svg height="1em" viewBox="0 0 24 24" width="1em"><path d="M0 0h24v24H0z" fill="none"></path><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"></path></svg>';
        $mainContent = '';

        if ($isDifference) {
            foreach ($data as $key => $values) {
                $content = '<tr>';
                krsort($values);
                foreach ($values as $class => $value) {
                    $content .= "<td class='$class'>";

                    $content .= "<label class='diff-tl'> $key: </label>";

                    $content .= "<div class='diff-copy'> $copyIcon </div><br>";

                    $content .= "<span class='diff-bl'> $value </span></td>";
                }

                $mainContent .= $content . '</tr>';
            }
        } else {
            $sourceContent = Translations::$plugin->elementTranslator->getTargetData($data, true);

            foreach ($sourceContent as $key => $value) {
                $content = '<tr><td class="source">';

                $content .= "<label class='diff-tl'> $key: </label>";

                $content .= "<div class='diff-copy'> $copyIcon </div><br>";

                $content .= "<span class='diff-bl'> $value </span>";

                $mainContent .= $content . '</td></tr>';
            }
        }

        return '<table class="diffTable data"><tbody>' . $mainContent . '</tbody></table>';
    }

    /**
     * @param \acclaro\translations\models\FileModel $file
     */
    public function isReferenceChanged($file)
    {
        $currentData = $file->getTmMisalignmentFile()['reference'];

        return md5($currentData) !== md5($file->reference);
    }

    /**
     * @param \acclaro\translations\models\FileModel $file
     */
    public function checkTmMisalignments($file)
    {
        try {
            $element = $file->getElement();
            $source = $file->source;
            $sourceSite = $file->sourceSite;

            if ($file->isComplete()) {
                $element = $this->getDraft($file);
                $source = $file->target;
                $sourceSite = $file->targetSite;
            }

            // Skip incase entry doesn't exist for target site
            if (!$element) return false;

            $converter = Translations::$plugin->elementToFileConverter;

            $currentContent = Translations::$plugin->elementTranslator->toTranslationSource($element, $sourceSite);
            $currentContent = json_encode(array_map("strval", array_values($currentContent)));

            $sourceContent = json_decode($converter->xmlToJson($source), true);

            $sourceContent = json_encode(array_values($sourceContent['content']));

            /**
             * Replace `\u00a0` created by mysql with `space`
             * as mysql replaces any space before special char like ?, ! with `\u00a0`
             */
            $sourceContent = str_replace('\u{00a0}', ' ', $sourceContent);

            if (md5($sourceContent) !== md5($currentContent)) {
                return true;
            }
        } catch (\Exception $e) {
            Translations::$plugin->logHelper->log($e, Constants::LOG_LEVEL_ERROR);
        }

        return false;
    }

    /**
     * Return a preview setting array used for creating per file preview
     *
     * @param \acclaro\translations\models\FileModel $file
     *
     *@return array $settings
     */
    public function getFilePreviewSettings(FileModel $file, $trigger)
    {
        $settings = [];
        $element = $file->getElement(false);

        if ($element) {
            $security = Craft::$app->getSecurity();

            if ($trigger) {
                $settings = [
                    'trigger' => $trigger,
                    'previewUrl' => $element->getUrl(),
                    'previewAction' => $security->hashData('commerce/products-preview/preview-product'),
                    'previewParams' => [
                        'typeId' => $element->typeId,
                        'productId' => $element->id,
                        'siteId' => $element->siteId,
                    ]
                ];
            } else {
                if ($previewTargets = $element->getPreviewTargets() ?? []) {
                    if ($file->hasDraft() && $file->isComplete()) {
                        Craft::$app->getSession()->authorize("previewDraft:$file->draftId");
                    } else {
                        if ($element->getIsDraft()) {
                            Craft::$app->getSession()->authorize("previewDraft:$element->draftId");
                        } else {
                            Craft::$app->getSession()->authorize("previewElement:$element->id");
                        }
                    }
                }

                $settings = [
                    'canonicalId' => $element->getIsDraft() ? $element->getCanonicalId() : $element->id,
                    'elementType' => get_class($element),
                    'isLive' => !(($file->isComplete() && $file->hasDraft()) || $element->getIsDraft()),
                    'previewTargets' => $previewTargets,
                    'previewToken' => $security->generateRandomString() ?? null,
                    'siteId' => $file->targetSite,
                    'siteToken' => !$element->getSite()->enabled ? $security->hashData((string)$file->targetSite, '') : null,
                ];

                if ($file->isComplete() || $element->getIsDraft()) {
                    $settings['draftId'] = $file->isComplete() ? $file->draftId : $element->draftId;
                }
            }
        }

        return $settings;
    }

    /**
     * Return a preview setting array used for creating per entry preview
     *
     * @param \acclaro\translations\models\FileModel $file
     *
     *@return array $settings
     */
    public function getEntryPreviewSettings(FileModel $file)
    {
        $settings = [
            'id' => sprintf("filePreview-%s", $file->id),
            'elementType' => get_class($file->getElement(false))
        ];

        return $settings;
    }

    // Draft Actions

    public function getDraft(FileModel $file)
    {
        $draft = null;

        if ($file->draftId && $element = $file->getElement()) {
            switch (get_class($element)) {
                case GlobalSet::class:
                    $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($file->draftId);
                    break;
                case Category::class:
                    $draft = Translations::$plugin->categoryRepository->getDraftById($file->draftId, $file->targetSite);
                    break;
                case Asset::class:
                    $draft = Translations::$plugin->assetDraftRepository->getDraftById($file->draftId);
                    break;
                case Product::class:
                    $draft = Translations::$plugin->commerceRepository->getDraftById($file->draftId);
                    break;
                default:
                    $draft = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);
            }
        }

        return $draft;
    }

    public function createReferenceData(array $data, $meta, $forDownload = true)
    {
        $sourceLanguage = Craft::$app->sites->getSiteById($data['sourceElementSite'])->language;
        $targetLanguage = Craft::$app->sites->getSiteById($data['targetElementSite'])->language;
        
        $meta += [
            'sourceLang' => $sourceLanguage,
            'targetLang' => $targetLanguage,
        ];

        $source = json_decode(Translations::$plugin->elementToFileConverter->xmlToJson($data['sourceContent']), true)['content'] ?? [];
        
        $target = Translations::$plugin->elementTranslator->toTranslationSource(
            $data['targetElement'],
            $data['targetElementSite']
        );
        $tmContent = '';

        if ($forDownload) {
            switch ($data['format']) {
                case Constants::FILE_FORMAT_XML:
                    $tmContent = $this->referenceAsXml($source, $target, $meta);
                    break;
                case Constants::FILE_FORMAT_JSON:
                    $tmContent = $this->referenceAsJson($source, $target, $meta);
                    break;
                default:
                    $tmContent = $this->referenceAsCsv($source, $target, $meta);
            }
        } else {
            $tmContent = json_encode($this->referenceAsCsv($source, $target, $meta, $forDownload));
        }

        return $tmContent;
    }
    
    private function referenceAsCsv($source, $target, $meta, $forDownload = true)
    {
        $tmContent = [[$meta['targetLang']]];
        
        if ($forDownload) {
            $tmContent = sprintf('"key","%s","%s"', $meta['sourceLang'], $meta['targetLang']);
        }

        foreach ($source as $key => $value) {
            $targetValue = $target[$key] ?? '';
            if ($forDownload) {
                if ($value !== $targetValue) {
                    $tmContent .= "\n" . sprintf('"%s","%s","%s"', $key, $value, $targetValue);
                }
            } else {
                $tmContent[] = [$targetValue];
            }
        }

        return $tmContent;
    }
    
    private function referenceAsJson($source, $target, $meta)
    {
        $tmContent = [
            'orderId'           => $meta['orderId'],
            'elementId'         => $meta['elementId'],
            'entryTitle'        => $meta['entryTitle'],
            'entrySlug'         => $meta['entrySlug'],
            'dateCreated'       => $meta['dateCreated'],
            "source-language"   => $meta['sourceLang'],
            "target-language"   => $meta['targetLang'],
            "content"           => []
        ];

        foreach ($source as $key => $value) {
            $targetValue = $target[$key] ?? '';
            $tmContent['content'][$meta['sourceLang']][$key] = $value;
            $tmContent['content'][$meta['targetLang']][$key] = $targetValue;
        }

        return json_encode($tmContent);
    }
    
    private function referenceAsXml($source, $target, $meta)
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        
        $sourceLanguage = $meta['sourceLang'];
        $targetLanguage = $meta['targetLang'];
        $elementId      = $meta['elementId'];
        $orderId        = $meta['orderId'];
        $entryTitle     = $meta['entryTitle'];
        $entrySlug      = $meta['entrySlug'];
        $dateCreated    = $meta['dateCreated'];

        $dom->formatOutput = true;

        $xml = $dom->appendChild($dom->createElement('xml'));

        $head = $xml->appendChild($dom->createElement('head'));
        $langs = $head->appendChild($dom->createElement('langs'));
        $langs->setAttribute('source-language', $sourceLanguage);
        $langs->setAttribute('target-language', $targetLanguage);

        $meta = $head->appendChild($dom->createElement('meta'));
        $meta->setAttribute('elementId', $elementId);
        $meta->setAttribute('orderId', $orderId);
        $meta->setAttribute('entryTitle', $entryTitle);
        $meta->setAttribute('entrySlug', $entrySlug);
        $meta->setAttribute('dateCreated', $dateCreated);

        $body = $xml->appendChild($dom->createElement('body'));
        $sourceLang = $body->appendChild($dom->createElement('lang'));
        $sourceLang->setAttribute('source-language', $sourceLanguage);
        $targetLang = $body->appendChild($dom->createElement('lang'));
        $targetLang->setAttribute('target-language', $targetLanguage);

        foreach ($source as $key => $value) {
            $translation = $dom->createElement('content');

            $translation->setAttribute('resname', $key);

            // Does the value contain characters requiring a CDATA section?
            $text = 1 === preg_match('/[&<>]/', $value) ? $dom->createCDATASection($value) : $dom->createTextNode($value);

            $translation->appendChild($text);

            $sourceLang->appendChild($translation);
            
            $targetTranslation = $dom->createElement('content');
            $value = $target[$key] ?? '';

            $targetTranslation->setAttribute('resname', $key);

            // Does the value contain characters requiring a CDATA section?
            $text = 1 === preg_match('/[&<>]/', $value) ? $dom->createCDATASection($value) : $dom->createTextNode($value);

            $targetTranslation->appendChild($text);

            $targetLang->appendChild($targetTranslation);
        }

        return $dom->saveXML();
    }
}
