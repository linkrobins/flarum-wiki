<?php

namespace LinkRobins\Wiki\Content;

use Flarum\Frontend\Document;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Keeps the article editor out of search results.
 *
 * This is a forum-wide content callback with a low priority rather than a
 * route callback, on purpose. Route callbacks run first (priority 100), so an
 * SEO extension that sets `robots` on every page, as fof/seo does with
 * "index, follow", would overwrite a `noindex` set there. Running last means
 * ours is the value that reaches the page.
 */
class NoIndexEditorPages
{
    public const ROUTES = ['linkrobins-wiki.compose', 'linkrobins-wiki.edit'];

    public function __invoke(Document $document, Request $request): void
    {
        if (in_array($request->getAttribute('routeName'), self::ROUTES, true)) {
            $document->meta['robots'] = 'noindex';
        }
    }
}
