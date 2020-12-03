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
use DateTime;
use craft\base\Model;
use craft\base\Element;
use craft\helpers\ElementHelper;
use craft\elements\db\ElementQuery;
use yii\validators\NumberValidator;
use craft\validators\StringValidator;
use craft\validators\UniqueValidator;
use craft\validators\DateTimeValidator;
use craft\validators\SiteIdValidator;
use craft\elements\db\ElementQueryInterface;
use acclaro\translations\services\App;
use acclaro\translations\elements\Order;
use acclaro\translations\records\OrderRecord;
use acclaro\translations\Translations;
use acclaro\translations\elements\db\OrderQuery;


/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class Order extends Element
{
    public $actionButton = true;
    
    protected $elementType = 'Order';
    
    protected $_elements = array();
    
    protected $_files;

    public $id;
    
    public $title;

    public $translatorId;
    
    public $ownerId;
    
    public $sourceSite;
    
    public $targetSites;
    
    public $status;
    
    public $requestedDueDate;
    
    public $comments;
    
    public $activityLog;
    
    public $dateOrdered;

    public $dateUpdated;
    
    public $serviceOrderId;
    
    public $entriesCount;
    
    public $wordCount;
    
    public $elementIds;
    
    public $siteId;

    /**
     * Properties
     */
    public static function displayName(): string
    {
        return Translations::$plugin->translator->translate('app', 'Order');
    }

    public static function refHandle()
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

    public function getIsEditable(): bool
    {
        return false;
    }

    public static function hasStatuses(): bool
    {
        return false;
    }

    /**
     * Transition to this instead of custom statuses
     */
    // public static function statuses(): array
    // {
    //     return [
    //         'new' => Translations::$plugin->translator->translate('app', 'Pending submission'),
    //         'in progress' => [
    //             'label' => Translations::$plugin->translator->translate('app', 'In progress'),
    //             'color' => 'orange'
    //         ],
    //         'complete' => [
    //             'label' => Translations::$plugin->translator->translate('app', 'Ready to update'),
    //             'color' => 'blue'
    //         ],
    //         'canceled' => [
    //             'label' => Translations::$plugin->translator->translate('app', 'Canceled'),
    //             'color' => 'red'
    //         ],
    //         'published' => [
    //             'label' => Translations::$plugin->translator->translate('app', 'Complete'),
    //             'color' => 'green'
    //         ],
    //     ];
    // }

    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => 'all',
                'label' => Translations::$plugin->translator->translate('app', 'All Orders'),
                'criteria' => [],
                'defaultSort' => ['dateOrdered', 'desc']
            ],
            [
                'key' => 'in-progress',
                'label' => Translations::$plugin->translator->translate('app', 'Orders in progress'),
                'criteria' => [
                    'status' => [
                        'new', 'in progress', 'in review', 'in preparation', 'getting quote', 'needs approval', 'complete'
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
            case 'entriesCount':
            case 'wordCount':
                return $value ? $value : '';

            case 'serviceOrderId':
                if (!$value && ( !is_null($this->getTranslator()) && $this->getTranslator()->service !== 'export_import'))
                {
                    return '';
                }

                $translator = $this->getTranslator();

                if (!$translator) 
                {
                    return $value ? $value : sprintf('#%s', $this->id);
                }

                if ($this->getTranslator()->service === 'export_import')
                {
                    return  sprintf('#%s', $this->id);
                }

                $translationService = Translations::$plugin->translatorFactory->makeTranslationService($translator->service, json_decode($translator->settings, true));

                return sprintf('<a href="%s" target="_blank">#%s</a>', $translationService->getOrderUrl($this), $value);

            case 'status':
            switch ($this->statusLabel) {
                case 'Order failed':
                    return '<span class="status red"></span>'.Translations::$plugin->translator->translate('app', $this->statusLabel);
                case 'Pending submission':
                    return '<span class="status"></span>'.Translations::$plugin->translator->translate('app', $this->statusLabel);
                case 'In progress':
                    return '<span class="status orange"></span>'.Translations::$plugin->translator->translate('app', $this->statusLabel);
                case 'In review':
                    return '<span class="status yellow"></span>'.Translations::$plugin->translator->translate('app', $this->statusLabel);
                case 'Ready to apply':
                    return '<span class="status blue"></span>'.Translations::$plugin->translator->translate('app', $this->statusLabel);
                case 'Cancelled':
                    return '<span class="status red"></span>'.Translations::$plugin->translator->translate('app', $this->statusLabel);
                case 'Applied':
                    return '<span class="status green"></span>'.Translations::$plugin->translator->translate('app', $this->statusLabel);
            }

            case 'requestedDueDate':
            case 'dateOrdered':
                return $value ? date('n/j/y', strtotime($value)) : '--';

            case 'dateUpdated':
                return $value ? date('n/j/y', strtotime($value->format('Y-m-d H:i:s'))) : '--';

            case 'actionButton':

                if (($this->getTranslator()->service == 'export_import' && $this->status === 'published') || ($this->getTranslator()->service == 'acclaro' && $this->status !== 'new' && $this->status !== 'failed')) {
                    return '<form><div class="btn menubtn settings icon"></div><div class="menu"><ul><li><a class="" href="'.$this->getCpEditUrl().'"> Edit</a></li></ul><hr><ul><li><a class="link-disabled">Move to Trash</a></li></ul></div></form>';
                }

                if (!$this->trashed) {
                    return sprintf(
                        '<div class="btn menubtn settings icon"></div><div class="menu"><ul><li><a class="" href="'.$this->getCpEditUrl().'"> Edit</a></li></ul><hr><ul><li><a class="translations-delete-order error" data-hard-delete="0" data-order-id="%s">Move to Trash</a></li></ul></div>',$this->id
                    );
                } else {
                    return sprintf(
                        '<div class="btn menubtn settings icon"></div><div class="menu"><ul><li><a class="translations-restore-order" data-order-id="%s"> Restore </a></li></ul><hr><ul><li><a class="translations-delete-order error" data-hard-delete="1" data-order-id="%s">Delete Permanently</a></li></ul></div>',$this->id,$this->id
                    );
                }

            case 'ownerId':
                return $this->getOwner() ? $this->getOwner()->username : '';
           
            case 'translatorId':
                if (!$this->getTranslator()) {
                    return 'N/a';
                }
                return $this->getTranslator() ? ($this->getTranslator()->label ? $this->getTranslator()->label : $this->getTranslator()->service) : $this->getTranslator()->service;
        }

        return parent::getTableAttributeHtml($attribute);
    }

    protected static function defineTableAttributes(): array
    {
        $attributes = [
            'title' => ['label' => Translations::$plugin->translator->translate('app', 'Name')],
            'serviceOrderId' => ['label' => Translations::$plugin->translator->translate('app', 'ID')],
            'ownerId' => ['label' => Translations::$plugin->translator->translate('app', 'Owner')],
            'entriesCount' => ['label' => Translations::$plugin->translator->translate('app', 'Entries')],
            'wordCount' => ['label' => Translations::$plugin->translator->translate('app', 'Words')],
            'translatorId' => ['label' => Translations::$plugin->translator->translate('app', 'Translator')],
            'targetSites' => ['label' => Translations::$plugin->translator->translate('app', 'Sites')],
            'status' => ['label' => Translations::$plugin->translator->translate('app', 'Status')],
            'dateOrdered' => ['label' => Translations::$plugin->translator->translate('app', 'Created')],
            'dateUpdated' => ['label' => Translations::$plugin->translator->translate('app', 'Updated')],
            'actionButton' => ['label' => Translations::$plugin->translator->translate('app', 'Actions')]
        ];

        return $attributes;
    }

    public function criteriaAttributes()
    {
        return [
            'sourceSite'    => $this->string()->notNull()->defaultValue(''),
            'targetSites'   => $this->string()->notNull()->defaultValue(''),
            'status' => $this->enum('values', ['new','getting quote','needs approval','in preparation','in progress','complete','canceled','published'])->defaultValue('new'),
        ];
    }

    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [['translatorId', 'ownerId', 'sourceSite', 'targetSites', 'activityLog', 'entriesCount', 'wordCount', 'elementIds',],'required'];
        $rules[] = [['sourceSite'], SiteIdValidator::class];
        $rules[] = [['wordCount', 'entriesCount'], NumberValidator::class];
        $rules[] = ['status', 'default', 'value' => 'new'];
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

        $elements = array();
        
        foreach ($elementIds as $key => $elementId) {
            if (!array_key_exists($elementId, $this->_elements)) {
                $this->_elements[$elementId] = Craft::$app->elements->getElementById($elementId, null, $this->sourceSite);
            }

            if ($this->_elements[$elementId]) {
                $elements[] = $this->_elements[$elementId];
            }
        }

        return $elements;
    }

    public function getFiles()
    {
        if (is_null($this->_files)) {
            $this->_files = Translations::$plugin->fileRepository->getFilesByOrderId($this->id);
        }

        return $this->_files;
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

    public function getCpEditUrl()
    {
        return Translations::$plugin->urlHelper->cpUrl('translations/orders/detail/'.$this->id);
    }

    public function getStatusLabel()
    {
        switch ($this->status) {
            case 'new':
                return 'Pending submission';
            case 'getting quote':
            case 'needs approval':
            case 'in preparation':
            case 'in progress':
                return 'In progress';
            case 'in review':
                return 'In review';
            case 'complete':
                return 'Ready to apply';
            case 'canceled':
                return 'Cancelled';
            case 'published':
                return 'Applied';
            case 'failed':
                return 'Order failed';
        }
    }

    /**
     * Events
     */
    public function beforeSave(bool $isNew): bool
    {
        return true;
    }

    public function afterSave(bool $isNew)
    {
        if (!$isNew) {
            $record = OrderRecord::findOne($this->id);
            if (!$record) {
                throw new Exception('Invalid entry ID: ' . $this->id);
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
        $record->comments =  $this->comments;
        $record->activityLog =  $this->activityLog;
        $record->dateOrdered =  $this->dateOrdered;
        $record->serviceOrderId =  $this->serviceOrderId;
        $record->entriesCount =  $this->entriesCount;
        $record->wordCount =  $this->wordCount;
        $record->elementIds =  $this->elementIds;
        
        $record->save(false);
        
        parent::afterSave($isNew);
    }

    public function beforeDelete(): bool
    {
        return true;
    }

    public function afterDelete()
    {
    }

    public function performAction(ElementActionInterface $query)
    {
        $query->addSelect('translations_orders.*');

        $query->join('translations_orders translations_orders', 'translations_orders.id = elements.id');

        if ($this->status) {
            if (is_array($this->status)) {
                $query->andWhere(array('in', 'translations_orders.status', $this->status));
            } else if ($this->status !== '*') {
                $query->andWhere('translations_orders.status = :status', array(':status' => $this->status));
            }
        }

        if ($this->getAttribute('translatorId')) {
            $query->andWhere('translations_orders.translatorId = :translatorId', array(':translatorId' => $this->translatorId));
        }

        if ($this->getAttribute('sourceSite')) {
            $query->andWhere('translations_orders.sourceSite = :sourceSite', array(':sourceSite' => $this->sourceSite));
        }

        if ($this->getAttribute('targetSites')) {
            $query->andWhere('translations_orders.targetSites LIKE :targetSites', array(':targetSites' => '%"'.$this->targetSites.'"%'));
        }

        if ($this->getAttribute('startDate')) {
            $query->andWhere('translations_orders.dateOrdered >= :dateOrdered', array(':dateOrdered' => DateTime::createFromFormat('n/j/Y', $this->startDate)->format('Y-m-d H:i:s')));
        }

        if ($this->getAttribute('endDate')) {
            $query->andWhere('translations_orders.dateOrdered <= :dateOrdered', array(':dateOrdered' => DateTime::createFromFormat('n/j/Y', $this->endDate)->format('Y-m-d H:i:s')));
        }
    }
}
