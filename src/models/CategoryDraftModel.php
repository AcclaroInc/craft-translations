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

use craft\elements\Category;
use craft\behaviors\DraftBehavior;
use craft\validators\NumberValidator;
use craft\validators\SiteIdValidator;
use craft\validators\StringValidator;
use craft\validators\DateTimeValidator;
use acclaro\translations\services\App;
use acclaro\translations\Translations;
use craft\behaviors\FieldLayoutBehavior;
use craft\behaviors\CustomFieldBehavior;
use acclaro\translations\records\CategoryDraftRecord;

use Craft;
use craft\base\Model;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class CategoryDraftModel extends Category
{
    protected $_category = null;

    public $id;

    public $draftId;

    public $name;

    public $categoryId;

    public $site;

    public $data;
    
    public $sourceSite;

    /**
     * @param array|null    $attributes
     */
    public function __construct(
        $attributes = null
    ) {
        parent::__construct($attributes);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [['name', 'categoryId', 'site', 'data'], 'required'];
        $rules[] = ['site', SiteIdValidator::class];

        return $rules;
    }

    public function getFieldLayout()
    {
        
        return parent::getFieldLayout();
    }

    public function getHandle()
    {
        return $this->getCategory()->handle;
    }

    public static function populateModel($attributes)
    {
        if ($attributes instanceof CategoryDraftRecord) {
            $attributes = $attributes->getAttributes();
        }

        $categoryData = json_decode($attributes['data'], true);
        $fieldContent = isset($categoryData['fields']) ? $categoryData['fields'] : null;
        $attributes['id'] = $attributes['categoryId'];
        
        $attributes = array_diff_key($attributes, array_flip(array('data', 'fields', 'categoryId')));
        
        $attributes = array_merge($attributes, $categoryData);
        
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

    public function getCategory()
    {
        if (is_null($this->categoryId)) {
            $this->_category = Translations::$plugin->categoryRepository->getCategoryById($this->id); // this works for creating orders
        } else {
            $this->_category = Translations::$plugin->categoryRepository->getCategoryById($this->categoryId); // this works for edit draft
        }
        
        // echo '<pre>';
        // echo "//======================================================================<br>// return getCategory()<br>//======================================================================<br>";
        // var_dump($this->_category);
        // echo '</pre>';

        return $this->_category;
    }

    public function getUrl()
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getCpEditUrl()
    {
        $category = $this->getCategory();

        $catUrl = $category->id . ($category->slug ? '-' . $category->slug : '');
        $path = 'translations/categories/'.$category->getGroup()->handle.'/'.$catUrl.'/drafts/'.$this->draftId;
        
        return Translations::$plugin->urlHelper->cpUrl($path);
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['customFields'] = [
            'class' => CustomFieldBehavior::class,
            'hasMethods' => false,
        ];
        $behaviors['fieldLayout'] = [
            'class' => FieldLayoutBehavior::class,
            'elementType' => Category::class,
        ];
        $behaviors['draft'] = [
            'class' => DraftBehavior::class,
            'creatorId' => 1,
            'draftName' => 'Category Draft',
            'draftNotes' => '',
            'trackChanges' => true,
        ];
        return $behaviors;
    }
}
