<?php

namespace LinkRobins\Wiki\Api\Resource;

use Carbon\Carbon;
use Flarum\Api\Context as FlarumContext;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use LinkRobins\Wiki\Access\WikiAbilities;
use LinkRobins\Wiki\Event\ArticleReported;
use LinkRobins\Wiki\WikiArticle;
use LinkRobins\Wiki\WikiReport;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;

/**
 * Reader reports about an article.
 *
 * Anyone who may report can create one; only editors can read or resolve them,
 * which is why the scope returns nothing at all to everybody else rather than
 * filtering: a reporter has no need to read the queue, not even their own row.
 */
class WikiReportResource extends AbstractDatabaseResource
{
    public function __construct(
        protected TranslatorInterface $translator,
        protected Dispatcher $events,
    ) {
    }

    public function type(): string
    {
        return 'linkrobins-wiki-reports';
    }

    public function model(): string
    {
        return WikiReport::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        /** @var \Illuminate\Database\Eloquent\Builder<WikiReport> $query */
        if (! WikiAbilities::isEditor($context->getActor())) {
            $query->whereRaw('1 = 0');
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->defaultInclude(['user', 'article'])
                ->paginate(25, 100),
            Endpoint\Create::make()
                ->authenticated()
                ->can('reportArticle'),
            // Resolving is the only update, and it is an editor's to make.
            Endpoint\Update::make()
                ->authenticated()
                ->can('editArticles'),
            Endpoint\Delete::make()
                ->authenticated()
                ->can('editArticles'),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt')->ascendingAlias('oldest')->descendingAlias('newest'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('reason')
                ->writable()
                ->set(function (WikiReport $report, $value) {
                    $report->reason = in_array($value, WikiReport::REASONS, true) ? $value : 'other';
                }),

            // The reporter's own words. Stored as plain text and rendered as
            // plain text: a report is read by an editor in a list, and nothing
            // good comes of letting a stranger put markup there.
            Schema\Str::make('detail')
                ->nullable()
                ->writable()
                ->set(function (WikiReport $report, $value) {
                    $value = is_string($value) ? trim($value) : '';
                    $report->detail = $value === '' ? null : mb_substr($value, 0, 1000);
                }),

            Schema\DateTime::make('createdAt')
                ->property('created_at'),

            Schema\DateTime::make('resolvedAt')
                ->property('resolved_at'),

            Schema\Boolean::make('isResolved')
                ->get(fn (WikiReport $report) => $report->resolved_at !== null)
                ->writable(fn (WikiReport $report, FlarumContext $context) => $context->updating())
                ->set(function (WikiReport $report, bool $value, FlarumContext $context) {
                    if ($value && $report->resolved_at === null) {
                        $report->resolved_at = Carbon::now();
                        $report->resolved_by_user_id = $context->getActor()->id;
                    } elseif (! $value && $report->resolved_at !== null) {
                        $report->resolved_at = null;
                        $report->resolved_by_user_id = null;
                    }
                }),

            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),

            Schema\Relationship\ToOne::make('resolvedBy')
                ->type('users')
                ->includable(),

            Schema\Relationship\ToOne::make('article')
                ->type('linkrobins-wiki-articles')
                ->includable()
                ->writable(),
        ];
    }

    public function creating(object $model, Context $context): ?object
    {
        /** @var WikiReport $model */
        $actor = $context->getActor();

        $model->user_id = $actor->id;
        $model->resolved_at = null;
        $model->resolved_by_user_id = null;

        $articleRel = data_get($context->body(), 'data.relationships.article.data.id');
        if (! is_numeric($articleRel)) {
            throw new BadRequestException($this->translator->trans('linkrobins-wiki.api.article_required'));
        }

        $article = WikiArticle::query()->find((int) $articleRel);
        if (! $article) {
            throw new BadRequestException($this->translator->trans('linkrobins-wiki.api.article_not_found'));
        }
        $model->article_id = $article->id;

        if (! in_array($model->reason, WikiReport::REASONS, true)) {
            $model->reason = 'other';
        }

        // One open report per person per article. A reader who reports twice
        // means it twice, but a queue with the same row in it four times is
        // harder to work through, not easier.
        $existing = WikiReport::query()
            ->where('article_id', $model->article_id)
            ->where('user_id', $actor->id)
            ->whereNull('resolved_at')
            ->first();

        if ($existing) {
            throw new BadRequestException($this->translator->trans('linkrobins-wiki.api.report_already_open'));
        }

        return $model;
    }

    public function created(object $model, Context $context): ?object
    {
        /** @var WikiReport $model */
        $this->events->dispatch(new ArticleReported($model, $context->getActor()));

        return $model;
    }

    public function updating(object $model, Context $context): ?object
    {
        // Only the resolved flag may move. Everything else is what the reporter
        // said, and an editor rewriting that would make the queue worthless.
        foreach (['article_id', 'user_id', 'reason', 'detail'] as $frozen) {
            $original = $model->getOriginal($frozen);
            if ($model->getAttribute($frozen) !== $original) {
                $model->setAttribute($frozen, $original);
            }
        }

        return $model;
    }
}
