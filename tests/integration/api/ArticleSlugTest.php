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

class ArticleSlugTest extends TestCase
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
            'group_permission' => [
                ['permission' => 'linkrobins-wiki.createArticle', 'group_id' => Group::MEMBER_ID],
            ],
            'linkrobins_wiki_articles' => [
                ['id' => 1, 'user_id' => 2, 'title' => 'Existing guide', 'slug' => 'existing-guide', 'content' => '<t><p>Body.</p></t>', 'last_edited_at' => $now, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 2, 'user_id' => 2, 'title' => 'Hidden guide', 'slug' => 'hidden-guide', 'content' => '<t><p>Body.</p></t>', 'last_edited_at' => $now, 'deleted_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function createBody(array $attributes): array
    {
        return ['data' => ['type' => 'linkrobins-wiki-articles', 'attributes' => $attributes]];
    }

    private function create(array $attributes): \Psr\Http\Message\ResponseInterface
    {
        return $this->send(
            $this->request('POST', '/api/linkrobins-wiki-articles', [
                'authenticatedAs' => 2,
                'json' => $this->createBody($attributes),
            ])
        );
    }

    #[Test]
    public function a_slug_is_generated_from_the_title(): void
    {
        $response = $this->create(['title' => 'Getting Started', 'content' => 'Plug it in.']);

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true)['data'];

        $this->assertEquals('getting-started', $data['attributes']['slug']);
        $this->assertEquals(
            'getting-started',
            $this->database()->table('linkrobins_wiki_articles')->where('id', $data['id'])->value('slug')
        );
    }

    #[Test]
    public function a_duplicate_title_gets_an_id_suffixed_slug(): void
    {
        $response = $this->create(['title' => 'Existing guide', 'content' => 'Same title, new article.']);

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true)['data'];

        // `existing-guide` belongs to article 1; the new one falls back to the
        // id-suffixed form, which is unique by construction.
        $this->assertEquals('existing-guide-'.$data['id'], $data['attributes']['slug']);
    }

    #[Test]
    public function an_all_digit_title_cannot_shadow_id_urls(): void
    {
        $response = $this->create(['title' => '2026', 'content' => 'Year notes.']);

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true)['data'];

        $this->assertEquals('2026-'.$data['id'], $data['attributes']['slug']);
    }

    #[Test]
    public function a_custom_slug_is_normalized_and_used(): void
    {
        $response = $this->create(['title' => 'Router setup', 'slug' => '  My Custom Path!  ', 'content' => 'Steps.']);

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true)['data'];

        $this->assertEquals('my-custom-path', $data['attributes']['slug']);
    }

    #[Test]
    public function a_reserved_custom_slug_is_rejected(): void
    {
        foreach (['123', 'new'] as $reserved) {
            $response = $this->create(['title' => 'Router setup', 'slug' => $reserved, 'content' => 'Steps.']);

            $this->assertEquals(400, $response->getStatusCode(), "Slug `$reserved` should be rejected.");
        }

        $this->assertEquals(2, $this->database()->table('linkrobins_wiki_articles')->count());
    }

    #[Test]
    public function a_taken_custom_slug_is_rejected(): void
    {
        // `hidden-guide` belongs to a soft-deleted article; its URL stays
        // reserved so a restore doesn't collide.
        foreach (['existing-guide', 'hidden-guide'] as $taken) {
            $response = $this->create(['title' => 'Router setup', 'slug' => $taken, 'content' => 'Steps.']);

            $this->assertEquals(400, $response->getStatusCode(), "Slug `$taken` should be rejected.");
        }

        $this->assertEquals(2, $this->database()->table('linkrobins_wiki_articles')->count());
    }

    #[Test]
    public function an_article_resolves_by_slug_and_by_id(): void
    {
        foreach (['existing-guide', '1'] as $segment) {
            $response = $this->send(
                $this->request('GET', '/api/linkrobins-wiki-articles/'.$segment)
            );

            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('1', json_decode($response->getBody()->getContents(), true)['data']['id']);
        }
    }

    #[Test]
    public function an_unknown_slug_404s(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/linkrobins-wiki-articles/no-such-article')
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function a_soft_deleted_articles_slug_404s_for_guests(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/linkrobins-wiki-articles/hidden-guide')
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function the_author_can_change_the_slug(): void
    {
        $response = $this->send(
            $this->request('PATCH', '/api/linkrobins-wiki-articles/1', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['type' => 'linkrobins-wiki-articles', 'id' => '1', 'attributes' => ['slug' => 'renamed-guide']]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            'renamed-guide',
            $this->database()->table('linkrobins_wiki_articles')->where('id', 1)->value('slug')
        );
    }

    #[Test]
    public function a_blank_slug_on_update_keeps_the_existing_one(): void
    {
        $response = $this->send(
            $this->request('PATCH', '/api/linkrobins-wiki-articles/1', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['type' => 'linkrobins-wiki-articles', 'id' => '1', 'attributes' => ['slug' => '   ']]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            'existing-guide',
            $this->database()->table('linkrobins_wiki_articles')->where('id', 1)->value('slug')
        );
    }
}
