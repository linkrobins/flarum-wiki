<?php

/*
 * This file is part of linkrobins/wiki.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace LinkRobins\Wiki\Tests\integration\forum;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * What a crawler that never runs the JavaScript app gets from wiki URLs.
 */
class ServerRenderedPagesTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('linkrobins-wiki');

        $now = Carbon::now();

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(), // id 2
            ],
            'linkrobins_wiki_articles' => [
                ['id' => 1, 'user_id' => 2, 'title' => 'Installing the widget', 'slug' => 'installing-the-widget', 'content' => '<t><p>Unpack the widget into the plugins folder.</p></t>', 'last_edited_at' => $now, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 2, 'user_id' => 2, 'title' => 'Retired page', 'slug' => 'retired-page', 'content' => '<t><p>Gone.</p></t>', 'last_edited_at' => $now, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => $now],
                ['id' => 3, 'user_id' => 2, 'title' => 'Unfinished draft', 'slug' => 'unfinished-draft', 'content' => '<t><p>Not yet.</p></t>', 'is_draft' => true, 'last_edited_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ],
        ]);
    }

    private function page(string $path): array
    {
        $response = $this->send($this->request('GET', $path));

        return [$response->getStatusCode(), $response->getBody()->getContents()];
    }

    #[Test]
    public function article_page_carries_its_own_title_meta_and_body(): void
    {
        [$status, $html] = $this->page('/wiki/installing-the-widget');

        $this->assertEquals(200, $status);
        $this->assertMatchesRegularExpression('#<title>[^<]*Installing the widget#', $html);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/wiki/installing-the-widget">', $html);
        $this->assertStringContainsString('<meta name="description" content="Unpack the widget into the plugins folder.">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Installing the widget">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);

        // The body has to be in the server-rendered fallback, which is what
        // core shows (and a crawler reads) when the JavaScript app fails.
        $fallback = substr($html, (int) strpos($html, '<noscript id="flarum-content">'));
        $this->assertStringContainsString('Unpack the widget into the plugins folder.', $fallback);
        $this->assertStringContainsString('<h1>Installing the widget</h1>', $fallback);

        // The app reads the article from the payload instead of the API, so a
        // crawler barred from /api by robots.txt still gets the page.
        preg_match('#<script id="flarum-json-payload" type="application/json">(.*?)</script>#s', $html, $m);
        $payload = json_decode($m[1] ?? '{}', true);
        $this->assertEquals('linkrobins-wiki-articles', $payload['apiDocument']['data']['type'] ?? null);
        $this->assertEquals('1', $payload['apiDocument']['data']['id'] ?? null);
    }

    #[Test]
    public function article_reached_by_id_is_canonical_to_its_slug(): void
    {
        [$status, $html] = $this->page('/wiki/1');

        $this->assertEquals(200, $status);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/wiki/installing-the-widget">', $html);
    }

    #[Test]
    public function missing_article_is_a_real_404(): void
    {
        [$status] = $this->page('/wiki/no-such-article');

        $this->assertEquals(404, $status);
    }

    #[Test]
    public function soft_deleted_article_is_a_404_for_guests(): void
    {
        [$status, $html] = $this->page('/wiki/retired-page');

        $this->assertEquals(404, $status);
        $this->assertStringNotContainsString('Retired page', $html);
    }

    #[Test]
    public function draft_is_a_404_for_guests(): void
    {
        [$status, $html] = $this->page('/wiki/unfinished-draft');

        $this->assertEquals(404, $status);
        $this->assertStringNotContainsString('Not yet.', $html);
    }

    #[Test]
    public function index_lists_visible_articles_for_crawlers(): void
    {
        [$status, $html] = $this->page('/wiki');

        $this->assertEquals(200, $status);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/wiki">', $html);

        $fallback = substr($html, (int) strpos($html, '<noscript id="flarum-content">'));
        $this->assertStringContainsString('<a href="http://localhost/wiki/installing-the-widget">Installing the widget</a>', $fallback);
        $this->assertStringNotContainsString('Retired page', $fallback);
        $this->assertStringNotContainsString('Unfinished draft', $fallback);
    }

    #[Test]
    public function index_preloads_its_first_page_for_the_app(): void
    {
        [$status, $html] = $this->page('/wiki');
        $this->assertEquals(200, $status);

        // Same reason as the article page: the index must not need /api.
        preg_match('#<script id="flarum-json-payload" type="application/json">(.*?)</script>#s', $html, $m);
        $payload = json_decode($m[1] ?? '{}', true);
        $types = array_unique(array_column($payload['apiDocument']['data'] ?? [], 'type'));
        $this->assertEquals(['linkrobins-wiki-articles'], array_values($types));
        // Categories come along, since the index groups articles by them.
        $this->assertArrayHasKey('included', $payload['apiDocument']);
    }

    #[Test]
    public function grouped_home_preloads_every_categorys_first_articles(): void
    {
        $this->setting('linkrobins-wiki.home_per_category', '2');
        $this->app();

        $old = Carbon::now()->subYear();
        $new = Carbon::now();
        $db = $this->database();
        $db->table('linkrobins_wiki_categories')->insert([
            ['id' => 1, 'name' => 'Getting started', 'slug' => 'getting-started', 'position' => 0, 'created_at' => $old, 'updated_at' => $old],
            ['id' => 2, 'name' => 'The launcher', 'slug' => 'the-launcher', 'position' => 1, 'created_at' => $old, 'updated_at' => $old],
        ]);
        // The primer was written long ago and never touched; the launcher's
        // articles are all newer. The primer must still be on the home page.
        $db->table('linkrobins_wiki_articles')->insert([
            ['id' => 10, 'user_id' => 2, 'category_id' => 1, 'title' => 'Primer', 'slug' => 'primer', 'content' => '<t><p>Start here.</p></t>', 'last_edited_at' => $old, 'created_at' => $old, 'updated_at' => $old],
            ['id' => 11, 'user_id' => 2, 'category_id' => 2, 'title' => 'Launcher one', 'slug' => 'launcher-one', 'content' => '<t><p>One.</p></t>', 'last_edited_at' => $new, 'created_at' => $new, 'updated_at' => $new],
            ['id' => 12, 'user_id' => 2, 'category_id' => 2, 'title' => 'Launcher two', 'slug' => 'launcher-two', 'content' => '<t><p>Two.</p></t>', 'last_edited_at' => $new, 'created_at' => $new, 'updated_at' => $new],
            ['id' => 13, 'user_id' => 2, 'category_id' => 2, 'title' => 'Launcher three', 'slug' => 'launcher-three', 'content' => '<t><p>Three.</p></t>', 'last_edited_at' => $new, 'created_at' => $new, 'updated_at' => $new],
            ['id' => 14, 'user_id' => 2, 'category_id' => 2, 'title' => 'Launcher four', 'slug' => 'launcher-four', 'content' => '<t><p>Four.</p></t>', 'last_edited_at' => $new, 'created_at' => $new, 'updated_at' => $new],
        ]);

        [$status, $html] = $this->page('/wiki');
        $this->assertEquals(200, $status);

        preg_match('#<script id="flarum-json-payload" type="application/json">(.*?)</script>#s', $html, $m);
        $document = json_decode($m[1] ?? '{}', true)['apiDocument'] ?? [];

        $this->assertEquals(2, $document['meta']['linkrobinsWikiHome']['perCategory'] ?? null);

        $byGroup = [];
        foreach ($document['data'] ?? [] as $article) {
            $byGroup[$article['relationships']['category']['data']['id'] ?? 'none'][] = (int) $article['id'];
        }

        // Every group, each with one more than it shows (so the page knows
        // there is a "See all"), and only what a guest may see: the deleted
        // article and the draft from setUp stay out of "Other".
        $this->assertSame([10], $byGroup['1'] ?? null);
        $this->assertCount(3, $byGroup['2'] ?? []);
        $this->assertSame([1], $byGroup['none'] ?? null);

        // Crawlers still get the plain list of every article.
        $this->assertStringContainsString('Primer', $html);
        $this->assertStringContainsString('Launcher four', $html);
    }

    #[Test]
    public function later_index_pages_are_not_preloaded(): void
    {
        // The test request does not parse a query string out of the path, so
        // the page number goes in as a query parameter.
        $html = $this->send($this->request('GET', '/wiki')->withQueryParams(['page' => '2']))->getBody()->getContents();

        preg_match('#<script id="flarum-json-payload" type="application/json">(.*?)</script>#s', $html, $m);
        $payload = json_decode($m[1] ?? '{}', true);
        // Core always sends the key; nothing is preloaded into it here.
        $this->assertEmpty($payload['apiDocument'] ?? null);
    }

    #[Test]
    public function editor_pages_are_noindex(): void
    {
        [, $html] = $this->page('/wiki/new');
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $html);

        [, $html] = $this->page('/wiki/installing-the-widget/edit');
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $html);
    }

    #[Test]
    public function public_article_is_not_noindex(): void
    {
        [, $html] = $this->page('/wiki/installing-the-widget');

        $this->assertStringNotContainsString('noindex', $html);
    }
}
