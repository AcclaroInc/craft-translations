<?php

namespace acclaro\translations\migrations;

use Craft;
use craft\db\Migration;
use acclaro\translations\elements\Order;

/**
 * m240829_054957_drop_commerce_draft_table migration.
 */
class m240829_054957_drop_commerce_draft_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        echo "Dropping translations_commercedrafts table...\n";

        $this->dropTableIfExists('{{%translations_commercedrafts}}');

        echo "Done dropping translations_commercedrafts table...\n";

        echo "Re-indexing all existing orders...\n";

        $batchSize = 100;
        $offset = 0;
        $totalProcessed = 0;

        while (true) {
            $orders = Order::find()
                ->limit($batchSize)
                ->offset($offset)
                ->all();

            if (empty($orders)) {
                break;
            }

            foreach ($orders as $order) {
                try {
                    $order = Craft::$app->getElements()->getElementById($order->id, null, $order->sourceSite);
                    if ($order) {
                        $order->resaving = true;
                        if (!Craft::$app->getElements()->saveElement($order)) {
                            Craft::error('Failed to save order ID: ' . $order->id, __METHOD__);
                        }
                    }
                } catch (\Throwable $e) {
                    Craft::error('Error re-saving order ID: ' . $order->id . '. Error: ' . $e->getMessage(), __METHOD__);
                    throw $e;
                }
            }

            $totalProcessed += count($orders);
            $offset += $batchSize;

            echo "Processed $totalProcessed orders so far...\n";
        }

        echo "Done re-indexing all orders.\n";

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "mYYYYMMDD_HHMMSS_drop_commerce_draft_table cannot be reverted.\n";
        return false;
    }
}
