<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace acclaro\translations\elements\actions;

use Craft;
use craft\helpers\Json;
use craft\base\ElementAction;
use craft\elements\actions\Edit;

/**
 * OrderEdit represents a Edit element action.
 */
class OrderEdit extends Edit
{
     /**
     * @inheritdoc
     */
    public function getTriggerHtml(): ?string
    {
        $type = Json::encode(static::class);

        $js = <<<JS
            (() => {
                new Craft.ElementActionTrigger({
                    type: {$type},
                    batch: false,
                    validateSelection: function(\$selectedItems)
                    {
                        return Garnish.hasAttr(\$selectedItems.find('.element'), 'data-editable');
                    },
                    activate: function(\$selectedItems)
                    {
                        var \$element = \$selectedItems.find('.element:first');
                        location.href = Craft.getUrl(\$element.data('url'));
                    }
                });
            })();
            JS;

        Craft::$app->getView()->registerJs($js);
        return null;
    }
}
