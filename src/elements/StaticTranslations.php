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
use acclaro\translations\Translations;
use craft\elements\db\ElementQueryInterface;
use acclaro\translations\services\StaticTranslation;
use acclaro\translations\elements\db\StaticTranslationQuery;


/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class StaticTranslations extends Element
{

    const TRANSLATED = 'translated';
    const UNTRANSLATED = 'untranslated';

    public $original;
    public $translateId;
    public $translation;
    public $source;
    public $file;
    public $locale = 'en_us';
    public $field;
    public $translateStatus;

    /**
     * Properties
     */
    public static function displayName(): string
    {
        return Translations::$plugin->translator->translate('app', 'Static Translation');
    }

    public function __toString()
    {
        try{
            return $this->original;
        } catch (\Exception $e) {
            ErrorHandler::convertExceptionToError($e);
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
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @return string
     */
    public function getTranslateStatus()
    {
        if ($this->original != $this->translation) {
            return static::TRANSLATED;
        }

        return static::UNTRANSLATED;
    }

    /**
     * @param string|null $context
     * @return array
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [];
        $sources[] = ['heading' =>  Craft::t('app','Status')];

        $sources[] = [
            'status'   => 'orange',
            'key'      => 'status:' . self::UNTRANSLATED,
            'label'    =>  Craft::t('app','Untranslated'),
            'criteria' => [
                'source' => [
                    Craft::$app->path->getSiteTemplatesPath()
                ],
                'translateStatus' => self::UNTRANSLATED
            ],
        ];

        $sources[] = [
            'status'   => 'green',
            'key'      => 'status:' . self::TRANSLATED,
            'label'    => Craft::t('app', 'Translated'),
            'criteria' => [
                'source' => [
                    Craft::$app->path->getSiteTemplatesPath()
                ],
                'translateStatus' => self::TRANSLATED
            ],
        ];

        // Get all template files
        $templateSourceFiles = [];
        $options = [
            'recursive' => false,
            'only' => ['*.html','*.twig','*.js','*.json','*.atom','*.rss'],
            'except' => ['vendor/', 'node_modules/']
        ];
        $allFiles = FileHelper::findFiles(Craft::$app->path->getSiteTemplatesPath(), $options);

        foreach ($allFiles as $file) {
            $fileName = basename($file);
            $templateKey = str_replace('/', '*', $file);
            $templateSourceFiles['templatessources:'.$fileName] = [
                'label' => $fileName,
                'key' => 'templates:'.$templateKey,
                'criteria' => [
                    'source' => [
                        $file
                    ],
                ],
            ];
        }

        // Other Template folders & files
        $options = [
            'recursive' => false,
            'except' => ['vendor/', 'node_modules/']
        ];
        $allFiles = FileHelper::findDirectories(Craft::$app->path->getSiteTemplatesPath(), $options);
        foreach ($allFiles as $file) {
            $fileName = basename($file);
            $templateKey = str_replace('/', '*', $file);
            $templateSourceFiles['templatessources:'.$fileName] = [
                'label' => $fileName.'/',
                'key' => 'templates:'.$templateKey,
                'criteria' => [
                    'source' => [
                        $file
                    ],
                ],
            ];
        }

        $sources[] = ['heading' =>  Craft::t('app','Templates')];
        $sources[] = [
            'label'    => Craft::t('app', 'Templates'),
            'key'      => 'all-templates:',
            'criteria' => [
                'source' => [
                    Craft::$app->path->getSiteTemplatesPath()
                ]
            ],
            'nested' => $templateSourceFiles
        ];

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
            'status',
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

    public static function find(): ElementQueryInterface
    {
        return new StaticTranslationQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public static function indexHtml(ElementQueryInterface $elementQuery, array $disabledElementIds = null, array $viewState, string $sourceKey = null, string $context = null, bool $includeContainer, bool $showCheckboxes): string
    {

        // if not getting siteId then set primary site id
        if (empty($elementQuery->siteId)) {
            $primarySite = Craft::$app->getSites()->getPrimarySite();
            $elementQuery->siteId = $primarySite->id;
        }

        if ($elementQuery->translateStatus) {
            $elementQuery->status = $elementQuery->translateStatus;
        }

        $elements = Translations::$plugin->staticTranslationsRepository->get($elementQuery);

        $variables = [
            'viewMode' => $viewState['mode'],
            'context' => $context,
            'disabledElementIds' => $disabledElementIds,
            'attributes' => Craft::$app->getElementIndexes()->getTableAttributes(static::class, $sourceKey),
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

    public static function statuses(): array
    {
        return [
            self::TRANSLATED => ['label' => ucfirst(self::TRANSLATED), 'color' => 'green'],
            self::UNTRANSLATED => ['label' => ucfirst(self::UNTRANSLATED), 'color' => 'orange'],
        ];
    }
}
