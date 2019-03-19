<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translationsforcraft\services\repository;

use acclaro\translationsforcraft\TranslationsForCraft;
use Craft;
use craft\elements\User;

use Exception;

class UserRepository
{
    public function getUserById($id)
    {
        return User::find()->id($id)->one();
    }
}