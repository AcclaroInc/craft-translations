<?php

namespace acclaro\translations\services;

use craft\base\Component;
use yii\base\Application as Application;

/**
 * App Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Acclaro
 * @package   Translations
 */
class App extends Component
{

    /**
     * @var UrlHelper
     */
    public $urlHelper;
    
    /**
     * @var UrlGenerator
     */
    public $urlGenerator;
    
    /**
     * @var Translator
     */
    public $translator;

    /**
     * @var ElementCloner
     */
    public $elementCloner;
    
    /**
     * @var repository\TranslationRepository
     */
    public $translationRepository;
    
    /**
     * @var repository\CategoryRepository
     */
    public $categoryRepository;

    /**
     * @var repository\CategoryDraftRepository
     */
    public $categoryDraftRepository;

    /**
     * @var repository\TagRepository
     */
    public $tagRepository;
    
    /**
     * @var repository\DraftRepository
     */
    public $draftRepository;
    
    /**
     * @var repository\EntryRepository
     */
    public $entryRepository;
    
    /**
     * @var repository\FileRepository
     */
    public $fileRepository;
    
    /**
     * @var repository\GlobalSetRepository
     */
    public $globalSetRepository;
    
    /**
     * @var repository\GlobalSetDraftRepository
     */
    public $globalSetDraftRepository;
    
    /**
     * @var repository\SiteRepository
     */
    public $siteRepository;
    
    /**
     * @var repository\OrderRepository
     */
    public $orderRepository;
    
    /**
     * @var repository\TranslatorRepository
     */
    public $translatorRepository;
    
    /**
     * @var repository\UserRepository
     */
    public $userRepository;

    /**
     * @var repository\StaticTranslationsRepository
     */
    public $staticTranslationsRepository;

    /**
     * @var WordCounter
     */
    public $wordCounter;
    
    /**
     * @var fieldtranslator\Factory
     */
    public $fieldTranslatorFactory;
    
    /**
     * @var translator\Factory
     */
    public $translatorFactory;
    
    /**
     * @var ElementTranslator
     */
    public $elementTranslator;
    
    /**
     * @var ElementToXmlConverter
     */
    public $elementToXmlConverter;
    
    /**
     * @var ElementToFileConverter
     */
    public $elementToFileConverter;
    
    /**
     * @var OrderSearchParams
     */
    public $orderSearchParams;
    
    public function init()
    {
        $this->urlHelper = new UrlHelper();
        $this->urlGenerator = new UrlGenerator();
        $this->translator = new Translator();
        $this->elementCloner = new ElementCloner();
        $this->translationRepository = new repository\TranslationRepository();
        $this->categoryRepository = new repository\CategoryRepository();
        $this->categoryDraftRepository = new repository\CategoryDraftRepository();
        $this->tagRepository = new repository\TagRepository();
        $this->draftRepository = new repository\DraftRepository();
        $this->entryRepository = new repository\EntryRepository();
        $this->fileRepository = new repository\FileRepository();
        $this->globalSetRepository = new repository\GlobalSetRepository();
        $this->globalSetDraftRepository = new repository\GlobalSetDraftRepository();
        $this->siteRepository = new repository\SiteRepository();
        $this->orderRepository = new repository\OrderRepository();
        $this->translatorRepository = new repository\TranslatorRepository();
        $this->userRepository = new repository\UserRepository();
        $this->staticTranslationsRepository = new repository\StaticTranslationsRepository();
        $this->wordCounter = new WordCounter();
        $this->fieldTranslatorFactory = new fieldtranslator\Factory();
        $this->translatorFactory = new translator\Factory();
        $this->elementTranslator = new ElementTranslator();
        $this->elementToXmlConverter = new ElementToXmlConverter();
        $this->elementToFileConverter = new ElementToFileConverter();
        $this->orderSearchParams = new OrderSearchParams();
    }
}
