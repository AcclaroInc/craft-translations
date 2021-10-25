<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace acclaro\translations\elements\actions;

use Craft;
use craft\elements\actions\Delete;

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
    public function getConfirmationMessage()
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

        return Craft::t('app', 'Are you sure you want to trash the selected {type}?', [
            'type' => $elementType::pluralLowerDisplayName(),
        ]);
    }
}
