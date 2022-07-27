<?php

namespace acclaro\translations\services;

use Craft;
use craft\base\ElementExporter;
use acclaro\translations\Translations;
use craft\base\EagerLoadingFieldInterface;
use craft\elements\db\ElementQueryInterface;

class Exporter extends ElementExporter
{
    protected $type;

    protected $elementsTitle;

    protected $allSites;

    protected null|array $selectedOrderIds = null;

    protected $allTranslators;

    protected $keyMap = [
        'id', 'title', 'translator', 'owner', 'sourceSite', 'targetSites', 'status',
        'requestedDueDate', 'comments', 'activityLog', 'dateOrdered',
        'entriesCount', 'wordCount', 'elements','previewUrls',
    ];

    /**
     * Exporter constructor.
     * @param $type
     */
    public function __construct($type)
    {
        $this->type = $type;
        $this->allSites = Craft::$app->getSites()->getAllSites();
        $this->allTranslators = Translations::$plugin->translatorRepository->getTranslatorOptions();
    }

    /**
     * @param  ElementQueryInterface  $query
     * @return array|callable|resource|string
     */
    public function export(ElementQueryInterface $query): mixed
    {
        $this->setSitesArray();

        $eagerLoadableFields = [];
        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            if ($field instanceof EagerLoadingFieldInterface) {
                $eagerLoadableFields[] = $field->handle;
            }
        }

        $data = [];

        /** @var ElementQuery $query */
        $query->with($eagerLoadableFields);

        foreach ($query->each() as $element) {
            if ($this->selectedOrderIds && !in_array($element->id, $this->selectedOrderIds)) continue;
            // Get the basic array representation excluding custom fields
            $attributes = array_flip($element->attributes());

            if (($fieldLayout = $element->getFieldLayout()) !== null) {
                foreach ($fieldLayout->getFields() as $field) {
                    unset($attributes[$field->handle]);
                }
            }
            $elementArr = $element->toArray(array_keys($attributes));
            if ($fieldLayout !== null) {
                foreach ($fieldLayout->getFields() as $field) {
                    $value = $element->getFieldValue($field->handle);
                    $elementArr[$field->handle] = $field->serializeValue($value, $element);
                }
            }
            $this->getElementsName($element['elementIds'], $element->sourceSite);
            $elementArr['previewUrls'] = $this->getPreviewUrls($element);
            $elementArr['elements'] = json_encode(array_values($this->elementsTitle));

            $elementArr['sourceSite'] = $this->getSourceSiteName($element['sourceSite']);
            $elementArr['targetSites'] = json_encode($this->getTargetSitesName($element['targetSites']));
            $elementArr['owner'] = json_encode($this->getOwnerName($element['ownerId']));
            $elementArr['translator'] = json_encode($this->getTranslatorName($element['translatorId']));

            $data[] = $elementArr;

            $this->elementsTitle = [];
        }

        return $this->filterResponseData($data);
    }

    public function setOrderIds($ids)
    {
        $this->selectedOrderIds = $ids;
    }

    /**
     * @param $data
     * @return array
     */
    protected function filterResponseData($data)
    {

        $rawData = [];
        foreach ($data as $order) {
            if ($this->type !== 'Expanded') {
                $order = array_intersect_key($order, array_flip($this->keyMap));
            }

            $attributeArr = [];
            foreach ($order as $key => $value){
                $attributeArr[ucfirst($key)] = $value;
            }
            $rawData[] = $attributeArr;
        }

        return $rawData;
    }

    /**
     * @param $ids
     * @return array
     */
    protected function getTargetSitesName($ids)
    {
        $targetLanguages = [];
        $ids = json_decode($ids, true);
        foreach ($ids as $langId) {
            $targetLanguages[] = $this->allSites[$langId];
        }

        return $targetLanguages;
    }

    /**
     * @param $id
     * @return \craft\models\Site|mixed|string
     */
    protected function getSourceSiteName($id)
    {
        return !empty($this->allSites[$id]) ? $this->allSites[$id] : '';
    }

    protected function getTranslatorName($id)
    {
        return isset($this->allTranslators[$id]) ? $this->allTranslators[$id] : null;
    }

    protected function getOwnerName($id)
    {
        return Translations::$plugin->userRepository->getUserById($id)['username'];
    }

    /**
     * @param $elementIds
     * @param  null  $sourceSite
     */
    protected function getElementsName($elementIds, $sourceSite=null)
    {
        $elementIds = json_decode($elementIds, true);
        foreach ($elementIds as $elementId){
            $element = Translations::$plugin->elementRepository->getElementById($elementId, $sourceSite);
            $this->elementsTitle[$elementId] = is_object($element) ? $element->title : 'N/A';
        }

    }

    /**
     * @param $order
     * @return array
     */
    protected function getPreviewUrls($order)
    {
        $urls = [];

        foreach ($order->files as $file) {
            if (!empty($this->elementsTitle[$file->elementId]) && $this->allSites[$file->targetSite]) {
                $urls[$this->elementsTitle[$file->elementId]][$this->allSites[$file->targetSite]] = $file->previewUrl;
            }
        }

        return $urls;
    }

    /**
     * Set sites array
     */
    private function setSitesArray() {
        $sitesArr = [];
        foreach($this->allSites as $site){
            $sitesArr[$site->id] = $site->language;
        }

        $this->allSites = $sitesArr;
    }
}
