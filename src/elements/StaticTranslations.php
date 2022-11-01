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
use yii\helpers\FileHelper;
use craft\elements\db\ElementQueryInterface;

use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\elements\db\StaticTranslationQuery;


/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class StaticTranslations extends Element
{
    public $original;
    public $translateId;
    public $translation;
    public $source;
    public $file;
    public $field;
    public $translateStatus;

    /**
     * Properties
     */
    public static function displayName(): string
    {
        return Translations::$plugin->translator->translate('app', 'Static Translation');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('app', 'Static Translations');
    }

    public function __toString(): string
    {
        try{
            return $this->original;
        } catch (\Exception $e) {
            \yii\base\ErrorHandler::convertExceptionToError($e);
        }
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @return string
     */
    public function getTranslateStatus()
    {
        if ($this->original != $this->translation) {
            return Constants::STATUS_STATIC_TRANSLATION_TRANSLATED;
        }

        return Constants::STATUS_STATIC_TRANSLATION_UNTRANSLATED;
    }

    /**
     * @inheritdoc
     */
    public static function sources(string $context): array
    {
        return static::defineSources($context);;
    }

    /**
     * @param string|null $context
     * @return array
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [];

        $options = [
            'recursive' => false,
            'only' => ['*.html','*.twig','*.js','*.json','*.atom','*.rss'],
            'except' => ['vendor/', 'node_modules/']
        ];
        $allFiles = FileHelper::findFiles(Craft::$app->path->getSiteTemplatesPath(), $options);

        $sources[] = [
            'key'      => str_replace('/', '*', Craft::$app->path->getSiteTemplatesPath()),
            'label'    =>  Craft::t('app','All Translations'),
            'criteria' => [
                'source' => [
                    Craft::$app->path->getSiteTemplatesPath()
                ]
            ],
        ];

        $sources[] = ['heading' =>  Craft::t('app','Templates')];

        foreach ($allFiles as $file) {
            if (self::countTranslation($file)) {
                $sources[] = [
                    'label' => basename($file),
                    'key' => str_replace('/', '*', $file),
                    'criteria' => [
                        'source' => [ $file ],
                    ],
                ];
            }
        }

        // Other Template folders & files
        $options = [
            'recursive' => false,
            'except' => ['vendor/', 'node_modules/']
        ];

        $allFiles = FileHelper::findDirectories(Craft::$app->path->getSiteTemplatesPath(), $options);
        foreach ($allFiles as $file) {
            if (self::countTranslation($file)) {
                $sources[] = [
                    'label' => basename($file).'/',
                    'key' => str_replace('/', '*', $file),
                    'criteria' => [
                        'source' => [ $file ],
                    ],
                ];
            }
        }

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return [
            'original',
            'translation',
            'source',
            'file',
            'locale'
        ];
    }

    /**
     * @inheritdoc
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        return $this->$attribute;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        $primary = Craft::$app->getSites()->getPrimarySite();
        $lang = Craft::$app->getI18n()->getLocaleById($primary->language);
        $attributes['original'] = ['label' => Translations::$plugin->translator->translate('app', "Source: $lang->displayName ($primary->language)")];
        $attributes['field']     = ['label' => Craft::t('app','Target: Translation')];

        return $attributes;
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'original',
            'field'
        ];
    }

    public static function find(): ElementQueryInterface
    {
        return new StaticTranslationQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public static function indexHtml(ElementQueryInterface $elementQuery, ?array $disabledElementIds, array $viewState, ?string $sourceKey, ?string $context, bool $includeContainer, bool $showCheckboxes): string
    {
        // if not getting siteId then set primary site id
        if (empty($elementQuery->siteId)) {
            $primarySite = Craft::$app->getSites()->getPrimarySite();
            $elementQuery->siteId = $primarySite->id;
        }

        $attributes = self::removeDisplayName(Craft::$app->getElementSources()->getTableAttributes(static::class, $sourceKey));

        if (!empty($elementQuery->siteId)) {
            $currentSite = Craft::$app->getSites()->getSiteById($elementQuery->siteId);
            $lang = Craft::$app->getI18n()->getLocaleById($currentSite->language);
            $trans = 'Target: '.ucfirst($lang->displayName).' ('.$currentSite->language.')';
            array_walk_recursive($attributes, function(&$attributes) use($trans) {
                if($attributes == 'Target: Translation') {
                    $attributes= $trans;
                }
            });
        }

        $elements = Translations::$plugin->staticTranslationsRepository->get($elementQuery);

        $variables = [
            'viewMode' => $viewState['mode'],
            'context' => $context,
            'disabledElementIds' => $disabledElementIds,
            'collapsedElementIds' => Craft::$app->getRequest()->getParam('collapsedElementIds'),
            'attributes' => $attributes,
            'elements' => $elements,
            'showCheckboxes' => $showCheckboxes
        ];

        Craft::$app->view->registerJs("$('table.fullwidth thead th').css('width', '50%');");
        Craft::$app->view->registerJs("$('.buttons.hidden').removeClass('hidden');");

        $template = '_elements/'.$viewState['mode'].'view/'.($includeContainer ? 'container' : 'elements');

        return Craft::$app->view->renderTemplate($template, $variables);
    }

    /**
     * @return null|string
     */
    public function getLocale()
    {
        $site = Craft::$app->getSites()->getSiteById($this->siteId);

        return $site->language;
    }

    /**
     * @param $source
     * @return int
     * @throws \craft\errors\SiteNotFoundException
     */
    public static function countTranslation($source) {
        $elementQuery = StaticTranslations::find();
        $elementQuery->status = null;
        $elementQuery->source = [$source];
        $elementQuery->search = null;
        $elementQuery->siteId = Craft::$app->getSites()->getPrimarySite()->id;

        $translations = Translations::$plugin->staticTranslationsRepository->get($elementQuery);

        return count($translations);
    }

    /**
     * Used to remove default first column in attributes
     */
    private static function removeDisplayName($attributes) {
        unset($attributes[0]);
        return $attributes;
    }
}
