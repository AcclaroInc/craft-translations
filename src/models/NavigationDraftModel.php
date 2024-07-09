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
use craft\behaviors\DraftBehavior;
use craft\validators\SiteIdValidator;
use acclaro\translations\Translations;
use craft\behaviors\FieldLayoutBehavior;
use craft\behaviors\CustomFieldBehavior;
use verbb\navigation\elements\Node;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class NavigationDraftModel extends Node
{
    /**
     * @var \acclaro\translations\services\repository\NavigationDraftRepository $_nodes
     */
    protected $_nodes = null;

    public $name;

    public ?int $navId = null;

    public $site;

    public $titles;

    public array $data = [];

    public $sourceSite;

    /**
     * @param array $attributes
     */
    public function __construct(
        $attributes = []
    ) {
        parent::__construct($attributes);
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['name', 'navId', 'data' , 'site'], 'required'];
        $rules[] = ['site', SiteIdValidator::class];

        return $rules;
    }

    public function getHandle()
    {
        return $this->getNavigationNav();
    }


    public function getNavigationNav()
    {
        if (is_null($this->navId)) {
            $this->_nodes = Translations::$plugin->navigationDraftRepository->getNavById($this->id); // this works for creating orders
        } else {
            $this->_nodes = Translations::$plugin->navigationDraftRepository->getNavById($this->navId); // this works for edit draft
        }

        return $this->_nodes;
    }

    public function getUrl($transform = null, ?bool $generateNow = null): ?string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getCpEditUrl(): ?string
    {
        $path = 'translations/edit/'.$this->navId. '/'.$this->draftId;

        return Translations::$plugin->urlHelper->cpUrl($path);
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['customFields'] = [
            'class' => CustomFieldBehavior::class,
            'hasMethods' => false,
        ];
        $behaviors['fieldLayout'] = [
            'class' => FieldLayoutBehavior::class,
            'elementType' => Node::class,
        ];
        $behaviors['draft'] = [
            'class' => DraftBehavior::class,
            'creatorId' => 1,
            'draftName' => 'Navigation Draft',
            'draftNotes' => '',
            'trackChanges' => true,
        ];
        return $behaviors;
    }
}
