<?php

/**
 * Translations for Craft plugin for Craft CMS 5.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services;

use craft\helpers\Queue;
use yii\queue\JobInterface;

class QueueHelper
{
    /**
     * The job priority, if supported. Jobs with a lower priority will be executed first. (Default is 1024.)
     */
    public static $priority = 1;
    /**
     * The maximum time the queue should wait around for the job to be handled before assuming it failed.
     */
    public static $ttr = 1500;
    /**
     * The execution delay (in seconds), if supported.
     */
    public static $delay = null;

    /**
     * Push a job onto the queue.
     * @param JobInterface $job The job to execute via the queue.
     * @return string|null The new job ID
     */
    public static function push(JobInterface $job) {
        return Queue::push($job, self::$priority, self::$delay, self::$ttr);
    }
}