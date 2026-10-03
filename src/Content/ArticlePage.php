<?php

namespace LinkRobins\Wiki\Content;

use Flarum\Api\Client;
use Flarum\Extension\ExtensionManager;
use Flarum\Frontend\Document;
use Flarum\Http\UrlGenerator;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Server-side render of `/wiki/{id}`, the article page.
 *
 * Without this every article URL was served as the bare forum shell: the
 * forum's own title and description, no canonical link, and nothing inside
 * `#flarum-content`. A crawler that does not run the JavaScript app, or one
 * whose run of it fails, saw no article at all. When the app fails to boot,
 * core shows its "please refresh" alert and appends the text of
 * `#flarum-content` to it, which is why discussions still indexed and wiki
 * articles came out as soft 404s.
 *
 * Mirrors core's discussion page: the article is loaded through the API as the
 * visiting actor, so drafts, soft-deleted articles and anything else the API
 * hides stay hidden, and a miss throws, which the frontend turns into a real
 * 404 instead of an empty 200.
 */
class ArticlePage
{
    public function __construct(
        protected Client $api,
        protected UrlGenerator $url,
        protected ExtensionManager $extensions,
    ) {
    }

    public function __invoke(Document $document, Request $request): Document
    {
        $id = (string) Arr::get($request->getQueryParams(), 'id');

        $apiDocument = json_decode(
            json: $this->api
                ->withoutErrorHandling()
                ->withParentRequest($request)
                ->get('/linkrobins-wiki-articles/'.rawurlencode($id))
                ->getBody(),
            associative: false
        );

        $attributes = $apiDocument->data->attributes;
        $title = (string) ($attributes->title ?? '');
        $description = (string) ($attributes->excerpt ?? '');
        $canonical = $this->url->to('forum')->route('linkrobins-wiki.show', [
            'id' => ($attributes->slug ?? null) ?: $apiDocument->data->id,
        ]);

        $document->title = $title;
        $document->canonicalUrl = $canonical;

        // The forum app reads this instead of requesting the article again
        // (WikiShowPage::_preloadedOrFetch). Besides saving a round trip, it
        // keeps the page working for a crawler that cannot reach the API:
        // Googlebot obeys robots.txt for the requests a page makes, and SEO
        // extensions such as fof/sitemap disallow /api. Core does the same for
        // discussions, which is why those rendered while articles did not.
        $document->payload['apiDocument'] = $apiDocument;
        $document->content = $this->renderContent($title, (string) ($attributes->contentHtml ?? ''), $attributes->faq ?? []);

        if ($description !== '') {
            $document->meta['description'] = $description;
        }

        // Only editors can see a soft-deleted article, and a draft only its
        // author; neither is something a search engine should keep.
        if (! empty($attributes->isDeleted) || ! empty($attributes->isDraft)) {
            $document->meta['robots'] = 'noindex';
        }

        // fof/seo writes the social tags itself, de-duplicated by name, and it
        // already emits site-wide ones on every page. When it is enabled the
        // article's tags come from our page driver instead (see extend.php),
        // so writing them here as well would print every one twice.
        if (! $this->extensions->isEnabled('fof-seo')) {
            $this->addSocialTags($document, $title, $description, $canonical, $attributes);
        }

        return $document;
    }

    /**
     * @param array<int, object>|mixed $faq
     */
    protected function renderContent(string $title, string $contentHtml, mixed $faq): string
    {
        // contentHtml is the formatter's own render, the same trusted HTML core
        // prints for a post body; only the plain strings need escaping.
        $html = '<div class="container"><article>';
        $html .= '<h1>'.e($title).'</h1>';
        $html .= '<div class="Post-body">'.$contentHtml.'</div>';

        if (is_array($faq)) {
            foreach ($faq as $entry) {
                if (! is_object($entry) || empty($entry->question)) {
                    continue;
                }

                $html .= '<h2>'.e((string) $entry->question).'</h2>';
                $html .= '<div>'.((string) ($entry->answerHtml ?? '')).'</div>';
            }
        }

        return $html.'</article></div>';
    }

    protected function addSocialTags(Document $document, string $title, string $description, string $canonical, object $attributes): void
    {
        $tags = [
            'og:type' => 'article',
            'og:title' => $title,
            'og:url' => $canonical,
            'og:description' => $description,
            'article:published_time' => (string) ($attributes->createdAt ?? ''),
            'article:modified_time' => (string) ($attributes->lastEditedAt ?? ''),
        ];

        foreach ($tags as $property => $value) {
            if ($value !== '') {
                $document->head[] = '<meta property="'.e($property).'" content="'.e($value).'">';
            }
        }
    }
}
