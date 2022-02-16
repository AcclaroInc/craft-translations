<?php

namespace acclaro\translations\migrations;

use acclaro\translations\Constants;
use craft\db\Migration;

/**
 * m220216_121637_add_track_target_changes_to_orders_table migration.
 */
class m220216_121637_add_track_target_changes_to_orders_table extends Migration
{
	/**
	 * @inheritdoc
	 */
	public function safeUp()
	{
		echo "Adding translations_orders trackTargetChanges column...\n";
		$this->addColumn(Constants::TABLE_ORDERS, 'trackTargetChanges', $this->integer()->defaultValue(0));
		echo "Done adding translations_orders trackTargetChanges column...\n";
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown()
	{
		echo "m220216_121637_add_track_target_changes_to_orders_table cannot be reverted.\n";
		return false;
	}
}
