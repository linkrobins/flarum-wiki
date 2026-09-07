<?php

/*
 * This file is part of linkrobins/wiki.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace LinkRobins\Wiki\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ListArticlesTest extends TestCase
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
                ['id' => 1, 'user_id' => 2, 'title' => 'Live article', 'content' => '<t><p>Body.</p></t>', 'last_edited_at' => $now, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 2, 'user_id' => 2, 'title' => 'Deleted article', 'content' => '<t><p>Body.</p></t>', 'last_edited_at' => $now, 'deleted_at' => $now, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 3, 'user_id' => 2, 'title' => 'Ordered second', 'content' => '<t><p>Body.</p></t>', 'position' => 2, 'last_edited_at' => $now, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 4, 'user_id' => 2, 'title' => 'Ordered first', 'content' => '<t><p>Body mentioning gribbet.</p></t>', 'position' => 1, 'last_edited_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ],
        ]);
    }

    /**
     * @return list<int>
     */
    private function listedIds(string $body): array
    {
        $ids = array_map(
            fn (array $row) => (int) $row['id'],
            json_decode($body, true)['data']
        );
        sort($ids);

        return $ids;
    }

    #[Test]
    public function guests_see_only_live_articles(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/linkrobins-wiki-articles')
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([1, 3, 4], $this->listedIds($response->getBody()->getContents()));
    }

    #[Test]
    public function regular_users_see_only_live_articles(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/linkrobins-wiki-articles', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([1, 3, 4], $this->listedIds($response->getBody()->getContents()));
    }

    #[Test]
    public function editors_also_see_soft_deleted_articles(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/linkrobins-wiki-articles', [
                'authenticatedAs' => 1, // admin, always an editor
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([1, 2, 3, 4], $this->listedIds($response->getBody()->getContents()));
    }

    #[Test]
    public function showing_a_soft_deleted_article_is_a_404_for_regular_users(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/linkrobins-wiki-articles/2', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function editors_can_show_a_soft_deleted_article(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/linkrobins-wiki-articles/2', [
                'authenticatedAs' => 1,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * These two exist because v1.6.0 shipped raw SQL that spelled the table
     * name out without the prefix, so every prefixed install 500ed on any
     * sorted or searched listing while the CI prefix jobs stayed green: nothing
     * in the suite went near either query.
     */
    #[Test]
    public function sorting_by_position_works_and_puts_unordered_articles_last(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/linkrobins-wiki-articles')->withQueryParams(['sort' => 'position,-lastEditedAt'])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $titles = array_map(
            fn (array $row) => $row['attributes']['title'],
            json_decode($response->getBody()->getContents(), true)['data']
        );

        // Positioned articles lead in their arranged order; the unpositioned
        // one falls to the end rather than to the top, which is what MySQL's
        // NULL-first default would have done.
        $this->assertSame(['Ordered first', 'Ordered second', 'Live article'], $titles);
    }

    #[Test]
    public function searching_matches_titles_and_bodies(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/linkrobins-wiki-articles')->withQueryParams(['filter' => ['q' => 'gribbet']])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $titles = array_map(
            fn (array $row) => $row['attributes']['title'],
            json_decode($response->getBody()->getContents(), true)['data']
        );

        $this->assertSame(['Ordered first'], $titles);
    }
}
