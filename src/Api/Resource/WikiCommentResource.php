<?php

namespace LinkRobins\Wiki\Api\Resource;

use Carbon\Carbon;
use Flarum\Api\Context as FlarumContext;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Database\Eloquent\Builder;
use LinkRobins\Wiki\Access\WikiAbilities;
use LinkRobins\Wiki\WikiArticle;
use LinkRobins\Wiki\WikiComment;
use Psr\Log\LoggerInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\ForbiddenException;

class WikiCommentResource extends AbstractDatabaseResource
{
    public function __construct(
        protected TranslatorInterface $translator,
        protected LoggerInterface $log,
    ) {
    }

    public function type(): string
    {
        return 'linkrobins-wiki-comments';
    }

    public function model(): string
    {
        return WikiComment::class;
    }

    /**
     * Visibility scope. Comments are public; soft-deleted comments stay visible
     * to editors (for moderation) and are hidden from everyone else by the
     * default SoftDeletes scope. Mirrors CommentSearcher.
     */
    public function scope(Builder $query, Context $context): void
    {
        if (WikiAbilities::isEditor($context->getActor())) {
            $query->withTrashed();
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make()
                ->defaultInclude(['user']),
            Endpoint\Index::make()
                ->defaultInclude(['user'])
                ->paginate(25, 100),
            Endpoint\Create::make()
                ->authenticated()
                ->can('comment'),
            Endpoint\Update::make()
                ->authenticated()
                ->can('update'),
            Endpoint\Delete::make()
                ->authenticated()
                ->can('delete'),
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
            Schema\Str::make('content')
                ->writable()
                ->set(function (WikiComment $comment, $value, FlarumContext $context) {
                    if (! is_string($value)) {
                        $comment->content = '';
                        return;
                    }
                    $comment->setContentAttribute($value, $context->getActor());
                }),

            Schema\Str::make('contentHtml')
                ->get(function (WikiComment $comment, FlarumContext $context) {
                    try {
                        return $comment->formatContent($context->request);
                    } catch (\Throwable $e) {
                        $this->log->warning('[linkrobins/wiki] formatContent failed', ['exception' => $e]);
                        return '';
                    }
                }),

            Schema\DateTime::make('createdAt')
                ->property('created_at'),
            Schema\DateTime::make('updatedAt')
                ->property('updated_at'),

            Schema\Boolean::make('canEdit')
                ->get(function (WikiComment $comment, FlarumContext $context) {
                    $actor = $context->getActor();
                    if ($actor->isGuest()) {
                        return false;
                    }
                    try {
                        return $actor->can('update', $comment);
                    } catch (\Throwable $e) {
                        return false;
                    }
                }),

            Schema\Boolean::make('canDelete')
                ->get(function (WikiComment $comment, FlarumContext $context) {
                    $actor = $context->getActor();
                    if ($actor->isGuest()) {
                        return false;
                    }
                    try {
                        return $actor->can('delete', $comment);
                    } catch (\Throwable $e) {
                        return false;
                    }
                }),

            // Soft-delete toggle. Available to the author or an editor; the
            // permanent DELETE is admin-only and requires soft-deletion first.
            Schema\Boolean::make('isDeleted')
                ->get(fn (WikiComment $comment) => $comment->deleted_at !== null)
                ->writable(function (WikiComment $comment, FlarumContext $context) {
                    if (! $context->updating()) {
                        return false;
                    }
                    return $context->getActor()->can('update', $comment);
                })
                ->set(function (WikiComment $comment, bool $value) {
                    if ($value && $comment->deleted_at === null) {
                        $comment->deleted_at = Carbon::now();
                    } elseif (! $value && $comment->deleted_at !== null) {
                        $comment->deleted_at = null;
                    }
                }),

            Schema\DateTime::make('deletedAt')
                ->property('deleted_at'),

            Schema\Relationship\ToOne::make('user')
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
        $actor = $context->getActor();

        $this->assertContent($model);

        // Force authorship to the acting user -- never trust relationships.user.
        $model->user_id = $actor->id;

        // Resolve the article from the relationship in the request body.
        $articleRel = data_get($context->body(), 'data.relationships.article.data.id');
        if (! is_numeric($articleRel)) {
            throw new BadRequestException($this->translator->trans('linkrobins-wiki.api.article_required'));
        }
        $article = WikiArticle::query()->find((int) $articleRel);
        if (! $article) {
            throw new BadRequestException($this->translator->trans('linkrobins-wiki.api.article_not_found'));
        }
        $model->article_id = $article->id;

        return $model;
    }

    public function updating(object $model, Context $context): ?object
    {
        // Block author / article tampering on update.
        foreach (['user_id', 'article_id'] as $immutable) {
            $original = $model->getOriginal($immutable);
            if ((int) $model->$immutable !== (int) $original) {
                $model->$immutable = $original;
            }
        }

        if ($model->isDirty('content')) {
            $this->assertContent($model);
        }

        return $model;
    }

    public function deleting(object $model, Context $context): void
    {
        if ($model->deleted_at === null) {
            throw new BadRequestException(
                $this->translator->trans('linkrobins-wiki.api.comment_soft_delete_first')
            );
        }

        $model->forceDelete();
    }

    protected function assertContent(object $model): void
    {
        $content = $model->getAttribute('content');
        if (! is_string($content) || trim(strip_tags($content)) === '') {
            throw new BadRequestException(
                $this->translator->trans('linkrobins-wiki.api.comment_required')
            );
        }
    }
}
