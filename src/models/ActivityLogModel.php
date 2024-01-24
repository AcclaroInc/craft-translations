<?php

namespace acclaro\translations\models;

use craft\base\Model;
use craft\validators\DateTimeValidator;

class ActivityLogModel extends Model
{
    /**
     * @var int|null
     */
    public $id;

    public $targetClass;

    public $targetId;

    public $message;

    public $created;

    public $actions;

    public function init(): void
    {
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules[] = [['targetId', 'created', 'message'], 'required'];
        $rules[] = [['created'],  DateTimeValidator::class];
        $rules[] = [['message'], 'default', 'value' => ''];

        return $rules;
    }
}
