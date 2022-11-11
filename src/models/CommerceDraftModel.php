<?php
/**
 * Translations for Craft plugin for Craft CMS 4.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\models;

use Craft;
use craft\commerce\elements\Product;
use acclaro\translations\Translations;
use craft\behaviors\CustomFieldBehavior;
use craft\behaviors\DraftBehavior;
use craft\behaviors\FieldLayoutBehavior;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     3.1.0
 */
class CommerceDraftModel extends Product
{
    protected $_product = null;

    public $productId;

    public $site;

    public $data;

    public $variants;

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
        $rules[] = [['name', 'productId', 'site', 'data', 'variants', 'title', 'typeId'], 'required'];
        $rules[] = ['site', SiteIdValidator::class];
        $rules[] = [['dateCreated', 'dateUpdated'], DateTimeValidator::class];
        $rules[] = ['enabled', 'default', 'value' => true];
        $rules[] = ['archived', 'default', 'value' => false];
        $rules[] = ['enabledForSite', 'default', 'value' => true];

        return $rules;
    }

    public function getFieldLayout(): ?\craft\models\FieldLayout
    {
        $product = $this->getProduct();

        return $product->getFieldLayout();
    }

    public function getHandle()
    {
        return $this->getProduct()->handle;
    }

    public function getProduct()
    {
        if (is_null($this->productId)) {
            $this->_product = Translations::$plugin->commerceRepository->getProductById($this->id); // this works for creating orders
        } else {
            $this->_product = Translations::$plugin->commerceRepository->getProductById($this->productId); // this works for edit draft
        }

        return $this->_product;
    }

    public function getUrl(): ?string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getCpEditUrl(): ?string
    {
        $product = $this->getProduct();
        $data = [
            'draftId' => $this->draftId,
            'site' => Craft::$app->getSites()->getSiteById($this->site)->handle
        ];

        $path = sprintf('commerce/product/%s/%s-%s', $product->type, $product->id, $product->slug);

        return Translations::$plugin->urlHelper->cpUrl($path, $data);
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
            'elementType' => Product::class,
        ];
        $behaviors['draft'] = [
            'class' => DraftBehavior::class,
            'creatorId' => 1,
            'draftName' => 'Commerce Product Draft',
            'draftNotes' => '',
            'trackChanges' => true,
        ];
        return $behaviors;
    }
}