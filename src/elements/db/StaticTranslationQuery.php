<?php

namespace acclaro\translations\elements\db;

use craft\elements\db\ElementQuery;

class StaticTranslationQuery extends ElementQuery
{
    /**
     * @inheritdoc
     */
    public $source;
    public $translateStatus;
    public $pluginHandle;
    public $translateId;

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        parent::__set($name, $value);
    }

    /**
     * @inheritdoc
     */
    public function source($value)
    {
        $this->source = $value;
    }

    /**
     * @inheritdoc
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        return false;
    }
}