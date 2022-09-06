<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\widgets;

use Craft;
use craft\base\Widget;
use acclaro\translations\Constants;
use acclaro\translations\Translations;
use acclaro\translations\records\WidgetRecord;

/**
 * @author    Acclaro
 * @package   Translations
 * @since     1.0.2
 */
class News extends Widget
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Acclaro News');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return Craft::getAlias('@app/icons/feed.svg');
    }

    public $limit = 5;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules[] = ['limit', 'number', 'integerOnly' => true];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function minColspan()
    {
        return 1;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('translations/_components/widgets/News/settings',
            [
                'widget' => $this
            ]);
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        $view = Craft::$app->getView();

        $articles = $this->_getArticles();

        return $view->renderTemplate('translations/_components/widgets/News/body', ['articles' => $articles]);
    }

    public static function doesUserHaveWidget(string $type): bool
    {
        return WidgetRecord::find()
            ->where([
                'userId' => Craft::$app->getUser()->getIdentity()->id,
                'type' => $type,
            ])
            ->exists();
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return (static::allowMultipleInstances() || !static::doesUserHaveWidget(static::class));
    }

    /**
     * Returns the recent articles from Acclaro Blog RSS feed
     *
     * @return array
     */
    private function _getArticles(): array
    {
        $articles = [];

        $client = Craft::createGuzzleClient(array(
            'base_uri' => 'https://www.acclaro.com/',
            'timeout' => 2.0,
            'verify' => false
        ));

        try {
            $response = $client->get('feed/');
        } catch (\Exception $e) {
            Translations::$plugin->logHelper->log("[" . __METHOD__ . "] . $e", Constants::LOG_LEVEL_ERROR);
            return [];
        }

        $data = $response->getBody()->getContents();
        $feed = simplexml_load_string($data);

        $i = 0;
        foreach ($feed->channel->item as $key => $article) {
            $articles[$i]['title'] = $article->title;
            $articles[$i]['link'] = $article->link;
            $articles[$i]['pubDate'] = date('m/d/Y', strtotime($article->pubDate));

            if ($i + 1 < $this->limit) {
                $i++;
            } else {
                break;
            }
        }

        return $articles;
    }

    /**
     * Returns whether the widget can be selected more than once.
     *
     * @return bool Whether the widget can be selected more than once
     */
    protected static function allowMultipleInstances(): bool
    {
        return false;
    }
}
