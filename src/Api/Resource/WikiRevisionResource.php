<?php

namespace LinkRobins\Wiki\Api\Resource;

use Flarum\Api\Context as FlarumContext;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Illuminate\Database\Eloquent\Builder;
use LinkRobins\Wiki\Access\WikiAbilities;
use LinkRobins\Wiki\WikiRevision;
use Psr\Log\LoggerInterface;
use Tobyz\JsonApiServer\Context;

/**
 * Read-only revision history. Revisions are written by the article save hook
 * (see WikiServiceProvider), never through the API -- so there are no Create,
 * Update or Delete endpoints. They are removed only when their article is.
 */
class WikiRevisionResource extends AbstractDatabaseResource
{
    public function __construct(
        protected LoggerInterface $log,
    ) {
    }

    public function type(): string
    {
        return 'linkrobins-wiki-revisions';
    }

    public function model(): string
    {
        return WikiRevision::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        // Revisions of a soft-deleted article are visible only to editors,
        // matching ArticleSearcher / WikiArticleResource visibility.
        if (! WikiAbilities::isEditor($context->getActor())) {
            $query->whereHas('article');
        }
    }

    public function endpoints(): array
    {
        // No ->authenticated(): viewHistory is seeded to the guest group, so
        // guests may hold it. The gate only bites once an admin restricts it.
        return [
            Endpoint\Show::make()
                ->can('viewHistory')
                ->defaultInclude(['user']),
            Endpoint\Index::make()
                ->can('viewHistory')
                ->defaultInclude(['user'])
                ->paginate(25, 100),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt')->descendingAlias('newest')->ascendingAlias('oldest'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('title'),

            Schema\Str::make('content'),

            Schema\Str::make('contentHtml')
                ->get(function (WikiRevision $revision, FlarumContext $context) {
                    try {
                        return $revision->formatContent($context->request);
                    } catch (\Throwable $e) {
                        $this->log->warning('[linkrobins/wiki] formatContent failed', ['exception' => $e]);
                        return '';
                    }
                }),

            Schema\Str::make('summary')
                ->nullable(),

            Schema\DateTime::make('createdAt')
                ->property('created_at'),

            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),

            Schema\Relationship\ToOne::make('article')
                ->type('linkrobins-wiki-articles')
                ->includable(),
        ];
    }
}
