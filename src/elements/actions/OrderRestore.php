<?php

/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace acclaro\translations\elements\actions;

use craft\elements\actions\Restore;
use acclaro\translations\base\AlertsTrait;

/**
 * OrderDelete represents a Delete element action.
 *
 * Element types that make this action available should implement [[ElementInterface::getIsDeletable()]] to explicitly state whether they can be
 * deleted by the current user.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0.0
 */
class OrderRestore extends Restore
{
    use AlertsTrait;

    /**
     * @inheritdoc
     */
    public function setElementType(string $elementType): void
    {
        /** @var string|ElementInterface $elementType */
        /** @phpstan-var class-string<ElementInterface> $elementType */
        parent::setElementType($elementType);

        $this->successMessage = $this->getSuccessMessage('Orders restored.');
        $this->partialSuccessMessage = $this->getSuccessMessage('Some orders restored.');
        $this->failMessage = $this->getErrorMessage('Order not restored.');
    }
}
