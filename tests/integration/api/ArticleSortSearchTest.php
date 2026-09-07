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

/**
 * Sorting and searching articles.
 *
 * These exist because v1.6.0 shipped raw SQL that spelled the article table out
 * without the table prefix, so every prefixed install returned a 500 on any
 * sorted or searched listing while the CI prefix jobs stayed green: nothing in
 * the suite went near either query. Both run under those jobs now.
 */
class ArticleSortSearchTest extends TestCase
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
                // Distinct edit times so the fallback sort is deterministic,
                // and one article whose title carries the search word while
                // another only mentions it in the body.
                ['id' => 1, 'user_id' => 2, 'title' => 'Second by hand', 'content' => '<t><p>Body.</p></t>', 'position' => 2, 'last_edited_at' => $now->copy()->subDays(2), 'created_at' => $now, 'updated_at' => $now],
                ['id' => 2, 'user_id' => 2, 'title' => 'First by hand', 'content' => '<t><p>Body mentioning gribbet.</p></t>', 'position' => 1, 'last_edited_at' => $now->copy()->subDays(3), 'created_at' => $now, 'updated_at' => $now],
                ['id' => 3, 'user_id' => 2, 'title' => 'Newest unordered', 'content' => '<t><p>Body.</p></t>', 'last_edited_at' => $now, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 4, 'user_id' => 2, 'title' => 'Gribbet handbook', 'content' => '<t><p>Body.</p></t>', 'last_edited_at' => $now->copy()->subDay(), 'created_at' => $now, 'updated_at' => $now],
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function titles(string $body): array
    {
        return array_map(
            fn (array $row) => $row['attributes']['title'],
            json_decode($body, true)['data']
        );
    }

    #[Test]
    public function sorting_by_position_puts_unordered_articles_last(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/linkrobins-wiki-articles')
                ->withQueryParams(['sort' => 'position,-lastEditedAt'])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            ['First by hand', 'Second by hand', 'Newest unordered', 'Gribbet handbook'],
            $this->titles($response->getBody()->getContents())
        );
    }

    #[Test]
    public function searching_matches_bodies_and_ranks_title_matches_first(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/linkrobins-wiki-articles')
                ->withQueryParams(['filter' => ['q' => 'gribbet']])
        );

        $this->assertEquals(200, $response->getStatusCode());
        // The title match leads, the body match follows.
        $this->assertSame(['Gribbet handbook', 'First by hand'], $this->titles($response->getBody()->getContents()));
    }

}
