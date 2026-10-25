<?php

/*
 * This file is part of linkrobins/wiki.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace LinkRobins\Wiki\Tests\integration\linkpreview;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

class LinkPreviewCardsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('linkrobins-wiki', 'datlechin-link-preview');

        $edited = Carbon::parse('2026-10-01 12:00:00');

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'linkrobins_wiki_categories' => [
                ['id' => 1, 'name' => 'Getting started', 'slug' => 'getting-started', 'position' => 1, 'created_at' => $edited, 'updated_at' => $edited],
            ],
            'linkrobins_wiki_articles' => [
                ['id' => 1, 'category_id' => 1, 'user_id' => 2, 'title' => 'Installing the widget', 'slug' => 'installing-the-widget', 'content' => '<t><p>Unpack it and plug it in.</p></t>', 'last_edited_at' => $edited, 'created_at' => $edited, 'updated_at' => $edited],
                ['id' => 2, 'user_id' => 2, 'title' => 'Unfinished draft', 'slug' => 'unfinished-draft', 'content' => '<t><p>Body.</p></t>', 'is_draft' => true, 'last_edited_at' => $edited, 'created_at' => $edited, 'updated_at' => $edited],
                ['id' => 3, 'user_id' => 2, 'title' => 'Retired page', 'slug' => 'retired-page', 'content' => '<t><p>Body.</p></t>', 'deleted_at' => $edited, 'last_edited_at' => $edited, 'created_at' => $edited, 'updated_at' => $edited],
                ['id' => 4, 'user_id' => 2, 'title' => 'Broken body', 'slug' => 'broken-body', 'content' => '<r><p>Never closed', 'last_edited_at' => $edited, 'created_at' => $edited, 'updated_at' => $edited],
            ],
            'linkrobins_wiki_article_slugs' => [
                ['article_id' => 1, 'slug' => 'widget-setup', 'created_at' => $edited],
            ],
        ]);
    }

    private function preview(string $url, ?int $actor = null): array
    {
        $request = $this->request('POST', '/api/datlechin-link-preview', [
            'json' => ['url' => $url],
            'authenticatedAs' => $actor,
        ])->withAttribute('bypassCsrfToken', true);

        return $this->data($this->send($request));
    }

    private function data(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true)['data'] ?? [];
    }

    #[Test]
    public function an_article_link_is_a_card_about_the_article(): void
    {
        $data = $this->preview('http://localhost/wiki/installing-the-widget');

        $this->assertEquals('linkrobins-wiki.show', $data['type']);
        $this->assertEquals('Installing the widget', $data['title']);
        $this->assertEquals('Unpack it and plug it in.', $data['description']);
    }

    #[Test]
    public function the_card_lists_the_category_the_author_and_the_last_edit(): void
    {
        $meta = $this->preview('http://localhost/wiki/installing-the-widget')['meta'];

        $this->assertEquals(['type' => 'text', 'text' => 'Getting started'], $meta[0]);
        $this->assertEquals(['type' => 'user', 'text' => 'normal'], $meta[1]);
        $this->assertEquals('date', $meta[2]['type']);
        $this->assertEquals('2026-10-01T12:00:00+00:00', Carbon::parse($meta[2]['date'])->toAtomString());
    }

    #[Test]
    public function the_id_and_an_old_slug_reach_the_same_article(): void
    {
        $this->assertEquals('Installing the widget', $this->preview('http://localhost/wiki/1')['title']);
        $this->assertEquals('Installing the widget', $this->preview('http://localhost/wiki/widget-setup')['title']);
    }

    #[Test]
    public function a_draft_is_a_card_only_for_those_who_can_open_it(): void
    {
        $this->assertEquals('no_metadata', $this->preview('http://localhost/wiki/unfinished-draft')['error']);
        $this->assertEquals('Unfinished draft', $this->preview('http://localhost/wiki/unfinished-draft', 2)['title']);
    }

    #[Test]
    public function a_deleted_article_is_a_card_only_for_editors(): void
    {
        $this->assertEquals('no_metadata', $this->preview('http://localhost/wiki/retired-page')['error']);
        $this->assertEquals('no_metadata', $this->preview('http://localhost/wiki/retired-page', 2)['error']);
        $this->assertEquals('Retired page', $this->preview('http://localhost/wiki/retired-page', 1)['title']);
    }

    #[Test]
    public function a_body_that_will_not_parse_costs_the_card_only_its_description(): void
    {
        $data = $this->preview('http://localhost/wiki/broken-body');

        $this->assertEquals('Broken body', $data['title']);
        $this->assertNull($data['description']);
    }

    #[Test]
    public function an_article_nobody_wrote_is_no_card(): void
    {
        $this->assertEquals('no_metadata', $this->preview('http://localhost/wiki/nothing-here')['error']);
    }

    #[Test]
    public function the_wiki_home_counts_what_the_reader_can_open(): void
    {
        $guest = $this->preview('http://localhost/wiki');
        $author = $this->preview('http://localhost/wiki', 2);

        $this->assertEquals('linkrobins-wiki.index', $guest['type']);
        $this->assertEquals([['type' => 'count', 'key' => 'linkrobins-wiki.forum.link_preview.articles', 'count' => 2]], $guest['meta']);
        $this->assertEquals(3, $author['meta'][0]['count'], 'their own draft counts too');
    }

    #[Test]
    public function a_filtered_wiki_home_is_no_card(): void
    {
        $this->assertEquals('no_metadata', $this->preview('http://localhost/wiki?category=1')['error']);
        $this->assertEquals('no_metadata', $this->preview('http://localhost/wiki?q=widget')['error']);
    }

    #[Test]
    public function the_editor_pages_are_not_previewable(): void
    {
        $this->assertEquals('not_previewable', $this->preview('http://localhost/wiki/new')['error']);
        $this->assertEquals('not_previewable', $this->preview('http://localhost/wiki/installing-the-widget/edit')['error']);
    }
}
