<?php

namespace LinkRobins\Wiki\Seo;

use Flarum\Api\Client;
use Flarum\Http\UrlGenerator;
use FoF\Seo\Page\PageDriverInterface;
use FoF\Seo\SeoProperties;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * fof/seo page driver for wiki articles.
 *
 * Registered only when fof/seo is enabled (see extend.php), so this class is
 * never loaded on a forum without it and the wiki keeps no dependency on it.
 * On those forums ArticlePage leaves the social tags to this driver, because
 * fof/seo already prints site-wide `og:` tags on every page and de-duplicates
 * by name only within its own set.
 */
class ArticleSeoPage implements PageDriverInterface
{
    public function __construct(
        protected Client $api,
        protected UrlGenerator $url,
    ) {
    }

    public function extensionDependencies(): array
    {
        return [];
    }

    public function handleRoutes(): array
    {
        return ['linkrobins-wiki.show'];
    }

    public function handle(ServerRequestInterface $request, SeoProperties $properties): void
    {
        $id = (string) Arr::get($request->getQueryParams(), 'id');

        try {
            $apiDocument = json_decode(
                json: $this->api
                    ->withoutErrorHandling()
                    ->withParentRequest($request)
                    ->get('/linkrobins-wiki-articles/'.rawurlencode($id))
                    ->getBody(),
                associative: false
            );
        } catch (Throwable) {
            // ArticlePage has already turned a missing article into a 404.
            return;
        }

        $attributes = $apiDocument->data->attributes;
        $url = $this->url->to('forum')->route('linkrobins-wiki.show', [
            'id' => ($attributes->slug ?? null) ?: $apiDocument->data->id,
        ]);

        $properties
            ->setTitle((string) $attributes->title, false, true)
            ->setUrl($url, false)
            ->setCanonicalUrl($url, false)
            ->setMetaPropertyTag('og:type', 'article')
            ->setSchemaJson('@type', 'Article');

        if (! empty($attributes->excerpt)) {
            $properties->setDescription((string) $attributes->excerpt);
        }

        if (! empty($attributes->createdAt)) {
            $properties->setPublishedOn((string) $attributes->createdAt);
        }

        if (! empty($attributes->lastEditedAt)) {
            $properties->setUpdatedOn((string) $attributes->lastEditedAt);
        }

        // fof/seo sets "index, follow" on every page before its drivers run,
        // so this is the only place a noindex for these survives.
        if (! empty($attributes->isDeleted) || ! empty($attributes->isDraft)) {
            $properties->setMetaTag('robots', 'noindex');
        }
    }
}
