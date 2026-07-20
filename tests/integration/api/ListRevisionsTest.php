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

class ListRevisionsTest extends TestCase
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
                ['id' => 1, 'user_id' => 2, 'title' => 'Article', 'content' => '<t><p>Body.</p></t>', 'last_edited_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ],
            'linkrobins_wiki_revisions' => [
                ['id' => 1, 'article_id' => 1, 'user_id' => 2, 'title' => 'Article', 'content' => 'Body.', 'created_at' => $now, 'updated_at' => $now],
                ['id' => 2, 'article_id' => 1, 'user_id' => 2, 'title' => 'Article', 'content' => 'Body, edited.', 'created_at' => $now, 'updated_at' => $now],
            ],
        ]);
    }

    /**
     * The install migration grants viewHistory to the guest group; dropping
     * that row simulates an admin restricting history in the permission grid.
     * Runs inside the per-test transaction, so it never leaks between tests.
     */
    private function revokeFromGuests(): void
    {
        $this->database()->table('group_permission')
            ->where('permission', 'linkrobins-wiki.viewHistory')
            ->where('group_id', Group::GUEST_ID)
            ->delete();
    }

    private function grantToMembers(): void
    {
        $this->database()->table('group_permission')->insert([
            'group_id' => Group::MEMBER_ID,
            'permission' => 'linkrobins-wiki.viewHistory',
        ]);
    }

    #[Test]
    public function guests_can_list_revisions_by_default(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/linkrobins-wiki-revisions')
                ->withQueryParams(['filter' => ['articleId' => '1']])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, json_decode($response->getBody()->getContents(), true)['data']);
    }

    #[Test]
    public function guests_cannot_list_revisions_when_restricted(): void
    {
        $this->app();
        $this->revokeFromGuests();

        $response = $this->send(
            $this->request('GET', '/api/linkrobins-wiki-revisions')
                ->withQueryParams(['filter' => ['articleId' => '1']])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function guests_cannot_show_a_revision_when_restricted(): void
    {
        $this->app();
        $this->revokeFromGuests();

        $response = $this->send(
            $this->request('GET', '/api/linkrobins-wiki-revisions/1')
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function members_can_list_revisions_when_granted_to_members(): void
    {
        $this->app();
        $this->revokeFromGuests();
        $this->grantToMembers();

        $response = $this->send(
            $this->request('GET', '/api/linkrobins-wiki-revisions', [
                'authenticatedAs' => 2,
            ])->withQueryParams(['filter' => ['articleId' => '1']])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, json_decode($response->getBody()->getContents(), true)['data']);
    }
}
