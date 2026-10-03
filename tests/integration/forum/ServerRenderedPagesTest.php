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
