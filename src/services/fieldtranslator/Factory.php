<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\services\fieldtranslator;

use Craft;
use craft\base\Field;
use craft\fields\Tags;
use craft\fields\Table;
use craft\fields\Assets;
use craft\fields\Matrix;
use craft\fields\Number;
use craft\fields\Entries;
use craft\fields\Dropdown;
use craft\fields\PlainText;
use craft\fields\Categories;
use craft\fields\Checkboxes;
use craft\fields\MultiSelect;
use craft\fields\RadioButtons;

use benf\neo\Field as NeoField;
use typedlinkfield\fields\LinkField;
use craft\redactor\Field as RedactorField;
use fruitstudios\linkit\fields\LinkitField;
use verbb\supertable\fields\SuperTableField;
use nystudio107\seomatic\fields\SeoSettings;

use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\TranslationsForCraft;

class Factory
{
    private $nativeFieldTypes = array(
        // Assets::class           => AssetsFieldTranslator::class,
        Categories::class       => CategoryFieldTranslator::class,
        Checkboxes::class       => MultiOptionsFieldTranslator::class,
        Dropdown::class         => SingleOptionFieldTranslator::class,
        Entries::class          => EntriesFieldTranslator::class,
        Matrix::class           => MatrixFieldTranslator::class,
        MultiSelect::class      => MultiOptionsFieldTranslator::class,
        LinkField::class        => LinkFieldTranslator::class,
        LinkitField::class      => LinkitFieldTranslator::class,
        NeoField::class         => NeoFieldTranslator::class,
        Number::class           => GenericFieldTranslator::class,
        PlainText::class        => GenericFieldTranslator::class,
        RadioButtons::class     => SingleOptionFieldTranslator::class,
        RedactorField::class    => GenericFieldTranslator::class,
        SeoSettings::class      => SeomaticMetaFieldTranslator::class,
        SuperTableField::class  => SuperTableFieldTranslator::class,
        Table::class            => TableFieldTranslator::class,
        Tags::class             => TagFieldTranslator::class,
    );

    public function makeTranslator(Field $field)
    {
        if ($field instanceof TranslatableFieldInterface) {
            return $field;
        }
        
        $class = get_class($field);
        
        if (array_key_exists($class, $this->nativeFieldTypes)) {
            $translatorClass = $this->nativeFieldTypes[$class];

            switch ($translatorClass) {
                case MultiOptionsFieldTranslator::class:
                    return new MultiOptionsFieldTranslator(Craft::$app, TranslationsForCraft::$plugin->wordCounter, TranslationsForCraft::$plugin->translationRepository);
                case SingleOptionFieldTranslator::class:
                    return new SingleOptionFieldTranslator(Craft::$app, TranslationsForCraft::$plugin->wordCounter, TranslationsForCraft::$plugin->translationRepository);
                case TagFieldTranslator::class:
                    return new TagFieldTranslator(Craft::$app, TranslationsForCraft::$plugin->wordCounter, TranslationsForCraft::$plugin->tagRepository, TranslationsForCraft::$plugin->elementCloner);
                case CategoryFieldTranslator::class:
                    return new CategoryFieldTranslator(Craft::$app, TranslationsForCraft::$plugin->wordCounter, TranslationsForCraft::$plugin->categoryRepository);
            }
            return new $translatorClass(Craft::$app, TranslationsForCraft::$plugin->wordCounter);
        }

        return null;
    }
}