<?php

/*
 * This file is part of linkrobins/wiki.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace LinkRobins\Wiki\LinkPreview;

use Datlechin\LinkPreview\Internal\Card;
use Datlechin\LinkPreview\Internal\MatchedRoute;
use Datlechin\LinkPreview\Internal\Meta;
use Datlechin\LinkPreview\Internal\RouteCard;
use Flarum\User\User;
use LinkRobins\Wiki\Access\WikiAbilities;
use LinkRobins\Wiki\WikiArticle;
use Psr\Log\LoggerInterface;

class ArticleCard implements RouteCard
{
    public function __construct(
        protected LoggerInterface $log,
    ) {
    }

    public function card(MatchedRoute $route, User $actor): ?Card
    {
        $article = WikiArticle::findByAddress(
            WikiAbilities::scopeVisibleArticles(WikiArticle::query(), $actor),
            $route->parameter('id'),
        );

        if (! $article) {
            return null;
        }

        $meta = [];

        if ($article->category) {
            $meta[] = Meta::text($article->category->name);
        }

        if ($article->user) {
            $meta[] = Meta::user($article->user->username);
        }

        if ($article->last_edited_at) {
            $meta[] = Meta::date($article->last_edited_at);
        }

        return new Card(
            title: $article->title,
            description: $this->excerpt($article),
            meta: $meta,
        );
    }

    protected function excerpt(WikiArticle $article): ?string
    {
        try {
            return $article->excerpt() ?: null;
        } catch (\Throwable $e) {
            $this->log->warning('[linkrobins/wiki] excerpt failed', ['exception' => $e]);

            return null;
        }
    }
}
