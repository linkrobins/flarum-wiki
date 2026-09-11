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

/**
 * Reader reports about an article.
 *
 * The queue is the part that has to hold: it is written by anyone who can
 * report and read only by editors, so the tests that matter are the ones about
 * who may write, who may read, and what an editor may change once a report is
 * in (the answer being: whether it is resolved, and nothing else).
 */
class ReportArticleTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('linkrobins-wiki');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(), // id 2, a member
            ],
            'group_permission' => [
                ['permission' => 'linkrobins-wiki.reportArticle', 'group_id' => Group::MEMBER_ID],
            ],
            'linkrobins_wiki_categories' => [
                ['id' => 1, 'name' => 'Guides', 'slug' => 'guides', 'position' => 0, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ],
            'linkrobins_wiki_articles' => [
                [
                    'id' => 1,
                    'category_id' => 1,
                    'user_id' => 1,
                    'title' => 'Setup guide',
                    'content' => '<r><p>Step one.</p></r>',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportBody(array $attributes, string $articleId = '1'): array
    {
        return [
            'data' => [
                'type' => 'linkrobins-wiki-reports',
                'attributes' => $attributes,
                'relationships' => [
                    'article' => ['data' => ['type' => 'linkrobins-wiki-articles', 'id' => $articleId]],
                ],
            ],
        ];
    }

    private function report(array $attributes, int $actor = 2, string $articleId = '1')
    {
        return $this->send(
            $this->request('POST', '/api/linkrobins-wiki-reports', [
                'authenticatedAs' => $actor,
                'json' => $this->reportBody($attributes, $articleId),
            ])
        );
    }

    #[Test]
    public function a_member_can_report_an_article(): void
    {
        $response = $this->report(['reason' => 'outdated', 'detail' => 'The screenshots are old.']);

        $this->assertEquals(201, $response->getStatusCode());

        $row = $this->database()->table('linkrobins_wiki_reports')->first();

        $this->assertNotNull($row);
        $this->assertEquals(1, $row->article_id);
        $this->assertEquals(2, $row->user_id);
        $this->assertEquals('outdated', $row->reason);
        $this->assertEquals('The screenshots are old.', $row->detail);
        // An open report is one with no resolution on it yet.
        $this->assertNull($row->resolved_at);
    }

    #[Test]
    public function guests_cannot_report(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/linkrobins-wiki-reports', [
                'json' => $this->reportBody(['reason' => 'other']),
            ])
        );

        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
        $this->assertEquals(0, $this->database()->table('linkrobins_wiki_reports')->count());
    }

    #[Test]
    public function users_without_the_permission_cannot_report(): void
    {
        $this->database()->table('group_permission')
            ->where('permission', 'linkrobins-wiki.reportArticle')
            ->delete();

        $response = $this->report(['reason' => 'other']);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals(0, $this->database()->table('linkrobins_wiki_reports')->count());
    }

    #[Test]
    public function a_second_open_report_from_the_same_person_is_refused(): void
    {
        $this->assertEquals(201, $this->report(['reason' => 'outdated'])->getStatusCode());

        $response = $this->report(['reason' => 'inaccurate']);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(1, $this->database()->table('linkrobins_wiki_reports')->count());
    }

    #[Test]
    public function the_same_person_may_report_again_once_the_first_is_resolved(): void
    {
        $this->report(['reason' => 'outdated']);

        $this->database()->table('linkrobins_wiki_reports')
            ->update(['resolved_at' => Carbon::now(), 'resolved_by_user_id' => 1]);

        // A resolved report is history, not a standing objection, so the same
        // reader raising the article again is a new report rather than a
        // duplicate.
        $this->assertEquals(201, $this->report(['reason' => 'inaccurate'])->getStatusCode());
        $this->assertEquals(2, $this->database()->table('linkrobins_wiki_reports')->count());
    }

    #[Test]
    public function a_reason_outside_the_list_is_stored_as_other(): void
    {
        $this->assertEquals(201, $this->report(['reason' => 'whatever-i-like'])->getStatusCode());

        $this->assertEquals('other', $this->database()->table('linkrobins_wiki_reports')->first()->reason);
    }

    #[Test]
    public function a_report_against_a_missing_article_is_refused(): void
    {
        $response = $this->report(['reason' => 'other'], 2, '999');

        // 404 rather than 400: the relationship is resolved before the resource
        // hook runs, so "no such article" is answered by the api layer itself.
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals(0, $this->database()->table('linkrobins_wiki_reports')->count());
    }

    #[Test]
    public function a_report_naming_no_article_at_all_is_refused(): void
    {
        // The case the resource hook owns, as opposed to the one above: a body
        // with no article relationship never reaches the api layer's lookup.
        $response = $this->send(
            $this->request('POST', '/api/linkrobins-wiki-reports', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['type' => 'linkrobins-wiki-reports', 'attributes' => ['reason' => 'other']]],
            ])
        );

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(0, $this->database()->table('linkrobins_wiki_reports')->count());
    }

    #[Test]
    public function the_queue_is_invisible_to_the_reporter(): void
    {
        $this->report(['reason' => 'outdated']);

        $response = $this->send($this->request('GET', '/api/linkrobins-wiki-reports', ['authenticatedAs' => 2]));

        $this->assertEquals(200, $response->getStatusCode());
        // Not an error, an empty set: a reporter has no business reading the
        // queue, not even the row they wrote.
        $this->assertEquals([], json_decode($response->getBody()->getContents(), true)['data']);
    }

    #[Test]
    public function an_editor_sees_the_queue(): void
    {
        $this->report(['reason' => 'outdated']);

        $response = $this->send($this->request('GET', '/api/linkrobins-wiki-reports', ['authenticatedAs' => 1]));

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true)['data'];

        $this->assertCount(1, $data);
        $this->assertEquals('outdated', $data[0]['attributes']['reason']);
        $this->assertFalse($data[0]['attributes']['isResolved']);
    }

    #[Test]
    public function an_editor_resolves_a_report(): void
    {
        $this->report(['reason' => 'outdated']);
        $id = $this->database()->table('linkrobins_wiki_reports')->first()->id;

        $response = $this->send(
            $this->request('PATCH', '/api/linkrobins-wiki-reports/'.$id, [
                'authenticatedAs' => 1,
                'json' => ['data' => ['type' => 'linkrobins-wiki-reports', 'id' => (string) $id, 'attributes' => ['isResolved' => true]]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $row = $this->database()->table('linkrobins_wiki_reports')->first();

        $this->assertNotNull($row->resolved_at);
        // Who cleared it is the half of the record an audit trail cannot
        // reconstruct later.
        $this->assertEquals(1, $row->resolved_by_user_id);
    }

    #[Test]
    public function resolving_can_be_undone(): void
    {
        $this->report(['reason' => 'outdated']);
        $id = $this->database()->table('linkrobins_wiki_reports')->first()->id;

        foreach ([true, false] as $resolved) {
            $this->send(
                $this->request('PATCH', '/api/linkrobins-wiki-reports/'.$id, [
                    'authenticatedAs' => 1,
                    'json' => ['data' => ['type' => 'linkrobins-wiki-reports', 'id' => (string) $id, 'attributes' => ['isResolved' => $resolved]]],
                ])
            );
        }

        $row = $this->database()->table('linkrobins_wiki_reports')->first();

        $this->assertNull($row->resolved_at);
        $this->assertNull($row->resolved_by_user_id);
    }

    #[Test]
    public function an_editor_cannot_rewrite_what_the_reporter_said(): void
    {
        $this->report(['reason' => 'outdated', 'detail' => 'The screenshots are old.']);
        $id = $this->database()->table('linkrobins_wiki_reports')->first()->id;

        $this->send(
            $this->request('PATCH', '/api/linkrobins-wiki-reports/'.$id, [
                'authenticatedAs' => 1,
                'json' => ['data' => [
                    'type' => 'linkrobins-wiki-reports',
                    'id' => (string) $id,
                    'attributes' => ['reason' => 'duplicate', 'detail' => 'Something I made up.'],
                ]],
            ])
        );

        $row = $this->database()->table('linkrobins_wiki_reports')->first();

        // A queue an editor can edit is a queue worth nothing.
        $this->assertEquals('outdated', $row->reason);
        $this->assertEquals('The screenshots are old.', $row->detail);
    }

    #[Test]
    public function a_member_cannot_resolve_a_report(): void
    {
        $this->report(['reason' => 'outdated']);
        $id = $this->database()->table('linkrobins_wiki_reports')->first()->id;

        $response = $this->send(
            $this->request('PATCH', '/api/linkrobins-wiki-reports/'.$id, [
                'authenticatedAs' => 2,
                'json' => ['data' => ['type' => 'linkrobins-wiki-reports', 'id' => (string) $id, 'attributes' => ['isResolved' => true]]],
            ])
        );

        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
        $this->assertNull($this->database()->table('linkrobins_wiki_reports')->first()->resolved_at);
    }

    #[Test]
    public function deleting_the_article_takes_its_reports_with_it(): void
    {
        $this->report(['reason' => 'outdated']);

        $this->database()->table('linkrobins_wiki_articles')->where('id', 1)->delete();

        // The foreign key cascades, so a queue never shows rows pointing at an
        // article that is gone.
        $this->assertEquals(0, $this->database()->table('linkrobins_wiki_reports')->count());
    }
}
