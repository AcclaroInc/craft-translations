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

    public $status;

    public $elementIds;
    
    public function status($value)
    {
        $this->status = $value;

        return $this;
    }

    public function elementIds($value)
    {
        $this->elementIds = $value;

        return $this;
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case 'status':
                $this->elementIds($value);
                break;
            case 'elementIds':
                $this->elementIds($value);
                break;
            default:
                parent::__set($name, $value);
        }
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

        if ($this->status) {
            if (is_array($this->status)) {
                $this->subQuery->andWhere(array('in', 'translations_orders.status', $this->status));
            } else if ($this->status !== '*') {
                $this->subQuery->andWhere('translations_orders.status = :status', array(':status' => $this->status));
            }
        }

        if ($this->elementIds) {
            $this->subQuery->andWhere(Db::parseParam("translations_orders.elementIds", $this->elementIds, "like"));
        }

        return parent::beforePrepare();
    }
}