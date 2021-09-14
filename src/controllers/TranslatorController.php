<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\controllers;

use Craft;
use Exception;
use craft\web\Controller;
use yii\web\HttpException;
use acclaro\translations\Translations;
use acclaro\translations\services\AcclaroService;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class TranslatorController extends Controller
{
    /**
     * @var int
     */
    protected $pluginVersion;

    const DEFAULT_TRANSLATOR = 'export_import';

    // Public Methods
    // =========================================================================
    
    public function __construct(
        $id,
        $module = null
    ) {
        parent::__construct($id, $module);

        $this->pluginVersion = Craft::$app->getPlugins()->getPlugin('translations')->getVersion();
    }

    /**
     * @return mixed
     */
    public function actionDelete()
    {
        $this->requireLogin();
        $this->requirePostRequest();

        if (!Translations::$plugin->userRepository->userHasAccess('translations:translator:delete')) {
            return;
        }

        $translatorIds = Craft::$app->getRequest()->getBodyParam('translatorIds');
        $translatorIds = explode(",", $translatorIds);

        foreach($translatorIds as $translatorId) {
            $translator = Translations::$plugin->translatorRepository->getTranslatorById($translatorId);

            if (!$translator) {
                Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Invalid translator.'));
                return $this->asJson(["success" => false]);
            }

            // check if translator has any pending orders
            $pendingOrders = Translations::$plugin->orderRepository->getInProgressOrdersByTranslatorId($translatorId);
    
            $pendingOrdersCount = count($pendingOrders);
    
            if ($pendingOrdersCount > 0) {
                Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'The translator cannot be deleted as orders have been created already.'));
    
                return $this->asJson(["success" => false]);
            }
    
            Translations::$plugin->translatorRepository->deleteTranslator($translator);
        }

        Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Translators deleted.'));

        return $this->asJson([
            "success" => true
        ]);
    }

    /**
     * @return mixed
     */
    public function actionSave(array $variables = array())
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $variables['selectedSubnavItem'] = 'translators';
        $variables['pluginVersion'] = $this->pluginVersion;
        $variables['translatorId'] = $translatorId = Craft::$app->getRequest()->getBodyParam('id');
        $variables['translationServices'] = Translations::$plugin->translatorRepository->getTranslationServices();

        $isContinue = Craft::$app->getRequest()->getBodyParam('flow') === "continue";

        if ($translatorId) {

            if (!Translations::$plugin->userRepository->userHasAccess('translations:translator:edit')) {
                Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'User is not permitted for this operation.'));
                return;
            }

            $translator = Translations::$plugin->translatorRepository->getTranslatorById($translatorId);

            if (!$translator) {
                // throw new HttpException(400, 'Invalid Translator');
                Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Invalid Translator'));
                return;
            }
        } else {
            if (!Translations::$plugin->userRepository->userHasAccess('translations:translator:create')) {
                Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'User is not permitted to create new translator.'));
                return;
            }
            $translator = Translations::$plugin->translatorRepository->makeNewTranslator();
        }

        $service = Craft::$app->getRequest()->getBodyParam('service');
        $allSettings = Craft::$app->getRequest()->getBodyParam('settings');
        $settings = $allSettings[$service] ?? array();

        $translator->label = Craft::$app->getRequest()->getBodyParam('label');
        $translator->service = $service;
        $translator->settings = json_encode($settings);
        $translator->status = Craft::$app->getRequest()->getBodyParam('status');

        //Make Export/Import Translator automatically active
        if ($translator->service !== self::DEFAULT_TRANSLATOR)
        {
            $auth = (new AcclaroService())->authenticateService($service, $settings);
            if (! $auth) {
                $translator->status = "";
                $variables['translator'] = $translator;
                Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'Api token could not be authenticated.'));
                return $this->renderTemplate('translations/translators/_detail', $variables);
            }
        }

        $translator->status = 'active';
        Translations::$plugin->translatorRepository->saveTranslator($translator);

        Craft::$app->getSession()->setNotice(Translations::$plugin->translator->translate('app', 'Translator saved.'));

        if ($isContinue) {
            $this->redirect('translations/translators/new');
        } else {
            $this->redirect('translations/translators');
        }
    }

    /**
     * @return mixed
     */
    public function actionDetail(array $variables = array())
    {
        $variables = Craft::$app->getRequest()->resolve()[1];
        
        $variables['pluginVersion'] = $this->pluginVersion;
        $variables['selectedSubnavItem'] = 'translators';

        $variables['translatorId'] = isset($variables['translatorId']) ? $variables['translatorId'] : null;

        if ($variables['translatorId']) {
            $variables['translator'] = Translations::$plugin->translatorRepository->getTranslatorById($variables['translatorId']);

            if (!$variables['translator']) {
                throw new HttpException(404);
            }
        } else {
            $variables['translator'] = Translations::$plugin->translatorRepository->makeNewTranslator();
        }

        $variables['translationServices'] = Translations::$plugin->translatorRepository->getTranslationServices();

        $this->renderTemplate('translations/translators/_detail', $variables);
    }

    /**
     * @return mixed
     */
    public function actionIndex()
    {
        $variables = array();

        $variables['pluginVersion'] = $this->pluginVersion;

        $variables['translators'] = Translations::$plugin->translatorRepository->getTranslators();

        $variables['translatorTargetSites'] = array();

        $variables['selectedSubnavItem'] = 'translators';
        
        $this->renderTemplate('translations/translators/_index', $variables);
    }

    /**
     * @return mixed
     */
    public function actionGetTranslators()
    {
        $this->requireLogin();
        $this->requirePostRequest();
        $translators = [];

        $service = Craft::$app->getRequest()->getBodyParam('service') ?? null;

        if ($service && $service !== "all") {
            $translators = Translations::$plugin->translatorRepository->getTranslatorByService($service);
        } else {
            $translators = Translations::$plugin->translatorRepository->getTranslators();
        }

        return $this->asJson([
            "success" => true,
            "data"    => json_decode(json_encode($translators), true)
        ]);
    }
}
