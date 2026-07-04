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
        $this->assertEquals([1], $this->listedIds($response->getBody()->getContents()));
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
        $this->assertEquals([1], $this->listedIds($response->getBody()->getContents()));
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
        $this->assertEquals([1, 2], $this->listedIds($response->getBody()->getContents()));
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
}
