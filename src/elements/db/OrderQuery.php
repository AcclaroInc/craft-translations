<?php

namespace acclaro\translationsforcraft\elements\db;

use craft\elements\db\ElementQuery;

class OrderQuery extends ElementQuery
{
    /**
     * @inheritdoc
     */
    protected $defaultOrderBy = ['translationsforcraft_orders.dateCreated' => SORT_DESC];

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('translationsforcraft_orders');

        $this->query->select([
            'translationsforcraft_orders.id',
            'translationsforcraft_orders.translatorId',
            'translationsforcraft_orders.ownerId',
            'translationsforcraft_orders.sourceSite',
            'translationsforcraft_orders.targetSites',
            'translationsforcraft_orders.status',
            'translationsforcraft_orders.requestedDueDate',
            'translationsforcraft_orders.comments',
            'translationsforcraft_orders.activityLog',
            'translationsforcraft_orders.dateOrdered',
            'translationsforcraft_orders.serviceOrderId',
            'translationsforcraft_orders.entriesCount',
            'translationsforcraft_orders.wordCount',
            'translationsforcraft_orders.elementIds'
        ]);

        return parent::beforePrepare();
    }
}