<?php

namespace LinkRobins\Wiki\Content;

use Flarum\Api\Client;
use Flarum\Frontend\Document;
use Flarum\Http\UrlGenerator;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Server-side render of `/wiki`: a titled page with a plain, paginated list of
 * article links, so a crawler that does not run the JavaScript app can still
 * find every article. Pagination follows core's discussion list (`?page=N`,
 * with prev/next links in the head).
 */
class IndexPage
{
    public const PER_PAGE = 25;

    public function __construct(
        protected Client $api,
        protected UrlGenerator $url,
        protected TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Document $document, Request $request): Document
    {
        $page = max(1, intval(Arr::get($request->getQueryParams(), 'page')));

        $apiDocument = json_decode(
            json: $this->api
                ->withoutErrorHandling()
                ->withParentRequest($request)
                ->withQueryParams([
                    'page' => ['offset' => ($page - 1) * self::PER_PAGE, 'limit' => self::PER_PAGE],
                ])
                ->get('/linkrobins-wiki-articles')
                ->getBody(),
            associative: false
        );

        $title = $this->translator->trans('linkrobins-wiki.forum.nav');
        $html = '<div class="container"><h1>'.e($title).'</h1><ul>';

        foreach ($apiDocument->data ?? [] as $article) {
            $href = $this->url->to('forum')->route('linkrobins-wiki.show', [
                'id' => ($article->attributes->slug ?? null) ?: $article->id,
            ]);

            $html .= '<li><a href="'.e($href).'">'.e((string) ($article->attributes->title ?? '')).'</a>';

            if (! empty($article->attributes->excerpt)) {
                $html .= ' - '.e((string) $article->attributes->excerpt);
            }

            $html .= '</li>';
        }

        $html .= '</ul></div>';

        $document->title = $title;
        $document->content = $html;
        $document->canonicalUrl = $this->url->to('forum')->route('linkrobins-wiki.index');
        $document->page = $page;
        $document->hasNextPage = isset($apiDocument->links->next);

        return $document;
    }
}
