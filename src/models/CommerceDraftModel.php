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
use acclaro\translations\records\CommerceDraftRecord;
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
        $rules[] = [['name', 'productId', 'site', 'data', 'title', 'typeId'], 'required'];
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

    public static function populateModel($attributes)
    {
        if ($attributes instanceof CommerceDraftRecord) {
            $attributes = $attributes->getAttributes();
        }

        $productData = json_decode($attributes['data'], true);
        $fieldContent = isset($productData['fields']) ? $productData['fields'] : null;
        $attributes['id'] = $attributes['productId'];

        $attributes = array_diff_key($attributes, array_flip(array('data', 'fields', 'productId')));

        $attributes = array_merge($attributes, $productData);

        $draft = parent::setAttributes($attributes);

        if ($fieldContent) {
            $post = array();

            foreach ($fieldContent as $fieldId => $fieldValue) {
                $field = Craft::$app->fields->getFieldById($fieldId);

                if ($field) {
                    $post[$field->handle] = $fieldValue;
                }
            }

            $draft->setFieldValues($post);
        }

        return $draft;
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

        $path = sprintf('translations/products/%s/%s-%s', $product->type, $product->id, $product->slug);

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