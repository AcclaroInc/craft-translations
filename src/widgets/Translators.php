<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\widgets;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craft\base\Widget;
use acclaro\translationsforcraft\services\App;
use acclaro\translationsforcraft\records\WidgetRecord;
use acclaro\translationsforcraft\services\repository\TranslatorRepository;

/**
 * @author    Acclaro
 * @package   TranslationsForCraft
 * @since     1.0.2
 */
class Translators extends Widget
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Translator Services');
    }

    /**
     * @inheritdoc
     */
    public static function iconPath()
    {
        return Craft::getAlias('@acclaro/translationsforcraft/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
        
        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function minColspan()
    {
        return 1;
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml()
    {
        $view = Craft::$app->getView();
        
        $translators = $this->_getTranslators();
        
        return $view->renderTemplate('translations-for-craft/_components/widgets/Translators/body', ['translators' => $translators]);
    }

    public static function doesUserHaveWidget(string $type): bool
    {
        return WidgetRecord::find()
            ->where([
                'userId' => Craft::$app->getUser()->getIdentity()->id,
                'type' => $type,
            ])
            ->exists();
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return (static::allowMultipleInstances() || !static::doesUserHaveWidget(static::class));
    }

    /**
     * Returns translator data
     *
     * @return array
     */
    private function _getTranslators(): array
    {
        $repository = new TranslatorRepository();

        return $repository->getTranslators();
    }

    /**
     * Returns whether the widget can be selected more than once.
     *
     * @return bool Whether the widget can be selected more than once
     */
    protected static function allowMultipleInstances(): bool
    {
        return false;
    }
}