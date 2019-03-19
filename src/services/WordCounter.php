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

class WordCounter
{
    public function getWordCount($str)
    {
        if ($str === '' || $str === null || $str === false) {
            return 0;
        }

        return count(preg_split('~[^\p{L}\p{N}\']+~u', $str));
    }
}
