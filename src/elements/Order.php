<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\elements;

use Craft;
use craft\base\Element;
use craft\helpers\StringHelper;
use yii\validators\NumberValidator;
use craft\validators\DateTimeValidator;
use craft\validators\SiteIdValidator;
use craft\elements\db\ElementQueryInterface;

use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\base\AlertsTrait;
use acclaro\translations\records\OrderRecord;
use acclaro\translations\elements\db\OrderQuery;
use acclaro\translations\elements\actions\OrderDelete;
use acclaro\translations\elements\actions\OrderEdit;
use acclaro\translations\elements\actions\OrderRestore;
use craft\elements\User;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class Order extends Element
{
    use AlertsTrait;

    public $actionButton = true;

    protected $elementType = 'Order';

    protected $_elements = array();

    protected $_files;

    public $translatorId;

    public $ownerId;

    public $sourceSite;

    public $targetSites;

    public $status;

    public $statusColour;

    public $statusLabel;

    public $requestedDueDate;

    public $orderDueDate;

    public $comments;

    public $activityLog;

    public $dateOrdered;

    public $serviceOrderId;

    public $entriesCount;

    public $wordCount;

    public $elementIds;

    public $trackChanges;

	public $trackTargetChanges;

    public $includeTmFiles;

    public $asynchronousPublishing;

    public $requestQuote;

    public $tags;

    /**
     * Properties
     */
    public static function displayName(): string
    {
        // To Show Colum Header as Name inspite of order in index page table.
        if(debug_backtrace()[1]['function'] == 'getTableAttributes')
            return Translations::$plugin->translator->translate('app', 'Name');
        return Translations::$plugin->translator->translate('app', 'Order');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return StringHelper::toLowerCase(static::displayName());
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('app', 'Orders');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return StringHelper::toLowerCase(static::pluralDisplayName());
    }

    public static function refHandle(): ?string
    {
        return 'order';
    }

    public static function hasContent(): bool
    {
        return true;
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function isLocalized(): bool
    {
        return false;
    }

    public function canView(User $user): bool
    {
        return $user->can('translations:orders:create');
    }

    public function canSave(User $user): bool
    {
        return $user->can('translations:orders:edit');
    }

    public function canDelete(User $user): bool
    {
        return $user->can('translations:orders:delete');
    }

    /**
     * @inheritdoc
     */
    public static function trackChanges(): bool
    {
        return true;
    }

    public static function hasStatuses(): bool
    {
        return false;
    }

    protected static function defineActions(string $source): array
    {
        $actions = [OrderDelete::class, OrderEdit::class, OrderRestore::class];

        return $actions;
    }
    
    /**
     * @inheritdoc
     */
    public static function sources(string $context): array
    {
        return static::defineSources($context);;
    }

    protected static function defineSources(string $context): array
    {
        $sources = [
            [
                'key' => 'all',
                'label' => Translations::$plugin->translator->translate('app', 'All Orders'),
                'criteria' => [],
                'defaultSort' => ['dateOrdered', 'desc']
            ],
            [
                'key' => 'pending',
                'label' => Translations::$plugin->translator->translate('app', 'Pending'),
                'criteria' => [
                    'status' => [
                        Constants::ORDER_STATUS_PENDING
                    ]
                ],
                'defaultSort' => ['dateOrdered', 'desc']
            ],
			[
                'key' => 'new',
                'label' => Translations::$plugin->translator->translate('app', 'New'),
                'criteria' => [
                    'status' => [
                        Constants::ORDER_STATUS_NEW
                    ]
                ],
                'defaultSort' => ['dateOrdered', 'desc']
            ],
			[
                'key' => 'getting-quotes',
                'label' => Translations::$plugin->translator->translate('app', 'Getting quotes'),
                'criteria' => [
                    'status' => [
                        Constants::ORDER_STATUS_GETTING_QUOTE
                    ]
                ],
                'defaultSort' => ['dateOrdered', 'desc']
            ],
			[
                'key' => 'needs-approval',
                'label' => Translations::$plugin->translator->translate('app', 'Needs approval'),
                'criteria' => [
                    'status' => [
                        Constants::ORDER_STATUS_NEEDS_APPROVAL
                    ]
                ],
                'defaultSort' => ['dateOrdered', 'desc']
            ],
            [
                'key' => 'modified',
                'label' => Translations::$plugin->translator->translate('app', 'Modified'),
                'criteria' => [
                    'status' => [
                        Constants::ORDER_STATUS_MODIFIED
                    ]
                ],
                'defaultSort' => ['dateOrdered', 'desc']
            ],
            [
                'key' => 'in-progress',
                'label' => Translations::$plugin->translator->translate('app', 'In progress'),
                'criteria' => [
                    'status' => [
                        Constants::ORDER_STATUS_IN_PROGRESS,
                        Constants::ORDER_STATUS_IN_REVIEW,
                        Constants::ORDER_STATUS_IN_PREPARATION,
                    ]
                ],
                'defaultSort' => ['dateOrdered', 'desc']
            ],
            [
                'key' => 'ready-for-review',
                'label' => Translations::$plugin->translator->translate('app', 'Ready for review'),
                'criteria' => [
                    'status' => [
                        Constants::ORDER_STATUS_REVIEW_READY
                    ]
                ],
                'defaultSort' => ['dateOrdered', 'desc']
            ],
            [
                'key' => 'ready-to-apply',
                'label' => Translations::$plugin->translator->translate('app', 'Ready to apply'),
                'criteria' => [
                    'status' => [
                        Constants::ORDER_STATUS_COMPLETE
                    ]
                ],
                'defaultSort' => ['dateOrdered', 'desc']
            ],
            [
                'key' => 'applied',
                'label' => Translations::$plugin->translator->translate('app', 'Applied'),
                'criteria' => [
                    'status' => [
                        Constants::ORDER_STATUS_PUBLISHED
                    ]
                ],
                'defaultSort' => ['dateOrdered', 'desc']
            ],
            [
                'key' => 'failed',
                'label' => Translations::$plugin->translator->translate('app', 'Failed'),
                'criteria' => [
                    'status' => [
                        Constants::ORDER_STATUS_FAILED
                    ]
                ],
                'defaultSort' => ['dateOrdered', 'desc']
            ],
            [
                'key' => 'canceled',
                'label' => Translations::$plugin->translator->translate('app', 'Canceled'),
                'criteria' => [
                    'status' => [
                        Constants::ORDER_STATUS_CANCELED
                    ]
                ],
                'defaultSort' => ['dateOrdered', 'desc']
            ]
        ];

        return $sources;
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'serviceOrderId' => Craft::t('app', 'Order ID'),
            'ownerId' => Craft::t('app', 'Owner'),
            'entriesCount' => Craft::t('app', 'Entries'),
            'wordCount' => Craft::t('app', 'Words'),
            'translatorId' => Craft::t('app', 'Translator'),
            'targetSites' => Craft::t('app', 'Sites'),
            'status' => Craft::t('app', 'Status'),
            'dateOrdered' => Craft::t('app', 'Created'),
            'dateUpdated' => Craft::t('app', 'Updated'),
        ];
    }

    protected static function defineSearchableAttributes(): array
    {
        return [
            'title',
            'id',
            'status'
        ];
    }

    public function getTableAttributeHtml(string $attribute): string
    {
        $value = $this->$attribute;

        switch ($attribute) {
            case 'targetSites':
                $targetSites = $this->targetSites ? json_decode($this->targetSites, true) : array();
                $languages = '';
                $length = count($targetSites);
                foreach ($targetSites as $key => $site) {
                    if (($key+1) === $length) {
                        if (Craft::$app->getSites()->getSiteById($site)) {
                            $languages .= ucfirst(Translations::$plugin->siteRepository->getSiteLanguageDisplayName($site)). '<span class="light"> ('.Craft::$app->getSites()->getSiteById($site)->language.')</span>';
                        } else {
                            $languages .= '<s class="light">Deleted</s>';
                        }
                    } else {
                        if (Craft::$app->getSites()->getSiteById($site)) {
                            $languages .= ucfirst(Translations::$plugin->siteRepository->getSiteLanguageDisplayName($site)). '<span class="light"> ('.Craft::$app->getSites()->getSiteById($site)->language.')</span>'. ', ';
                        } else {
                            $languages .= '<s class="light">Deleted</s>'. ', ';
                        }
                    }
                }

                return $languages;

            case 'title':
            case 'wordCount':
                return $value ? $value : '';
            case 'entriesCount':
                return count($this->getElements());
            case 'serviceOrderId':
                if (!$value && ( !is_null($this->getTranslator()) && $this->getTranslator()->service !== Constants::TRANSLATOR_DEFAULT))
                {
                    return '';
                }

                $translationService = $this->getTranslationService();

                return $translationService->getOrderUrl($this);
            case 'status':
                $html = sprintf(
                    "<span class='status %s'></span>%s",
                    $this->getStatusColour(),
                    Translations::$plugin->translator->translate('app', $this->getStatusLabel())
                ) . $this->getTargetAlertHtml();

                return $html;
            case 'orderDueDate':
            case 'requestedDueDate':
            case 'dateOrdered':
                return $value ? date('n/j/y', strtotime($value)) : '--';

            case 'dateUpdated':
                return $value ? date('n/j/y', strtotime($value->format('Y-m-d H:i:s'))) : '--';

            case 'ownerId':
                return $this->getOwner() ? $this->getOwner()->username : '';

            case 'translatorId':
                if (!$this->getTranslator()) {
                    return 'N/A';
                }
                return $this->getTranslator()->label ? $this->getTranslator()->label : $this->getTranslator()->service;
        }

        return parent::getTableAttributeHtml($attribute);
    }

    protected static function defineTableAttributes(): array
    {
        $attributes = [
            // 'serviceOrderId' => ['label' => Translations::$plugin->translator->translate('app', 'ID')],
            'ownerId' => ['label' => Translations::$plugin->translator->translate('app', 'Owner')],
            'entriesCount' => ['label' => Translations::$plugin->translator->translate('app', 'Entries')],
            'wordCount' => ['label' => Translations::$plugin->translator->translate('app', 'Words')],
            'status' => ['label' => Translations::$plugin->translator->translate('app', 'Status')],
            'translatorId' => ['label' => Translations::$plugin->translator->translate('app', 'Translator')],
            'targetSites' => ['label' => Translations::$plugin->translator->translate('app', 'Sites')],
            'dateOrdered' => ['label' => Translations::$plugin->translator->translate('app', 'Created')],
            'dateUpdated' => ['label' => Translations::$plugin->translator->translate('app', 'Updated')],
        ];

        return $attributes;
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'status',
            'translatorId',
            'dateOrdered',
            'dateUpdated'
        ];
    }

    public function criteriaAttributes()
    {
        return [
            'sourceSite'    => $this->string()->notNull()->defaultValue(''),
            'targetSites'   => $this->string()->notNull()->defaultValue(''),
            'status' => $this->enum('values', Constants::ORDER_STATUSES)->defaultValue(Constants::ORDER_STATUS_PENDING),
        ];
    }

    public function getTargetAlertHtml() {
        $html = '';
        if (!($this->isPublished() || $this->isCanceled()) && $this->trackTargetChanges && $this->isTmMisaligned()) {
            $html .= '<span class="nowrap pl-5"><span class="warning order-warning font-size-15" data-icon="alert"> This order contains misaligned content that might affect translation memory accuracy. </span></span>';
        }

        return $html;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['translatorId', 'ownerId', 'sourceSite', 'targetSites', 'activityLog', 'entriesCount', 'wordCount', 'elementIds',],'required'];
        $rules[] = [['sourceSite'], SiteIdValidator::class];
        $rules[] = [['wordCount', 'entriesCount'], NumberValidator::class];
        $rules[] = ['status', 'default', 'value' => 'pending'];
        $rules[] = ['sourceSite', 'default', 'value' => ''];
        $rules[] = ['targetSites', 'default', 'value' => ''];
        $rules[] = ['serviceOrderId', 'default', 'value' => ''];
        $rules[] = ['dateOrdered', DateTimeValidator::class];
        $rules[] = ['elementIds', 'default', 'value' => ''];

        return $rules;
    }

    public static function find(): ElementQueryInterface
    {
        return new OrderQuery(get_called_class());
    }

    /**
     * Requests
     */
    public function getElements()
    {
        $elementIds = $this->elementIds ? json_decode($this->elementIds) : array();

        foreach ($elementIds as $key => $elementId) {
            if (!array_key_exists($elementId, $this->_elements)) {
                $element = Translations::$plugin->elementRepository->getElementById($elementId, $this->sourceSite);

                $element ? $this->_elements[$elementId] = $element : '';
            }
        }

        return $this->_elements;
    }

    /**
     * Returns array of element ids for an order. Ignores elements that are deleted
     *
     * @return array
     */
    public function getElementsId()
    {
        return array_keys($this->getElements());
    }

    /**
     * User in order details settings tab entry table
     */
    public function getEntryPreviewSettings($element)
    {
        $settings = [
            'elementType' => get_class($element)
        ];

        if ($element->getIsDraft()) {
            $settings['id'] = sprintf('entryPreview-%s', $element->getCanonicalId());
            $settings['draftId'] = $element->draftId;
        } else {
            $settings['id'] = sprintf('entryPreview-%s', $element->id);
        }

        return $settings;
    }

    public function getUrl(): ?string
    {
        return Constants::URL_ORDER_DETAIL . $this->id;
    }

    /**
     * Returns files associated with order
     *
     * @return \acclaro\translations\models\FileModel[]
     */
    public function getFiles()
    {
        if (is_null($this->_files)) {
            $this->_files = Translations::$plugin->fileRepository->getFiles($this->id);
        }

        return $this->_files;
    }

    /**
     * Checks if any file in order is complete
     *
     * @return bool
     */
    public function hasCompletedFiles()
    {
        foreach ($this->getFiles() as $file) {
			if ($file->isComplete() || $file->isPublished()) return true;
		}

		return false;
    }

    /**
     * Used to enable or disable sekect all checkbox in files tab
     *
     * @return bool
     */
    public function canEnableFilesCheckboxes()
    {
        foreach ($this->getFiles() as $file) {
			if ($file->canEnableFilesCheckboxes()) return true;
		}

		return false;
    }

	/**
     * Returns files in fileIds associated with order
     *
     * @return \acclaro\translations\models\FileModel[]
     */
    public function getFilesById($fileIds)
    {
		$files = Translations::$plugin->fileRepository->getFiles($this->id);
		$result = [];
		foreach ($files as $file) {
			if (in_array($file->id, $fileIds)) $result[$file->id] = $file;
		}

        return $result;
    }

    public function getTranslator()
    {
        $translator = $this->translatorId ? Translations::$plugin->translatorRepository->getTranslatorById($this->translatorId) : null;

        return $translator;
    }

    public function getOwner()
    {
        $owner = $this->ownerId ? Translations::$plugin->userRepository->getUserById($this->ownerId) : null;

        return $owner;
    }

	public function getTags()
	{
        return Translations::$plugin->tagRepository->getOrderTags($this);
	}

    public function getTargetSitesArray()
    {
        return $this->targetSites ? json_decode($this->targetSites, true) : array();
    }

    public function getActivityLogArray()
    {
        $str = $this->activityLog;

        return $str ? json_decode($str, true) : array();
    }

    public function logActivity($message)
    {
        $activityLog = $this->getActivityLogArray();

        $activityLog[] = array(
            'date' => date('n/j/Y'),
            'message' => $message,
        );

        $this->activityLog = json_encode($activityLog);
    }

    public function getCpEditUrl(): ?string
    {
        return Translations::$plugin->urlHelper->cpUrl(Constants::URL_ORDER_DETAIL.$this->id);
    }

    public function getStatusLabel()
    {
        $statusLabel = '';
        switch ($this->status) {
            case Constants::ORDER_STATUS_PENDING:
                $statusLabel = 'Pending submission';
                break;
            case Constants::ORDER_STATUS_MODIFIED:
                $statusLabel = 'Modified';
                break;
            case Constants::ORDER_STATUS_GETTING_QUOTE:
                $statusLabel = 'Getting quote';
                break;
            case Constants::ORDER_STATUS_NEEDS_APPROVAL:
                $statusLabel = 'Needs approval';
                break;
            case Constants::ORDER_STATUS_IN_PROGRESS:
            case Constants::ORDER_STATUS_IN_REVIEW:
                $statusLabel = 'In progress';
                break;
            case Constants::ORDER_STATUS_REVIEW_READY:
                $statusLabel = 'Ready for review';
                break;
            case Constants::ORDER_STATUS_COMPLETE:
                $statusLabel = 'Ready to apply';
                break;
            case Constants::ORDER_STATUS_CANCELED:
                $statusLabel = 'Canceled';
                break;
            case Constants::ORDER_STATUS_PUBLISHED:
                $statusLabel = 'Applied';
                break;
            case Constants::ORDER_STATUS_FAILED:
                $statusLabel = 'Failed';
                break;
            default :
                $statusLabel = 'New';
        }

        $this->statusLabel = $this->statusLabel ?? $statusLabel;
        return $this->statusLabel;
    }

    public function isTmMisaligned($ignoreNew = true)
    {
        if (!$this->hasDefaultTranslator() && ($this->isComplete() || $this->isReviewReady() || $this->isPublished())) {
            return false;
        }

        foreach ($this->getFiles() as $file) {
            if ($file->isPublished() || ($ignoreNew && $file->isNew())) continue;

            if ($file->hasTmMisalignments(!$ignoreNew)) return true;
        }

        return false;
    }

    public function getStatusColour()
    {
        switch ($this->status) {
            case Constants::ORDER_STATUS_MODIFIED:
                $statusColour = 'purple';
                break;
            case Constants::ORDER_STATUS_GETTING_QUOTE:
                $statusColour = 'status-getting-quote';
                break;
            case Constants::ORDER_STATUS_NEEDS_APPROVAL:
                $statusColour = 'status-needs-approval';
                break;
            case Constants::ORDER_STATUS_IN_PROGRESS:
            case Constants::ORDER_STATUS_IN_REVIEW:
                $statusColour = 'orange';
                break;
            case Constants::ORDER_STATUS_REVIEW_READY:
                $statusColour = 'yellow';
                break;
            case Constants::ORDER_STATUS_COMPLETE:
                $statusColour = 'blue';
                break;
            case Constants::ORDER_STATUS_PUBLISHED:
                $statusColour = 'green';
                break;
            case Constants::ORDER_STATUS_CANCELED:
            case Constants::ORDER_STATUS_FAILED:
                $statusColour = 'red';
                break;
            default :
                $statusColour = '';
        }

        $this->statusColour = $this->statusColour ?? $statusColour;
        return $this->statusColour;
    }

	public function hasDefaultTranslator()
	{
		$response = false;

		if ($translator = $this->getTranslator()) {
			$response = $translator->service === Constants::TRANSLATOR_DEFAULT;
		}

		return $response;
	}

	public function hasTranslator($service)
	{
		$response = false;

		if ($translator = $this->getTranslator()) {
			$response = $translator->service === $service;
		}

		return $response;
	}

    public function shouldTrackSourceContent()
    {
        if (!$this->id) {
            return Translations::getInstance()->settings->trackSourceChanges;
        }

        return $this->trackChanges;
    }

    public function shouldTrackTargetContent()
    {
        if (! $this->id) return Translations::getInstance()->settings->trackTargetChanges;

        return $this->trackTargetChanges;
    }

    /**
     * Check if the order should be processed using queue
     */
    public function shouldProcessByQueue(): bool
    {
        return ($this->wordCount * count($this->getTargetSitesArray())) > Constants::WORD_COUNT_LIMIT || $this->hasTranslator(Constants::TRANSLATOR_GOOGLE);
    }

    /**
     * Create translation service calss based on translator
     */
    public function getTranslationService()
    {
        return Translations::$plugin->translatorFactory->makeTranslationService($this->getTranslator()?->service, $this->getTranslator()?->getSettings());
    }

	public function isExportImportAllowed()
	{
		if ($this->isPublished() && !$this->hasDefaultTranslator()) return false;

		return !$this->isPending() && !$this->isFailed();
	}

    public function isNew()
    {
        return $this->status === Constants::ORDER_STATUS_NEW;
    }

    public function isInPreparation()
    {
        return $this->status === Constants::ORDER_STATUS_IN_PREPARATION;
    }

    public function isPending()
    {
        return $this->status === Constants::ORDER_STATUS_PENDING;
    }

    public function isFailed()
    {
        return $this->status === Constants::ORDER_STATUS_FAILED;
    }

    public function isCanceled()
    {
        return $this->status === Constants::ORDER_STATUS_CANCELED;
    }

    public function isModified()
    {
        return $this->status === Constants::ORDER_STATUS_MODIFIED;
    }

    public function isReviewReady()
    {
        return $this->status === Constants::ORDER_STATUS_REVIEW_READY;
    }

    public function isComplete()
    {
        return $this->status === Constants::ORDER_STATUS_COMPLETE;
    }

    public function isGettingQuote()
    {
        return $this->status === Constants::ORDER_STATUS_GETTING_QUOTE;
    }

    public function isAwaitingApproval()
    {
        return $this->status === Constants::ORDER_STATUS_NEEDS_APPROVAL;
    }

    public function isPublished()
    {
        return $this->status === Constants::ORDER_STATUS_PUBLISHED;
    }

    public function requestQuote()
    {
        return $this->requestQuote;
    }

    public function shouldIncludeTmFiles()
    {
        return (bool) $this->includeTmFiles;
    }

    /**
     * Events
     */
    public function beforeSave(bool $isNew): bool
    {
        return true;
    }

    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = OrderRecord::findOne($this->id);
            if (!$record) {
                throw new \Exception('Invalid entry ID: ' . $this->id);
            }
        } else {
            $record = new OrderRecord();
            $record->id = $this->id;
        }

        $record->translatorId =  $this->translatorId;
        $record->ownerId =  $this->ownerId;
        $record->sourceSite =  $this->sourceSite;
        $record->targetSites =  $this->targetSites;
        $record->status =  $this->status;
        $record->requestedDueDate =  $this->requestedDueDate;
        $record->orderDueDate =  $this->orderDueDate;
        $record->comments =  $this->comments;
        $record->activityLog =  $this->activityLog;
        $record->dateOrdered =  $this->dateOrdered;
        $record->serviceOrderId =  $this->serviceOrderId;
        $record->entriesCount =  $this->entriesCount;
        $record->wordCount =  $this->wordCount;
        $record->elementIds =  $this->elementIds;
        $record->tags =  $this->tags;
        $record->trackChanges =  $this->trackChanges;
		$record->trackTargetChanges =  $this->trackTargetChanges;
		$record->includeTmFiles =  $this->includeTmFiles;
		$record->requestQuote =  $this->requestQuote;

        $record->save(false);

        parent::afterSave($isNew);
    }

    public function beforeDelete(): bool
    {
        return true;
    }

    public function afterDelete(): void
    {
    }
}
