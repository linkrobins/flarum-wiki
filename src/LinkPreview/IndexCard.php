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
use Flarum\Locale\TranslatorInterface;
use Flarum\User\User;
use LinkRobins\Wiki\Access\WikiAbilities;
use LinkRobins\Wiki\WikiArticle;

class IndexCard implements RouteCard
{
    public function __construct(
        protected TranslatorInterface $translator,
    ) {
    }

    public function card(MatchedRoute $route, User $actor): ?Card
    {
        if (parse_url($route->url, PHP_URL_QUERY)) {
            return null;
        }

        $count = WikiAbilities::scopeVisibleDrafts(WikiArticle::query(), $actor)->count();

        return new Card(
            title: $this->translator->trans('linkrobins-wiki.forum.nav'),
            meta: [Meta::count('linkrobins-wiki.forum.link_preview.articles', $count)],
        );
    }
}
