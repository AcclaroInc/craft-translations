<?php

namespace acclaro\translations\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class OrderQuery extends ElementQuery
{
    /**
     * @inheritdoc
     */
    protected $defaultOrderBy = ['translations_orders.dateCreated' => SORT_DESC];

    public $elementIds;

    public function elementIds($value)
    {
        $this->elementIds = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('translations_orders');

        $this->query->select([
            'translations_orders.id',
            'translations_orders.translatorId',
            'translations_orders.ownerId',
            'translations_orders.sourceSite',
            'translations_orders.targetSites',
            'translations_orders.status',
            'translations_orders.requestedDueDate',
            'translations_orders.comments',
            'translations_orders.activityLog',
            'translations_orders.dateOrdered',
            'translations_orders.serviceOrderId',
            'translations_orders.entriesCount',
            'translations_orders.wordCount',
            'translations_orders.elementIds'
        ]);

        // $this->subQuery->andWhere(Db::parseParam("translations_orders.elementIds", '"122"', "like"));

        return parent::beforePrepare();
    }
}