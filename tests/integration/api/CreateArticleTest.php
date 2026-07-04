<?php

/*
 * This file is part of linkrobins/wiki.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace LinkRobins\Wiki\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CreateArticleTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('linkrobins-wiki');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(), // id 2
            ],
            'group_permission' => [
                ['permission' => 'linkrobins-wiki.createArticle', 'group_id' => Group::MEMBER_ID],
            ],
            'linkrobins_wiki_categories' => [
                ['id' => 1, 'name' => 'Guides', 'slug' => 'guides', 'position' => 0, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function articleBody(array $attributes, bool $withCategory = false): array
    {
        $data = [
            'type' => 'linkrobins-wiki-articles',
            'attributes' => $attributes,
        ];

        if ($withCategory) {
            $data['relationships'] = [
                'category' => ['data' => ['type' => 'linkrobins-wiki-categories', 'id' => '1']],
            ];
        }

        return ['data' => $data];
    }

    #[Test]
    public function guests_cannot_create_an_article(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/linkrobins-wiki-articles', [
                'json' => $this->articleBody(['title' => 'Setup guide', 'content' => 'Step one.']),
            ])
        );

        // An unauthenticated write is rejected before it can reach the auth
        // gate (Flarum's CSRF guard returns 400 for a tokenless session POST);
        // either way the guarantee that matters is that nothing is persisted.
        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
        $this->assertEquals(0, $this->database()->table('linkrobins_wiki_articles')->count());
    }

    #[Test]
    public function users_without_the_permission_cannot_create(): void
    {
        $this->database()->table('group_permission')
            ->where('permission', 'linkrobins-wiki.createArticle')
            ->delete();

        $response = $this->send(
            $this->request('POST', '/api/linkrobins-wiki-articles', [
                'authenticatedAs' => 2,
                'json' => $this->articleBody(['title' => 'Setup guide', 'content' => 'Step one.']),
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals(0, $this->database()->table('linkrobins_wiki_articles')->count());
    }

    #[Test]
    public function a_blank_title_is_rejected(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/linkrobins-wiki-articles', [
                'authenticatedAs' => 2,
                'json' => $this->articleBody(['title' => '   ', 'content' => 'Step one.']),
            ])
        );

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(0, $this->database()->table('linkrobins_wiki_articles')->count());
    }

    #[Test]
    public function empty_content_is_rejected(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/linkrobins-wiki-articles', [
                'authenticatedAs' => 2,
                'json' => $this->articleBody(['title' => 'Setup guide', 'content' => '   ']),
            ])
        );

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(0, $this->database()->table('linkrobins_wiki_articles')->count());
    }

    #[Test]
    public function creating_an_article_writes_the_initial_revision(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/linkrobins-wiki-articles', [
                'authenticatedAs' => 2,
                'json' => $this->articleBody([
                    'title' => 'Setup guide',
                    'content' => 'Step one: plug it in.',
                ], withCategory: true),
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $articleId = json_decode($response->getBody()->getContents(), true)['data']['id'];

        $article = $this->database()->table('linkrobins_wiki_articles')->where('id', $articleId)->first();
        $this->assertNotNull($article);
        // Authorship is forced to the acting user by the resource, never
        // taken from the request body.
        $this->assertEquals(2, $article->user_id);
        $this->assertEquals(1, $article->category_id);

        // The created hook must have snapshotted the article into the
        // revision history (revision 1 = the article as first written).
        $revision = $this->database()->table('linkrobins_wiki_revisions')
            ->where('article_id', $articleId)
            ->first();

        $this->assertNotNull($revision, 'Expected an initial revision to be written on create.');
        $this->assertEquals('Setup guide', $revision->title);
        $this->assertEquals(2, $revision->user_id);
    }
}
