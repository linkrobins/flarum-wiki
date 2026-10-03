<?php

/*
 * This file is part of linkrobins/wiki.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace LinkRobins\Wiki\Tests\integration\sitemap;

use Carbon\Carbon;
use Flarum\Http\UrlGenerator;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\Sitemap\Resources\Resource;
use LinkRobins\Wiki\Sitemap\ArticleSitemapResource;
use LinkRobins\Wiki\WikiArticle;
use PHPUnit\Framework\Attributes\Test;

/**
 * The sitemap lists exactly what a guest can open: never a deleted article
 * or a draft (they 404 for guests), and every published one, at its slug URL.
 */
class ArticleSitemapResourceTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('linkrobins-wiki');

        $now = Carbon::now();
        $edited = Carbon::now()->subDays(40);

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'linkrobins_wiki_articles' => [
                ['id' => 1, 'user_id' => 2, 'title' => 'Installing the widget', 'slug' => 'installing-the-widget', 'content' => '<t><p>Body.</p></t>', 'last_edited_at' => $edited, 'created_at' => $edited, 'updated_at' => $edited],
                ['id' => 2, 'user_id' => 2, 'title' => 'No slug yet', 'slug' => null, 'content' => '<t><p>Body.</p></t>', 'last_edited_at' => $now, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 3, 'user_id' => 2, 'title' => 'Retired page', 'slug' => 'retired-page', 'content' => '<t><p>Body.</p></t>', 'last_edited_at' => $now, 'deleted_at' => $now, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 4, 'user_id' => 2, 'title' => 'Unfinished draft', 'slug' => 'unfinished-draft', 'content' => '<t><p>Body.</p></t>', 'is_draft' => true, 'last_edited_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ],
        ]);
    }

    private function resource(): ArticleSitemapResource
    {
        Resource::setUrlGenerator($this->app()->getContainer()->make(UrlGenerator::class));

        return new ArticleSitemapResource();
    }

    #[Test]
    public function it_lists_only_what_a_guest_can_open(): void
    {
        $ids = $this->resource()->query()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        $this->assertEquals([1, 2], $ids);
    }

    #[Test]
    public function articles_are_listed_at_their_slug_or_their_id(): void
    {
        $resource = $this->resource();

        $this->assertEquals('http://localhost/wiki/installing-the-widget', $resource->url(WikiArticle::query()->find(1)));
        $this->assertEquals('http://localhost/wiki/2', $resource->url(WikiArticle::query()->find(2)));
    }

    #[Test]
    public function last_modified_follows_the_last_edit_and_old_articles_are_checked_monthly(): void
    {
        $resource = $this->resource();
        $old = WikiArticle::query()->find(1);

        $this->assertEquals($old->last_edited_at->toDateTimeString(), $resource->lastModifiedAt($old)->toDateTimeString());
        $this->assertEquals('monthly', $resource->dynamicFrequency($old));
        $this->assertEquals('daily', $resource->dynamicFrequency(WikiArticle::query()->find(2)));
    }
}
