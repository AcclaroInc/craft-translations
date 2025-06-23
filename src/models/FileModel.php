<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\models;

use Craft;
use craft\base\Model;
use yii\validators\NumberValidator;
use acclaro\translations\Constants;
use acclaro\translations\Translations;
use craft\elements\Asset;
use craft\elements\GlobalSet;
use craft\helpers\ElementHelper;
use craft\validators\SiteIdValidator;
use craft\validators\DateTimeValidator;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class FileModel extends Model
{
    /**
     * @var \acclaro\translations\services\repository\FileRepository $_service
     */
    private $_service;

    public $id;

    public $orderId;

    public $elementId;

    public $draftId;

    public $sourceSite;

    public $targetSite;

    public $status;

    public $wordCount;

    public $source;

    public $target;

    public $previewUrl;

    public $serviceFileId;

    public $dateUpdated;

    public $dateDelivered;

    public $dateDeleted;

    public $reference;

    public $draftReference;

    public function init(): void
    {
        parent::init();

        $this->_service = Translations::$plugin->fileRepository;

        $this->status = $this->status ? : Constants::FILE_STATUS_NEW;
        $this->sourceSite = $this->sourceSite ?: '';
        $this->targetSite = $this->targetSite ?: '';
    }

    public function rules(): array
    {
        return [
            [['orderId', 'elementId', 'draftId', 'sourceSite', 'targetSite'], 'required'],
            [['sourceSite', 'targetSite'], SiteIdValidator::class],
            ['wordCount', NumberValidator::class],
            [['dateCreated', 'dateUpdated', 'dateDelivered', 'dateDeleted'], DateTimeValidator::class],
        ];
    }

    public function getStatusLabel()
    {
        switch ($this->status) {
            case Constants::FILE_STATUS_MODIFIED:
                return 'Modified';
            case Constants::FILE_STATUS_PREVIEW:
            case Constants::FILE_STATUS_IN_PROGRESS:
                return 'In progress';
            case Constants::FILE_STATUS_REVIEW_READY:
                return 'Ready for review';
            case Constants::FILE_STATUS_COMPLETE:
                return 'Ready to apply';
            case Constants::FILE_STATUS_CANCELED:
                return 'Canceled';
            case Constants::FILE_STATUS_PUBLISHED:
                return 'Applied';
            case Constants::FILE_STATUS_FAILED:
                return 'Failed';
            default :
                return 'New';
        }
    }

    public function getStatusColor()
    {
        switch ($this->status) {
            case Constants::FILE_STATUS_MODIFIED:
                return 'purple';
            case Constants::FILE_STATUS_PREVIEW:
            case Constants::FILE_STATUS_IN_PROGRESS:
                return 'orange';
            case Constants::FILE_STATUS_REVIEW_READY:
                return 'yellow';
            case Constants::FILE_STATUS_COMPLETE:
                return 'blue';
            case Constants::FILE_STATUS_FAILED:
            case Constants::FILE_STATUS_CANCELED:
                return 'red';
            case Constants::FILE_STATUS_PUBLISHED:
                return 'green';
            default:
                return 'disabled';
        }
    }

    public function hasDraft()
    {
        return $this->_service->getDraft($this);
    }

    public function isNew()
    {
        return $this->status === Constants::FILE_STATUS_NEW;
    }

    public function isModified()
    {
        return $this->status === Constants::FILE_STATUS_MODIFIED;
    }

    public function isCanceled()
    {
        return $this->status === Constants::FILE_STATUS_CANCELED;
    }

    public function isFailed()
    {
        return $this->status === Constants::FILE_STATUS_FAILED;
    }

    public function isComplete()
    {
        return $this->status === Constants::FILE_STATUS_COMPLETE;
    }

    public function isInProgress()
    {
        return $this->status === Constants::FILE_STATUS_IN_PROGRESS;
    }

    public function isReviewReady()
    {
        return $this->status === Constants::FILE_STATUS_REVIEW_READY;
    }

    public function isPublished()
    {
        return $this->status === Constants::FILE_STATUS_PUBLISHED;
    }

	public function getCpEditUrl()
	{
		return Translations::$plugin->urlGenerator->generateFileUrl($this->getElement(), $this);
	}

	public function getUiLabel()
	{
        if ($this->isComplete() && $this->hasDraft()) {
			return Translations::$plugin->orderRepository->getFileTitle($this);
		}
		if ($element = $this->getElement($this->isPublished())) {
			if (isset($element->title)) return $element->title;
			if (isset($element->name)) return $element->name;
		}
		return 'Not Found!';
	}

    public function hasPreview()
    {
        $element = $this->getElement();

        return $element instanceof (Constants::CLASS_ENTRY) || $element instanceof (Constants::CLASS_CATEGORY) || $element instanceof (Constants::CLASS_COMMERCE_PRODUCT);
    }

    public function hasReference()
    {
        return !!($this->isComplete() ? $this->draftReference : $this->reference);
    }
    
    public function getOrder()
    {
        return Translations::$plugin->orderRepository->getOrderById($this->orderId);
    }
    
    public function getTranslator()
    {
        return $this->getOrder()->getTranslator();
    }
    
    public function canEnableFilesCheckboxes()
    {
        switch ($this->getTranslator()?->service) {
            case Constants::TRANSLATOR_GOOGLE:
                return $this->isNew() || $this->isInProgress() || $this->isModified() || $this->isReviewReady() ||
                    $this->isComplete() || $this->isPublished();
            default:
                return $this->isReviewReady() || $this->isComplete() || $this->isPublished();
        }
    }

    public function canBeCheckedForTargetChanges()
    {
        return ! ($this->isPublished() || $this->isCanceled() || $this->isFailed());
    }

    public function getSourceLangCode()
    {
        return Translations::$plugin->siteRepository->getSiteCode($this->sourceSite);
    }

    public function getTargetLangCode()
    {
        return Translations::$plugin->siteRepository->getSiteCode($this->targetSite);
    }

	public function getPreviewUrl()
	{
        $previewUrl = Translations::$plugin->urlGenerator->generateFileWebUrl($this->getElement(), $this);

		if ($this->isPublished()) return $previewUrl;

		return $this->previewUrl ?? $previewUrl;
	}

	public function hasSourceTargetDiff()
	{
		$hasDiff = false;
		if (!empty($this->target) && ($this->isReviewReady() || $this->isComplete() || $this->isPublished())) {
			$hasDiff = (bool) Translations::$plugin->fileRepository->getSourceTargetDifferences(
				$this->source, $this->target);
		}

		return $hasDiff;
	}

    public function getElement($canonical = true)
	{
        $element = Translations::$plugin->elementRepository->getElementById($this->elementId, $this->targetSite);

		if (! $element) {
            $element = Translations::$plugin->elementRepository->getElementById($this->elementId, $this->sourceSite);
		}

        /** Check element as an entry could have been deleted */
        if ($element && $canonical && $element->getIsDraft()) {
			$element = $element->getCanonical();
		}

		return $element;
	}

    public function getFilePreviewSettings()
    {
        return $this->_service->getFilePreviewSettings($this);
    }

    public function getEntryPreviewSettings()
    {
        return $this->_service->getEntryPreviewSettings($this);
    }

    public function hasTmMisalignments()
    {
        if ($this->isPublished() || $this->isCanceled() || $this->isFailed()) {
            return false;
        }

        /**
         * Will only be triggered for existing drafts that existed before the
         * draftReference column was added to the translations_files table.
         * No target changes will be detected at this point
         */
        if ($this->isComplete() && is_null($this->draftReference)) {
            $this->draftReference = $this->getReferenceFileContent();
            Translations::$plugin->fileRepository->saveFile($this);
            return false;
        }

        $dbVersion =  $this->isComplete() ? $this->draftReference : $this->reference;

        if (!!$dbVersion && Translations::$plugin->elementToFileConverter->isValidXml($dbVersion)) {
            $currentVersion = $this->getReferenceFileContent();
            return $this->_service->getIsContentChanged($dbVersion, $currentVersion);
        }

        return false;
    }

    public function getReferenceFileName($format = Constants::FILE_FORMAT_CSV)
    {
        $element = Translations::$plugin->elementRepository->getElementById($this->elementId, $this->sourceSite);

        if ($element instanceof GlobalSet) {
            $entrySlug= ElementHelper::normalizeSlug($element->name);
        } else if ($element instanceof Asset) {
            $assetFilename = $element->getFilename();
            $fileInfo = pathinfo($assetFilename);
            $entrySlug= basename($assetFilename, '.' . $fileInfo['extension']);
        } else {
            $entrySlug= $element->slug;
        }

        $targetLang = Translations::$plugin->siteRepository->normalizeLanguage(Craft::$app->getSites()->getSiteById($this->targetSite)->language);
        $filename = sprintf('%s-%s_%s_%s_TM.%s',$this->elementId, $entrySlug, $targetLang, date("Ymd\THi"), $format);

        return $filename;
    }

    public function getReferenceFileContent()
    {
        $targetEntry = Translations::$plugin->elementRepository->getElementById($this->elementId, $this->targetSite);

        if ($this->isComplete()) {
            $targetEntry = $this->hasDraft();
        }

        if (!$targetEntry) {
            return null;
        }

        $fileData = [
            'sourceSite'    => $this->sourceSite,
            'targetSite'    => $this->targetSite,
            'wordCount'     => $this->wordCount,
            'orderId'       => $this->orderId
        ];

        return Translations::$plugin->elementToFileConverter->convert(
            $targetEntry, Constants::FILE_FORMAT_XML, $fileData
        );
    }
}
