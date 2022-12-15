<?php

namespace acclaro\translations\services\repository;

use Craft;
use acclaro\translations\Constants;
use acclaro\translations\services\api\CraftApiClient;

class SiteRepository
{
    protected $supportedSites = array();
    protected $isoMapping;

    public function __construct()
    {
        foreach (Craft::$app->sites->getAllSiteIds() as $key => $site) {
            $this->supportedSites[] = $site;
        }
    }

    public function getSiteLanguage($siteId)
    {
        $site = Craft::$app->sites->getSiteById($siteId);

        if (!in_array($siteId, $this->supportedSites)) {
            return;
        }

        $language = $site->language;

        $language = $this->normalizeLanguage($site);

        return $language;
    }
    
    public function getSiteCode($siteId): string
    {
        $site = Craft::$app->sites->getSiteById($siteId);

        if (!in_array($siteId, $this->supportedSites)) {
            return '';
        }

        return $this->normalizeLanguage($site->language);
    }

    public function isSiteSupported($id)
    {
        return in_array($id, $this->supportedSites);
    }

    public function getSiteLanguageDisplayName($siteId)
    {
        $language = $this->getSiteLanguage($siteId);

        if ($language) {
            $displayName = Craft::$app->i18n->getLocaleById($language)->getDisplayName();
        } else {
            $displayName = '<s class="light">Deleted</s>';
        }

        return $displayName;
    }

    public function normalizeLanguage($language)
    {
        $language = str_replace('_', '-', $language);

        if (! $this->isoMapping) {
            $this->isoMapping = (new CraftApiClient())->getAliases();
        }

        return strtolower($this->isoMapping[$language] ?? $language);
    }

    public function getLanguages($namePrefix = '', $excludeSite = null)
    {
        $languages = array();

        foreach ($this->supportedSites as $site) {
            if ($excludeSite === $site) {
                continue;
            }

            $languages[$site] = $namePrefix.$this->getSiteLanguageDisplayName($site);
        }

        asort($languages);

        return $languages;
    }

    /**
     * getAllSitesHandle
     *
     * @return void
     */
    public function getAllSitesHandle()
    {
        $allSitesHandle = [];
        $allSites = Craft::$app->getSites()->getAllSites();

        foreach($allSites as $site)
        {
            $allSitesHandle[$site->id] = $site->handle;
        }

        return $allSitesHandle;
    }
}
