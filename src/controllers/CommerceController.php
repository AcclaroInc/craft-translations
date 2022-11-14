<?php

/**
 * Translations for Craft plugin for Craft CMS 4.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\controllers;

use Craft;
use craft\commerce\Plugin;
use craft\commerce\elements\Product;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\web\assets\editproduct\EditProductAsset;

use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

use acclaro\translations\Constants;
use acclaro\translations\Translations;
use craft\helpers\Json;
use craft\helpers\UrlHelper;

/**
 * Class CommerceController will be used to process Craft Commerce Products/Variants
 * 
 * @author    Acclaro
 * @package   Translations
 * @since     3.1.0
 */
class CommerceController extends BaseController
{
    /**
     * Edit Craft Commerce Products Draft
     *
     * @param array $variables
     * @return void
     */
    public function actionEditDraft(array $variables = array()): Response
    {
        $variables = $this->request->resolve()[1];
        $variables['selectedSubnavItem'] = 'products';

        if (empty($variables['productTypeHandle'])) {
            throw new NotFoundHttpException(Translations::$plugin->translator->translate('app', 'Invalid: “{name}”', array('name' => 'productTypeHandle')));
        }

        if ($site = $variables['site'] ?? null
        ) {
            $variables['site'] = Craft::$app->getSites()->getSiteByHandle($site);

            if (!$variables['site']) {
                throw new NotFoundHttpException('Invalid site handle: ' . $site);
            }
        }

        $this->_prepEditProductVariables($variables);

        /** @var Product $product */
        $product = $variables['product'];

        /** @var craft\elements\User $user */
        $user = Craft::$app->getUser()->getIdentity();

        if (!$user->can('translations:orders:create')) {
            throw new ForbiddenHttpException(
                Translations::$plugin->translator
                ->translate('app', 'User does not have permission to perform this action.')
            );
        }
        $variables['canCreateProduct'] = true;
        $variables['title'] = $variables['draft']->title ?? $product->title;

        // Can't just use the entry's getCpEditUrl() because that might include the site handle when we don't want it
        $variables['baseCpEditUrl'] = 'commerce/product/' . $variables['productTypeHandle'] . '/{id}-{slug}';

        // Set the "Continue Editing" URL
        $variables['continueEditingUrl'] = sprintf("%s?draftId=%s&site=%s", $variables['baseCpEditUrl'], $variables['draftId'], $site);

        $this->_prepVariables($variables);

        if (!$product->getType()->hasVariants) {
            $this->getView()->registerJs('Craft.Commerce.initUnlimitedStockCheckbox($("#details"));');
        }

        // Enable Live Preview?
        if (!$this->request->isMobileBrowser(true) && Plugin::getInstance()->getProductTypes()->isProductTypeTemplateValid($variables['productType'], $variables['site']->id)) {
            $this->getView()->registerJs('Craft.LivePreview.init(' . Json::encode([
                'fields' => '#fields > .flex-fields > .field',
                'extraFields' => '#details',
                'previewUrl' => $product->getUrl(),
                'previewAction' => Craft::$app->getSecurity()->hashData('commerce/products-preview/preview-product'),
                'previewParams' => [
                    'typeId' => $variables['productType']->id,
                    'productId' => $product->id,
                    'siteId' => $product->siteId,
                ],
            ]) . ');');

            $variables['showPreviewBtn'] = true;
        } else {
            $variables['showPreviewBtn'] = false;
        }

        $this->getView()->registerAssetBundle(EditProductAsset::class);
        return $this->renderTemplate('translations/products/_editDraft', $variables);
    }

    /**
     * Save Craft Commerce Products Draft
     *
     * @param array $variables
     * @return void
     */
    public function actionSaveDraft()
    {
        $request = $this->request;
        $variables = $request->resolve()[1];
        $variants = $request->getBodyParam('variants') ?: [];
        $fields = $request->getBodyParam('fields') ?: [];

        $draft = Translations::$plugin->commerceRepository->getDraftById($variables['draftId']);
        $draft->slug = $request->getBodyParam('slug');
        $draft->title = $request->getBodyParam('title', $draft->title);

        $draft->setFieldValuesFromRequest('fields');
        $draft->updateTitle();

        $draft->setVariants($variants);
        
        foreach ($draft->getVariants(true) as $variant) {
            $fields['variant'][$variant->id] = $variant->getSerializedFieldValues();
            $fields['variant'][$variant->id]['title'] = $variant->title;
        }

        if (Translations::$plugin->commerceRepository->saveDraft($draft, $fields)) {
            $this->setSuccess('Draft saved.');

            $this->redirect($draft->getCpEditUrl(), 302, true);
        } else {
            $this->setError('Couldn’t save draft.');
        }
    }

