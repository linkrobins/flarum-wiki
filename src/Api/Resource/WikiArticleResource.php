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
use LinkRobins\Wiki\Faq;
use LinkRobins\Wiki\Slug;
use LinkRobins\Wiki\WikiArticle;
use LinkRobins\Wiki\WikiCategory;
use Psr\Log\LoggerInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;

class WikiArticleResource extends AbstractDatabaseResource
{
    public function __construct(
        protected TranslatorInterface $translator,
        protected LoggerInterface $log,
    ) {
    }

    public function type(): string
    {
        return 'linkrobins-wiki-articles';
    }

    public function model(): string
    {
        return WikiArticle::class;
    }

    /**
     * Visibility scope (Show endpoints). Articles are public; the only rule is
     * that soft-deleted articles stay visible to editors (so they can restore
     * or force-delete) and are hidden from everyone else by the default
     * SoftDeletes scope. Mirrored in ArticleSearcher for Index endpoints.
     */
    public function scope(Builder $query, Context $context): void
    {
        /** @var \Illuminate\Database\Eloquent\Builder<WikiArticle> $query */
        $query->withCount('revisions as revision_count');

        if (WikiAbilities::isEditor($context->getActor())) {
            $query->withTrashed();
        }
    }

    /**
     * Resolve a URL segment to an article: numeric means id, anything else is
     * a slug. Slugs can never be purely numeric (Slug::isReserved), so the two
     * namespaces can't shadow each other. Both paths go through the
     * visibility-scoped query, so a slug for a soft-deleted article 404s for
     * non-editors just like its id does. Mirrors WikiCategoryResource::find.
     */
    public function find(string $id, Context $context): ?object
    {
        if (preg_match('/^\d+$/', $id)) {
            return $this->query($context)->find($id);
        }

        return $this->query($context)->where('slug', $id)->first();
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make()
                ->defaultInclude(['user', 'category', 'lastEditedBy']),
            Endpoint\Index::make()
                ->defaultInclude(['user', 'category'])
                ->paginate(25, 100),
            Endpoint\Create::make()
                ->authenticated()
                ->can('createArticle'),
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
            SortColumn::make('lastEditedAt')->descendingAlias('latest'),
            SortColumn::make('createdAt')->descendingAlias('newest')->ascendingAlias('oldest'),
            SortColumn::make('title'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('title')
                ->writable()
                ->maxLength(250)
                ->set(function (WikiArticle $article, $value) {
                    $article->title = is_string($value) ? trim($value) : '';
                }),

            Schema\Str::make('slug')
                ->nullable()
                ->writable()
                ->maxLength(191)
                ->set(function (WikiArticle $article, $value) {
                    $slug = Slug::normalize(is_string($value) ? $value : null);

                    if ($slug === null) {
                        // Blank means "no custom slug": on create the slug is
                        // generated from the title after save; on update the
                        // existing slug is kept so URLs don't silently break.
                        $article->slug = $article->exists ? $article->getOriginal('slug') : null;

                        return;
                    }

                    $article->slug = $slug;
                }),

            Schema\Str::make('content')
                ->writable()
                ->set(function (WikiArticle $article, $value, FlarumContext $context) {
                    if (! is_string($value)) {
                        $article->content = '';
                        return;
                    }
                    // Route through HasFormattedContent so the formatter parses
                    // the source into the trait's representation in `content`.
                    $article->setContentAttribute($value, $context->getActor());
                }),

            Schema\Str::make('contentHtml')
                ->get(function (WikiArticle $article, FlarumContext $context) {
                    try {
                        return $article->formatContent($context->request);
                    } catch (\Throwable $e) {
                        $this->log->warning('[linkrobins/wiki] formatContent failed', ['exception' => $e]);
                        return '';
                    }
                }),

            // The article's FAQ, an optional accordion under the body. Reads
            // return {question, answer, answerHtml} (source for the editor,
            // rendered HTML for display); writes accept {question, answer}
            // with Markdown answers, parsed through the same formatter
            // pipeline as the body. An empty list clears the FAQ. Changes
            // don't touch the revision history, which covers title + body.
            Schema\Arr::make('faq')
                ->writable()
                ->get(function (WikiArticle $article, FlarumContext $context) {
                    try {
                        $formatter = WikiArticle::getFormatter();
                        $out = [];

                        foreach (Faq::fromStored($article->faq) as $entry) {
                            try {
                                $out[] = [
                                    'question' => $entry['question'],
                                    'answer' => (string) $formatter->unparse($entry['answer'], $article),
                                    'answerHtml' => $formatter->render($entry['answer'], $article, $context->request),
                                ];
                            } catch (\Throwable $e) {
                                // Skip the broken entry, keep the rest.
                            }
                        }

                        return $out;
                    } catch (\Throwable $e) {
                        $this->log->warning('[linkrobins/wiki] faq serialization failed', ['exception' => $e]);

                        return [];
                    }
                })
                ->set(function (WikiArticle $article, $value, FlarumContext $context) {
                    if (! is_array($value)) {
                        $article->faq = null;

                        return;
                    }

                    $entries = Faq::normalize($value);

                    if (count($entries) > Faq::MAX_ENTRIES) {
                        throw new BadRequestException(
                            $this->translator->trans('linkrobins-wiki.api.faq_too_many', ['max' => Faq::MAX_ENTRIES])
                        );
                    }

                    $formatter = WikiArticle::getFormatter();
                    $actor = $context->getActor();
                    $stored = [];

                    foreach ($entries as $entry) {
                        $stored[] = [
                            'question' => $entry['question'],
                            'answer' => $formatter->parse($entry['answer'], $article, $actor->isGuest() ? null : $actor),
                        ];
                    }

                    $article->faq = $stored === [] ? null : json_encode($stored);
                }),

            Schema\DateTime::make('createdAt')
                ->property('created_at'),
            Schema\DateTime::make('updatedAt')
                ->property('updated_at'),
            Schema\DateTime::make('lastEditedAt')
                ->property('last_edited_at')
                ->nullable(),

            Schema\Integer::make('revisionCount')
                ->get(fn (WikiArticle $article) => (int) ($article->revision_count ?? $article->revisions()->count())),

            Schema\Boolean::make('canUpdate')
                ->get(function (WikiArticle $article, FlarumContext $context) {
                    $actor = $context->getActor();
                    if ($actor->isGuest()) {
                        return false;
                    }
                    try {
                        return $actor->can('update', $article);
                    } catch (\Throwable $e) {
                        return false;
                    }
                }),

            Schema\Boolean::make('canDelete')
                ->get(function (WikiArticle $article, FlarumContext $context) {
                    $actor = $context->getActor();
                    if ($actor->isGuest()) {
                        return false;
                    }
                    try {
                        return $actor->can('delete', $article);
                    } catch (\Throwable $e) {
                        return false;
                    }
                }),

            // Writable boolean toggling the article's soft-delete state. PATCH
            // isDeleted=true soft-deletes; PATCH isDeleted=false restores.
            // Available to editors; the permanent DELETE is admin-only and
            // requires the article to be soft-deleted first.
            Schema\Boolean::make('isDeleted')
                ->get(fn (WikiArticle $article) => $article->deleted_at !== null)
                ->writable(function (WikiArticle $article, FlarumContext $context) {
                    return $context->updating() && WikiAbilities::isEditor($context->getActor());
                })
                ->set(function (WikiArticle $article, bool $value) {
                    if ($value && $article->deleted_at === null) {
                        $article->deleted_at = Carbon::now();
                    } elseif (! $value && $article->deleted_at !== null) {
                        $article->deleted_at = null;
                    }
                }),

            Schema\DateTime::make('deletedAt')
                ->property('deleted_at'),

            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),

            Schema\Relationship\ToOne::make('lastEditedBy')
                ->type('users')
                ->includable(),

            Schema\Relationship\ToOne::make('category')
                ->type('linkrobins-wiki-categories')
                ->includable()
                ->writable()
                ->set(function (WikiArticle $article, $value) {
                    if ($value === null) {
                        $article->category_id = null;
                    } elseif (is_object($value) && isset($value->id)) {
                        $article->category_id = (int) $value->id;
                    } elseif (is_numeric($value)) {
                        $article->category_id = (int) $value;
                    }
                }),
        ];
    }

    public function creating(object $model, Context $context): ?object
    {
        /** @var WikiArticle $model */
        $actor = $context->getActor();

        // Force authorship to the acting user -- never trust relationships.user
        // from the request body.
        if (! $actor->isGuest()) {
            $model->user_id = $actor->id;
            $model->last_edited_by_user_id = $actor->id;
        }

        $this->assertContent($model);

        // Resolve the optional category from the relationship in the body.
        $categoryRel = data_get($context->body(), 'data.relationships.category.data.id');
        if (is_numeric($categoryRel) && $category = WikiCategory::query()->find((int) $categoryRel)) {
            $model->category_id = $category->id;
        }

        $model->last_edited_at = Carbon::now();

        if ($model->slug !== null) {
            $this->assertSlugUsable($model->slug);
        }

        return $model;
    }

    /**
     * With the row saved (and its id known), give a slugless article one
     * derived from the title. Best-effort: a race on the unique index must
     * fail the slug, never the create, the article stays reachable by id.
     */
    public function created(object $model, Context $context): ?object
    {
        /** @var WikiArticle $model */
        if ($model->slug === null) {
            try {
                $model->slug = Slug::forArticle($model->title, (int) $model->id);
                $model->save();
            } catch (\Throwable $e) {
                $model->slug = null;
                $this->log->warning('[linkrobins/wiki] slug generation failed', ['exception' => $e]);
            }
        }

        return $model;
    }

    public function updating(object $model, Context $context): ?object
    {
        /** @var WikiArticle $model */
        // Block author tampering on update.
        $originalUserId = $model->getOriginal('user_id');
        if ((int) $model->user_id !== (int) $originalUserId) {
            $model->user_id = $originalUserId;
        }

        // Stamp edit metadata when the title or body actually changed. A pure
        // soft-delete / restore PATCH (isDeleted toggle) leaves both intact and
        // must not bump last_edited_at or write a revision.
        if ($model->isDirty('title') || $model->isDirty('content')) {
            $this->assertContent($model);
            $model->last_edited_at = Carbon::now();
            $model->last_edited_by_user_id = (int) $context->getActor()->id;
        }

        if ($model->isDirty('slug') && $model->slug !== null) {
            $this->assertSlugUsable($model->slug, (int) $model->id);
        }

        return $model;
    }

    /**
     * Permanent deletion. The delete policy already restricts this to admins;
     * here we require the article to be soft-deleted first so a stray DELETE on
     * a live article 400s instead of irreversibly wiping it and its revisions
     * (the FK cascade would otherwise remove everything in one click).
     */
    public function deleting(object $model, Context $context): void
    {
        /** @var WikiArticle $model */
        if ($model->deleted_at === null) {
            throw new BadRequestException(
                $this->translator->trans('linkrobins-wiki.api.article_soft_delete_first')
            );
        }

        $model->forceDelete();
    }

    protected function assertSlugUsable(string $slug, ?int $exceptId = null): void
    {
        if (Slug::isReserved($slug)) {
            throw new BadRequestException(
                $this->translator->trans('linkrobins-wiki.api.slug_invalid')
            );
        }

        if (Slug::isTaken($slug, $exceptId)) {
            throw new BadRequestException(
                $this->translator->trans('linkrobins-wiki.api.slug_taken')
            );
        }
    }

    protected function assertContent(object $model): void
    {
        $title = $model->getAttribute('title');
        if (! is_string($title) || trim($title) === '') {
            throw new BadRequestException(
                $this->translator->trans('linkrobins-wiki.api.title_required')
            );
        }

        $content = $model->getAttribute('content');
        if (! is_string($content) || trim(strip_tags($content)) === '') {
            throw new BadRequestException(
                $this->translator->trans('linkrobins-wiki.api.content_required')
            );
        }
    }
}
