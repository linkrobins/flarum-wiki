<?php

use Flarum\Extend;
use Flarum\Search\Database\DatabaseSearchDriver;
use LinkRobins\Wiki\Access;
use LinkRobins\Wiki\Api\Resource\WikiArticleResource;
use LinkRobins\Wiki\Api\Resource\WikiCategoryResource;
use LinkRobins\Wiki\Api\Resource\WikiCommentResource;
use LinkRobins\Wiki\Api\Resource\WikiRevisionResource;
use LinkRobins\Wiki\Search\ArticleSearcher;
use LinkRobins\Wiki\Search\CommentSearcher;
use LinkRobins\Wiki\Search\Filter as Filters;
use LinkRobins\Wiki\Search\RevisionSearcher;
use LinkRobins\Wiki\WikiArticle;
use LinkRobins\Wiki\WikiCategory;
use LinkRobins\Wiki\WikiComment;
use LinkRobins\Wiki\WikiRevision;
use LinkRobins\Wiki\WikiServiceProvider;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less')
        ->route('/wiki',           'linkrobins-wiki.index')
        ->route('/wiki/new',       'linkrobins-wiki.compose')
        ->route('/wiki/{id}',      'linkrobins-wiki.show')
        ->route('/wiki/{id}/edit', 'linkrobins-wiki.edit'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\Settings())
        ->default('linkrobins-wiki.index_layout', '')
        // Table of contents: on by default, only shown once an article has at
        // least this many headings so short articles don't get a stub rail.
        ->default('linkrobins-wiki.toc_enabled', true)
        ->default('linkrobins-wiki.toc_min_headings', 2)
        // Where the global "Wiki" link sits in the index sidebar nav, and
        // whether wiki pages drop that sidebar and use the full width.
        ->default('linkrobins-wiki.nav_position', 'sections')
        ->default('linkrobins-wiki.full_width', false)
        ->serializeToForum('linkrobinsWikiIndexLayout', 'linkrobins-wiki.index_layout')
        ->serializeToForum(
            'linkrobinsWikiNavPosition',
            'linkrobins-wiki.nav_position',
            // An unset setting arrives as '', which is not one of the four
            // positions; fall back rather than let the frontend guess.
            fn ($value) => in_array($value, ['top', 'below_all', 'sections', 'bottom'], true) ? $value : 'sections'
        )
        ->serializeToForum('linkrobinsWikiFullWidth', 'linkrobins-wiki.full_width', fn ($value) => (bool) $value)
        ->serializeToForum('linkrobinsWikiTocEnabled', 'linkrobins-wiki.toc_enabled', fn ($value) => (bool) $value)
        ->serializeToForum('linkrobinsWikiTocMinHeadings', 'linkrobins-wiki.toc_min_headings', fn ($value) => max(1, (int) $value)),

    (new Extend\ApiResource(WikiCategoryResource::class)),
    (new Extend\ApiResource(WikiArticleResource::class)),
    (new Extend\ApiResource(WikiRevisionResource::class)),
    (new Extend\ApiResource(WikiCommentResource::class)),

    (new Extend\Policy())
        ->modelPolicy(WikiArticle::class,  Access\WikiArticlePolicy::class)
        ->modelPolicy(WikiCategory::class, Access\WikiCategoryPolicy::class)
        ->modelPolicy(WikiComment::class,  Access\WikiCommentPolicy::class)
        ->globalPolicy(Access\GlobalPolicy::class),

    (new Extend\ServiceProvider())
        ->register(WikiServiceProvider::class),

    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addSearcher(WikiArticle::class, ArticleSearcher::class)
        ->addFilter(ArticleSearcher::class, Filters\CategoryIdFilter::class)
        ->addSearcher(WikiRevision::class, RevisionSearcher::class)
        ->addFilter(RevisionSearcher::class, Filters\ArticleIdFilter::class)
        ->addSearcher(WikiComment::class, CommentSearcher::class)
        ->addFilter(CommentSearcher::class, Filters\CommentArticleIdFilter::class),

    (new Extend\ApiResource(\Flarum\Api\Resource\ForumResource::class))
        ->fields(fn () => [
            // Whether the current user may start / edit articles. The frontend
            // uses these to show or hide the "New article" and edit controls.
            // Admins always pass. A policy can() shouldn't throw under normal
            // operation; if it somehow does, degrade to false rather than 500
            // the forum boot payload (this field ships on every forum response).
            \Flarum\Api\Schema\Boolean::make('canCreateWikiArticle')
                ->get(function ($model, \Flarum\Api\Context $context) {
                    $actor = $context->getActor();
                    if ($actor->isGuest()) {
                        return false;
                    }
                    try {
                        return $actor->can('createArticle');
                    } catch (\Throwable $e) {
                        return false;
                    }
                }),

            \Flarum\Api\Schema\Boolean::make('canEditWikiArticles')
                ->get(function ($model, \Flarum\Api\Context $context) {
                    $actor = $context->getActor();
                    if ($actor->isGuest()) {
                        return false;
                    }
                    try {
                        return $actor->can('editArticles');
                    } catch (\Throwable $e) {
                        return false;
                    }
                }),

            \Flarum\Api\Schema\Boolean::make('canCommentWiki')
                ->get(function ($model, \Flarum\Api\Context $context) {
                    $actor = $context->getActor();
                    if ($actor->isGuest()) {
                        return false;
                    }
                    try {
                        return $actor->can('comment');
                    } catch (\Throwable $e) {
                        return false;
                    }
                }),

            // Unlike the fields above, guests can hold viewHistory (it's
            // seeded to the guest group so history stays public by default),
            // so there is no isGuest() short-circuit here.
            \Flarum\Api\Schema\Boolean::make('canViewWikiHistory')
                ->get(function ($model, \Flarum\Api\Context $context) {
                    try {
                        return $context->getActor()->can('viewHistory');
                    } catch (\Throwable $e) {
                        return false;
                    }
                }),
        ]),
];