    /**
     * Publish Craft Commerce Products Draft
     *
     * @param array $variables
     * @return void
     */
    public function actionPublishDraft()
    {
        $variables = $this->request->resolve()[1];
        $draftId = $variables['draftId'];
        $productId = Craft::$app->getRequest()->getParam('productId');
        $draft = Translations::$plugin->commerceRepository->getDraftById($draftId);

        if (!$draft) {
            $this->setError("No draft exists with the ID '{$draftId}'.");
            return;
        }

        $product = Translations::$plugin->commerceRepository->getProductById($productId, $draft->site);

        if (!$product) {
            $this->setError("No product exists with the ID '{$draft->productId}'.");
            return;
        }

        $draft->title = $this->request->getParam('title') ?? $product->title;
        $draft->slug = $this->request->getParam('slug') ?? $product->slug;

        $fields = $this->request->getParam('fields') ?? [];

        if ($fields) {
            $draft->setFieldValues($fields);
        }

        $file = Translations::$plugin->fileRepository->getFileByDraftId($draftId, $product->id);

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            if ($file) {
                $order = Translations::$plugin->orderRepository->getOrderById($file->orderId);

                $file->status = Constants::ORDER_STATUS_PUBLISHED;
                $file->draftId = 0;

                Translations::$plugin->fileRepository->saveFile($file);

                $order->status = Translations::$plugin->orderRepository->getNewStatus($order);
                Translations::$plugin->orderRepository->saveOrder($order);
            }

            if (Translations::$plugin->commerceRepository->publishDraft($draft)) {
                $this->redirect($product->getCpEditUrl(), 302, true);

                $this->setSuccess('Draft published.');
                $transaction->commit();

                return Translations::$plugin->commerceRepository->deleteDraft($draft);
            } else {
                $this->setError('Couldn’t publish draft.');
                $transaction->rollBack();
            }
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            $transaction->rollBack();
        }
    }

    /**
     * Delete Craft Commerce Products Draft
     *
     * @param array $variables
     * @return void
     */
    public function actionDeleteDraft()
    {
        $variables = $this->request->resolve()[1];
        $draftId = $variables['draftId'];
        $draft = Translations::$plugin->commerceRepository->getDraftById($draftId);

        if (!$draft) {
            $this->setError("No draft exists with the ID '{$draftId}'.");
            return;
        }

        $product = Translations::$plugin->commerceRepository->getProductById($draft->productId);
        $url = $product->getCpEditUrl();

        Translations::$plugin->commerceRepository->deleteDraft($draft);

        $file = Translations::$plugin->fileRepository->getFileByDraftId($draftId, $product->id);

        if ($file) {
            $order = Translations::$plugin->orderRepository->getOrderById($file->orderId);

            $file->status = Constants::FILE_STATUS_CANCELED;
            $file->draftId = null;
            $file->dateDelivered = null;

            Translations::$plugin->fileRepository->saveFile($file);

            $order->status = Translations::$plugin->orderRepository->getNewStatus($order);
            Translations::$plugin->orderRepository->saveOrder($order);
        }

        $this->setSuccess('Draft deleted.');

        return $this->redirect($url, 302, true);
    }

    // Private methods

    /**
     * @param array $variables
     * @throws ForbiddenHttpException
     * @throws HttpException
     * @throws NotFoundHttpException
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     */
    private function _prepEditProductVariables(array &$variables): void
    {
        if (!empty($variables['productTypeHandle'])) {
            $variables['productType'] = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle($variables['productTypeHandle']);
        } elseif (!empty($variables['productTypeId'])) {
            $variables['productType'] = Plugin::getInstance()->getProductTypes()->getProductTypeById($variables['productTypeId']);
        }

        if (empty($variables['productType'])) {
            throw new NotFoundHttpException('Product Type not found');
        }

        // Get the site
        // ---------------------------------------------------------------------

        if (Craft::$app->getIsMultiSite()) {
            // Only use the sites that the user has access to
            $variables['siteIds'] = Craft::$app->getSites()->getEditableSiteIds();
        } else {
            $variables['siteIds'] = [Craft::$app->getSites()->getPrimarySite()->id];
        }

        if (!$variables['siteIds']) {
            throw new ForbiddenHttpException('User not permitted to edit content in any sites supported by this product type');
        }

        if (empty($variables['site'])) {
            $variables['site'] = Craft::$app->getSites()->currentSite;

            if (!in_array($variables['site']->id, $variables['siteIds'], false)) {
                $variables['site'] = Craft::$app->getSites()->getSiteById($variables['siteIds'][0]);
            }

            $site = $variables['site'];
        } else {
            // Make sure they were requesting a valid site
            /** @var Site $site */
            $site = $variables['site'];
            if (!in_array($site->id, $variables['siteIds'], false)) {
                throw new ForbiddenHttpException('User not permitted to edit content in this site');
            }
        }

        if (!empty($variables['productTypeHandle'])) {
            $variables['productType'] = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle($variables['productTypeHandle']);
        }

        if (empty($variables['productType'])) {
            throw new HttpException(400, Craft::t('commerce', 'Wrong product type specified'));
        }

        // Get the product
        // ---------------------------------------------------------------------

        if (empty($variables['product'])) {
            if (!empty($variables['productId'])) {
                $variables['product'] = Plugin::getInstance()->getProducts()->getProductById($variables['productId'], $variables['site']->id);

                if (!$variables['product']) {
                    throw new NotFoundHttpException('Product not found');
                }
            } else {
                throw new NotFoundHttpException('ProductId not found');
            }
        }

        if ($variables['product']->id) {
            $variables['enabledSiteIds'] = Craft::$app->getElements()->getEnabledSiteIdsForElement($variables['product']->id);
        } else {
            $variables['enabledSiteIds'] = [];

            foreach (Craft::$app->getSites()->getEditableSiteIds() as $site) {
                $variables['enabledSiteIds'][] = $site;
            }
        }

        $variables['draft'] = Translations::$plugin->commerceRepository->getDraftById($variables['draftId']);
        $variables['product']->slug = $variables['draft']->slug;
    }

    /**
     * @throws ForbiddenHttpException
     */
    private function _prepVariables(array &$variables): void
    {
        $variables['tabs'] = [];

        /** @var ProductType $productType */
        $productType = $variables['productType'];
        /** @var Product $product */
        $product = $variables['draft'];
        $product->typeId = $variables['product']->typeId;

        DebugPanel::prependOrAppendModelTab(model: $productType, prepend: true);
        DebugPanel::prependOrAppendModelTab(model: $product, prepend: true);

        $form = $productType->getProductFieldLayout()->createForm($product);
        $variables['tabs'] = $form->getTabMenu();
        $variables['fieldsHtml'] = $form->render();
    }
}