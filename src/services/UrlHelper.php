<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services;

class UrlHelper
{
    public function __call($name, $arguments)
    {
        return call_user_func_array("\\craft\\helpers\\UrlHelper::{$name}", $arguments);
    }
}