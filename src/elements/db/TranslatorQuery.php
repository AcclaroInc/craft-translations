<?php

namespace acclaro\translations\elements\db;

use craft\elements\db\ElementQuery;

class TranslatorQuery extends ElementQuery
{
    /**
     * @inheritdoc
     */
    public $id;
    public $label;
    public $service;
    public $status;
    public $settings;

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
    public function service($value)
    {
        $this->service = $value;
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
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        return false;
    }
}