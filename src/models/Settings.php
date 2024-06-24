<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\models;

use craft\base\Model;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.0
 */
class Settings extends Model
{
    public $chkDuplicateEntries = true;

    public $trackSourceChanges;

    public $trackTargetChanges;

    public $apiLogging;

    public $twigSearchFilterSingleQuote = "";

    public $twigSearchFilterDoubleQuote = "";

    public $targetStringPosition = "";

    /** @var int The Volume ID where uploads will be saved */
    public $uploadVolume = 0;

    public function rules(): array
    {
        return [];
    }
}
