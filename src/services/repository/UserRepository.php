<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\repository;

use acclaro\translations\Translations;
use Craft;
use craft\elements\User;

use Exception;

class UserRepository
{
    public function getUserById($id)
    {
        return User::find()->id($id)->one();
    }

    public function userHasAccess($permission) {

        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser->can($permission)) {
            Craft::$app->getSession()->setError(Translations::$plugin->translator->translate('app', 'User does not have permission to perform this action.'));

            return false;
        }

        return true;
    }
}