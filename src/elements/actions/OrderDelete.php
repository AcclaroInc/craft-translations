<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace acclaro\translations\elements\actions;

use Craft;
use craft\commerce\elements\Product;
use craft\elements\actions\Delete;
use craft\elements\Asset;
use acclaro\translations\Translations;
use acclaro\translations\base\AlertsTrait;
use craft\elements\db\ElementQueryInterface;
use craft\elements\GlobalSet;

/**
 * OrderDelete represents a Delete element action.
 *
 * Element types that make this action available should implement [[ElementInterface::getIsDeletable()]] to explicitly state whether they can be
 * deleted by the current user.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0.0
 */
class OrderDelete extends Delete
{
    use AlertsTrait;

    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        if ($this->hard) {
            return Craft::t('app', 'Delete permanently');
        }

        if ($this->withDescendants) {
            return Craft::t('app', 'Delete (with descendants)');
        }

        return Craft::t('app', 'Delete');
    }

    /**
     * @inheritdoc
     */
    public function getConfirmationMessage(): ?string
    {
        if ($this->confirmationMessage !== null) {
            return $this->confirmationMessage;
        }

        /** @var ElementInterface|string $elementType */
        $elementType = $this->elementType;

        if ($this->hard) {
            return Craft::t('app', 'Are you sure you want to permanently delete the selected {type}?', [
                'type' => $elementType::pluralLowerDisplayName(),
            ]);
        }

        if ($this->withDescendants) {
            return Craft::t('app', 'Are you sure you want to delete the selected {type} along with their descendants?', [
                'type' => $elementType::pluralLowerDisplayName(),
            ]);
        }

        return Craft::t('app', 'Are you sure you want to delete the selected {type}?', [
            'type' => $elementType::pluralLowerDisplayName(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        $this->successMessage = $this->getSuccessMessage("Order Deleted.");

        if ($this->hard) {
            foreach ($query->all() as $order) {
                /** @var \acclaro\translations\elements\Order $order */
                foreach ($order->getFiles() as $file) {
                    if ($file->hasDraft()) {
                        $element = $file->getElement();

                        // Skip if the entry has been deleted
                        if (! $element) continue;

                        switch (get_class($element)) {
                            case ($element instanceof GlobalSet):
                                $elementRepository = Translations::$plugin->globalSetDraftRepository;
                                $draft = $elementRepository->getDraftById($file->draftId);
                                $elementRepository->deleteDraft($draft);
                                break;
                            case ($element instanceof Asset):
                                $elementRepository = Translations::$plugin->assetDraftRepository;
                                $draft = $elementRepository->getDraftById($file->draftId);
                                $elementRepository->deleteDraft($draft);
                                break;
                            case ($element instanceof Product):
                                $elementRepository = Translations::$plugin->commerceRepository;
                                $draft = $elementRepository->getDraftById($file->draftId);
                                $elementRepository->deleteDraft($draft);
                                break;
                            default:
                                $elementRepository = Translations::$plugin->draftRepository;
                                $elementRepository->deleteDraft($file->draftId, $file->targetSite);
                        }
                    }
                }
            }
        }

        return parent::performAction($query);
    }
}
