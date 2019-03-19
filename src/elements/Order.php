<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\elements;


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
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\elements\Order;
use acclaro\translationsforcraft\records\OrderRecord;
use acclaro\translationsforcraft\TranslationsForCraft;
use acclaro\translationsforcraft\elements\db\OrderQuery;


/**
 * @author    Acclaro
 * @package   TranslationsForCraft
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
        return TranslationsForCraft::$plugin->translator->translate('app', 'Order');
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
    //         'new' => TranslationsForCraft::$plugin->translator->translate('app', 'Pending submission'),
    //         'in progress' => [
    //             'label' => TranslationsForCraft::$plugin->translator->translate('app', 'In progress'),
    //             'color' => 'orange'
    //         ],
    //         'complete' => [
    //             'label' => TranslationsForCraft::$plugin->translator->translate('app', 'Ready to publish'),
    //             'color' => 'blue'
    //         ],
    //         'canceled' => [
    //             'label' => TranslationsForCraft::$plugin->translator->translate('app', 'Canceled'),
    //             'color' => 'red'
    //         ],
    //         'published' => [
    //             'label' => TranslationsForCraft::$plugin->translator->translate('app', 'Complete'),
    //             'color' => 'green'
    //         ],
    //     ];
    // }

    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => 'all',
                'label' => TranslationsForCraft::$plugin->translator->translate('app', 'All Orders'),
                'criteria' => [],
                'defaultSort' => ['postDate', 'desc']
            ],
            [
                'key' => 'in-progress',
                'label' => TranslationsForCraft::$plugin->translator->translate('app', 'Orders in progress'),
                'criteria' => [
                    'status' => [
                        'new', 'in progress', 'in preparation', 'getting quote', 'needs approval', 'complete'
                    ]
                ],
                'defaultSort' => ['postDate', 'desc']
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
                        $languages .= ucfirst(TranslationsForCraft::$plugin->siteRepository->getSiteLanguageDisplayName($site)). '<span class="light"> ('.Craft::$app->getSites()->getSiteById($site)->language.')</span>';
                    } else {
                        $languages .= ucfirst(TranslationsForCraft::$plugin->siteRepository->getSiteLanguageDisplayName($site)). '<span class="light"> ('.Craft::$app->getSites()->getSiteById($site)->language.')</span>'. ', ';
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

                $translationService = TranslationsForCraft::$plugin->translationFactory->makeTranslationService($translator->service, json_decode($translator->settings, true));

                return sprintf('<a href="%s" target="_blank">#%s</a>', $translationService->getOrderUrl($this), $value);

            case 'status':
            switch ($this->statusLabel) {
                case 'Order failed':
                    return '<span class="status red"></span>'.TranslationsForCraft::$plugin->translator->translate('app', $this->statusLabel);
                case 'Pending submission':
                    return '<span class="status"></span>'.TranslationsForCraft::$plugin->translator->translate('app', $this->statusLabel);
                case 'In progress':
                    return '<span class="status orange"></span>'.TranslationsForCraft::$plugin->translator->translate('app', $this->statusLabel);
                case 'Ready to publish':
                    return '<span class="status blue"></span>'.TranslationsForCraft::$plugin->translator->translate('app', $this->statusLabel);
                case 'Canceled':
                    return '<span class="status red"></span>'.TranslationsForCraft::$plugin->translator->translate('app', $this->statusLabel);
                case 'Published':
                    return '<span class="status green"></span>'.TranslationsForCraft::$plugin->translator->translate('app', $this->statusLabel);
            }

            case 'requestedDueDate':
            case 'dateOrdered':
                return $value ? date('n/j/y', strtotime($value)) : '--';

            case 'actionButton':
            if ($this->status !== 'new' && $this->status !== 'failed') {
                    return '<form><div class="btn menubtn settings icon"></div><div class="menu"><ul><li><a class="" href="'.$this->getCpEditUrl().'"> Edit</a></li></ul><hr><ul><li><a class="link-disabled">Delete</a></li></ul></div></form>';
                }

                return sprintf(
                    '<div class="btn menubtn settings icon"></div><div class="menu"><ul><li><a class="" href="'.$this->getCpEditUrl().'"> Edit</a></li></ul><hr><ul><li><a class="translations-delete-order error" data-order-id="%s">Delete</a></li></ul></div>',$this->id
                );

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
            'title' => ['label' => TranslationsForCraft::$plugin->translator->translate('app', 'Name')],
            'serviceOrderId' => ['label' => TranslationsForCraft::$plugin->translator->translate('app', 'ID')],
            'ownerId' => ['label' => TranslationsForCraft::$plugin->translator->translate('app', 'Owner')],
            'entriesCount' => ['label' => TranslationsForCraft::$plugin->translator->translate('app', 'Entries')],
            'wordCount' => ['label' => TranslationsForCraft::$plugin->translator->translate('app', 'Words')],
            'translatorId' => ['label' => TranslationsForCraft::$plugin->translator->translate('app', 'Translator')],
            'targetSites' => ['label' => TranslationsForCraft::$plugin->translator->translate('app', 'Sites')],
            'status' => ['label' => TranslationsForCraft::$plugin->translator->translate('app', 'Status')],
            'dateOrdered' => ['label' => TranslationsForCraft::$plugin->translator->translate('app', 'Created')],
            'actionButton' => ['label' => TranslationsForCraft::$plugin->translator->translate('app', 'Actions')]
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
                $this->_elements[$elementId] = Craft::$app->elements->getElementById($elementId, null, $this->siteId);
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
            $this->_files = TranslationsForCraft::$plugin->fileRepository->getFilesByOrderId($this->id);
        }

        return $this->_files;
    }

    public function getTranslator()
    {
        $translator = $this->translatorId ? TranslationsForCraft::$plugin->translatorRepository->getTranslatorById($this->translatorId) : null;
        
        return $translator;
    }

    public function getOwner()
    {
        $owner = $this->ownerId ? TranslationsForCraft::$plugin->userRepository->getUserById($this->ownerId) : null;
        
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
        return TranslationsForCraft::$plugin->urlHelper->cpUrl('translations-for-craft/orders/detail/'.$this->id);
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
            case 'complete':
                return 'Ready to publish';
            case 'canceled':
                return 'Canceled';
            case 'published':
                return 'Published';
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
        $query->addSelect('translationsforcraft_orders.*');

        $query->join('translationsforcraft_orders translationsforcraft_orders', 'translationsforcraft_orders.id = elements.id');

        if ($this->status) {
            if (is_array($this->status)) {
                $query->andWhere(array('in', 'translationsforcraft_orders.status', $this->status));
            } else if ($this->status !== '*') {
                $query->andWhere('translationsforcraft_orders.status = :status', array(':status' => $this->status));
            }
        }

        if ($this->getAttribute('translatorId')) {
            $query->andWhere('translationsforcraft_orders.translatorId = :translatorId', array(':translatorId' => $this->translatorId));
        }

        if ($this->getAttribute('sourceSite')) {
            $query->andWhere('translationsforcraft_orders.sourceSite = :sourceSite', array(':sourceSite' => $this->sourceSite));
        }

        if ($this->getAttribute('targetSites')) {
            $query->andWhere('translationsforcraft_orders.targetSites LIKE :targetSites', array(':targetSites' => '%"'.$this->targetSites.'"%'));
        }

        if ($this->getAttribute('startDate')) {
            $query->andWhere('translationsforcraft_orders.dateOrdered >= :dateOrdered', array(':dateOrdered' => DateTime::createFromFormat('n/j/Y', $this->startDate)->format('Y-m-d H:i:s')));
        }

        if ($this->getAttribute('endDate')) {
            $query->andWhere('translationsforcraft_orders.dateOrdered <= :dateOrdered', array(':dateOrdered' => DateTime::createFromFormat('n/j/Y', $this->endDate)->format('Y-m-d H:i:s')));
        }
    }
}
