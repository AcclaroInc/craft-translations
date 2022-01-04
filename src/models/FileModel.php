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
use craft\validators\SiteIdValidator;
use craft\validators\DateTimeValidator;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class FileModel extends Model
{

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

    public function init()
    {
        parent::init();

        $this->status = $this->status ? : Constants::FILE_STATUS_NEW;
        $this->sourceSite = $this->sourceSite ?: '';
        $this->targetSite = $this->targetSite ?: '';
    }

    public function rules()
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
                return '';
        }
    }

    public function hasDraft()
    {
        return $this->draftId ?: null;
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

    public function isComplete()
    {
        return $this->status === Constants::FILE_STATUS_COMPLETE;
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
		if ($this->isComplete()) {
			return Translations::$plugin->orderRepository->getFileTitle($this);
		}
		if ($element = $this->getElement($this->isPublished())) {
			if (isset($element->title)) return $element->title;
			if (isset($element->name)) return $element->name;
		}
		return 'Not Found!';
	}

	public function getPreviewUrl()
	{
		$previewUrl = Translations::$plugin->urlGenerator->generateFileWebUrl($this->getElement($this->isPublished()), $this);

		if ($this->isPublished()) return $previewUrl;

		return $this->previewUrl ?? $previewUrl;
	}

	public function hasSourceTargetDiff()
	{
		$hasDiff = false;
		if ($this->isReviewReady() || $this->isComplete() || $this->isPublished()) {
			$hasDiff = (bool) Translations::$plugin->fileRepository->getSourceTargetDifferences(
				$this->source, $this->target);
		}

		return $hasDiff;
	}

	public function getElement($isApplied = null)
	{
		$site = $isApplied ? $this->targetSite : $this->sourceSite;
		$element = Craft::$app->getElements()->getElementById($this->elementId, null, $site);
		if ($isApplied && $element->getIsDraft()) {
			$element = $element->getCanonical();
		}

		return $element;
	}
}
