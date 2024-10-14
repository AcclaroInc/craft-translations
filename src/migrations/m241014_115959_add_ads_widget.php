<?php

namespace acclaro\translations\migrations;

use craft\db\Migration;
use acclaro\translations\widgets\Ads;
use acclaro\translations\Translations;

/**
 * m241014_115959_add_ads_widget migration.
 */
class m241014_115959_add_ads_widget extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $widgetRepository = Translations::$plugin->widgetRepository;

        $adsWidget = $widgetRepository->createWidget(Ads::class);

        $widgetRepository->saveWidget($adsWidget);

        $allWidgets = $widgetRepository->getAllWidgets();
        $allWidgetIds = [];

        foreach($allWidgets as $widget) {
            if ($widget->getTitle() == "Acclaro Features") {
                array_unshift($allWidgetIds, $widget->id);
            } else {
                array_push($allWidgetIds, $widget->id);
            }
        }
        $widgetRepository->reorderWidgets($allWidgetIds, false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m241014_115959_add_ads_widget cannot be reverted.\n";
        return true;
    }
}
