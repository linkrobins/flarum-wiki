<?php

namespace LinkRobins\Wiki\Content;

use Flarum\Api\Client;
use Flarum\Frontend\Document;
use Flarum\Http\UrlGenerator;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
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
        protected SettingsRepositoryInterface $settings,
    ) {
    }

    /**
     * How many articles each category shows on the home page, from the admin
     * setting, kept to a sane range. Shared with the forum serializer so the
     * page and the preload below always agree.
     */
    public static function perCategory(mixed $value): int
    {
        return max(1, min(50, (int) ($value ?: 5)));
    }

    public function __invoke(Document $document, Request $request): Document
    {
        $page = max(1, intval(Arr::get($request->getQueryParams(), 'page')));

        $apiDocument = json_decode(
            json: $this->api
                ->withoutErrorHandling()
                ->withParentRequest($request)
                // The same query the index page's own first load makes
                // (utils/api.ts loadArticles), so the first page can be handed
                // to it below instead of being fetched again.
                ->withQueryParams([
                    'sort' => '-lastEditedAt',
                    'include' => 'user,category',
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

        // Preload the first page for the index page, the way core preloads
        // the discussion list. Without it the page fetched its list from /api
        // after loading, which Googlebot does not do on forums whose
        // robots.txt disallows /api (fof/sitemap's does): the wiki index came
        // out as an empty list and an error. Later pages are not preloaded;
        // the index page only ever shows the first. On the default grouped
        // home page it is that page's own document instead.
        if ($page === 1) {
            $document->payload['apiDocument'] = $this->homeDocument($request) ?? $apiDocument;
        }

        return $document;
    }

    /**
     * The default home page's articles: each category's first few, in the
     * order the home page shows them, merged into one document the page can
     * group. The page needs one more than it shows to know whether a group
     * gets a "See all" link, so that is what is fetched.
     *
     * Null when the home page is not grouped: a custom layout replaces it,
     * and fewer than two non-empty groups is shown as the plain list, which
     * the first-page document above already is.
     *
     * @return object|null
     */
    protected function homeDocument(Request $request): ?object
    {
        if (trim((string) $this->settings->get('linkrobins-wiki.index_layout')) !== '') {
            return null;
        }

        $api = $this->api->withoutErrorHandling()->withParentRequest($request);
        $limit = self::perCategory($this->settings->get('linkrobins-wiki.home_per_category')) + 1;

        $categories = json_decode(
            json: $api->withQueryParams(['sort' => 'position', 'page' => ['limit' => 100]])
                ->get('/linkrobins-wiki-categories')
                ->getBody(),
            associative: false
        );

        $groups = array_map(fn ($category) => (string) $category->id, $categories->data ?? []);
        $groups[] = 'none';

        $data = [];
        $included = [];
        $nonEmpty = 0;

        foreach ($groups as $group) {
            $document = json_decode(
                json: $api->withQueryParams([
                    'filter' => ['categoryId' => $group],
                    'sort' => 'position,-lastEditedAt',
                    'include' => 'user,category',
                    'page' => ['limit' => $limit],
                ])->get('/linkrobins-wiki-articles')->getBody(),
                associative: false
            );

            if (empty($document->data)) {
                continue;
            }

            $nonEmpty++;
            array_push($data, ...$document->data);

            foreach ($document->included ?? [] as $resource) {
                $included[$resource->type.':'.$resource->id] = $resource;
            }
        }

        if ($nonEmpty < 2) {
            return null;
        }

        return (object) [
            'data' => $data,
            'included' => array_values($included),
            // Tells the page this is the grouped home, not a page of the list.
            'meta' => (object) ['linkrobinsWikiHome' => (object) ['perCategory' => $limit - 1]],
        ];
    }
}
