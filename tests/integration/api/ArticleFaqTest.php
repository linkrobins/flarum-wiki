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
use LinkRobins\Wiki\Faq;
use PHPUnit\Framework\Attributes\Test;

class ArticleFaqTest extends TestCase
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
            ],
        ]);
    }

    private function patchFaq(array $faq): \Psr\Http\Message\ResponseInterface
    {
        return $this->send(
            $this->request('PATCH', '/api/linkrobins-wiki-articles/1', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['type' => 'linkrobins-wiki-articles', 'id' => '1', 'attributes' => ['faq' => $faq]]],
            ])
        );
    }

    #[Test]
    public function faq_entries_round_trip_through_the_formatter(): void
    {
        // Markdown rendering itself comes from flarum/markdown, which isn't
        // in the test install; what's asserted here is the pipeline: source
        // in, parsed representation stored, source + rendered HTML out.
        $response = $this->patchFaq([
            ['question' => 'How do I install it?', 'answer' => 'Run composer require and relax.'],
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $faq = json_decode($response->getBody()->getContents(), true)['data']['attributes']['faq'];

        $this->assertCount(1, $faq);
        $this->assertEquals('How do I install it?', $faq[0]['question']);
        // The editor gets the source back...
        $this->assertEquals('Run composer require and relax.', $faq[0]['answer']);
        // ...while readers get the render, not the raw source.
        $this->assertStringContainsString('composer require', $faq[0]['answerHtml']);

        // Stored as the formatter's parsed representation (an s9e XML doc),
        // not raw input.
        $stored = $this->database()->table('linkrobins_wiki_articles')->where('id', 1)->value('faq');
        $this->assertNotNull($stored);
        $this->assertStringStartsWith('<', json_decode($stored, true)[0]['answer']);
    }

    #[Test]
    public function script_tags_in_answers_are_neutralized(): void
    {
        $response = $this->patchFaq([
            ['question' => 'Is this safe?', 'answer' => '<script>alert(1)</script> hopefully'],
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $faq = json_decode($response->getBody()->getContents(), true)['data']['attributes']['faq'];

        $this->assertStringNotContainsString('<script', $faq[0]['answerHtml']);
    }

    #[Test]
    public function malformed_entries_are_dropped(): void
    {
        $response = $this->patchFaq([
            ['question' => 'Kept', 'answer' => 'Yes.'],
            ['question' => '', 'answer' => 'No question.'],
            ['question' => 'No answer', 'answer' => ''],
            ['bogus' => true],
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $faq = json_decode($response->getBody()->getContents(), true)['data']['attributes']['faq'];

        $this->assertCount(1, $faq);
        $this->assertEquals('Kept', $faq[0]['question']);
    }

    #[Test]
    public function an_oversized_faq_is_rejected(): void
    {
        $response = $this->patchFaq(
            array_fill(0, Faq::MAX_ENTRIES + 1, ['question' => 'Q?', 'answer' => 'A.'])
        );

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertNull($this->database()->table('linkrobins_wiki_articles')->where('id', 1)->value('faq'));
    }

    #[Test]
    public function an_empty_list_clears_the_faq(): void
    {
        $this->patchFaq([['question' => 'Q?', 'answer' => 'A.']]);
        $response = $this->patchFaq([]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], json_decode($response->getBody()->getContents(), true)['data']['attributes']['faq']);
        $this->assertNull($this->database()->table('linkrobins_wiki_articles')->where('id', 1)->value('faq'));
    }

    #[Test]
    public function faq_changes_write_no_revision_and_keep_last_edited(): void
    {
        $before = $this->database()->table('linkrobins_wiki_articles')->where('id', 1)->first();

        $response = $this->patchFaq([['question' => 'Q?', 'answer' => 'A.']]);
        $this->assertEquals(200, $response->getStatusCode());

        // The history covers title + body only; a FAQ edit is neither.
        $this->assertEquals(0, $this->database()->table('linkrobins_wiki_revisions')->where('article_id', 1)->count());

        $after = $this->database()->table('linkrobins_wiki_articles')->where('id', 1)->first();
        $this->assertEquals($before->last_edited_at, $after->last_edited_at);
    }

    #[Test]
    public function guests_see_the_faq_on_show(): void
    {
        $this->patchFaq([['question' => 'Visible to guests?', 'answer' => 'It is.']]);

        $response = $this->send(
            $this->request('GET', '/api/linkrobins-wiki-articles/existing-guide')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $faq = json_decode($response->getBody()->getContents(), true)['data']['attributes']['faq'];
        $this->assertEquals('Visible to guests?', $faq[0]['question']);
    }
}
