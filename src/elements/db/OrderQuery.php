<?php

namespace acclaro\translations\elements\db;

use craft\elements\db\ElementQuery;

class OrderQuery extends ElementQuery
{
    /**
     * @inheritdoc
     */
    protected $defaultOrderBy = ['translations_orders.dateCreated' => SORT_DESC];

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('translations_orders');

        $this->query->select(['translations_orders.*']);

        return parent::beforePrepare();
    }
}
