<?php

use Flarum\Extend;
use Flarum\Search\Database\DatabaseSearchDriver;
use LinkRobins\Wiki\Access;
use LinkRobins\Wiki\Api\Resource\WikiArticleResource;
use LinkRobins\Wiki\Api\Resource\WikiCategoryResource;
use LinkRobins\Wiki\Api\Resource\WikiRevisionResource;
use LinkRobins\Wiki\Search\ArticleSearcher;
use LinkRobins\Wiki\Search\Filter as Filters;
use LinkRobins\Wiki\Search\RevisionSearcher;
use LinkRobins\Wiki\WikiArticle;
use LinkRobins\Wiki\WikiCategory;
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
        ->serializeToForum('linkrobinsWikiIndexLayout', 'linkrobins-wiki.index_layout'),

    (new Extend\ApiResource(WikiCategoryResource::class)),
    (new Extend\ApiResource(WikiArticleResource::class)),
    (new Extend\ApiResource(WikiRevisionResource::class)),

    (new Extend\Policy())
        ->modelPolicy(WikiArticle::class,  Access\WikiArticlePolicy::class)
        ->modelPolicy(WikiCategory::class, Access\WikiCategoryPolicy::class)
        ->globalPolicy(Access\GlobalPolicy::class),

    (new Extend\ServiceProvider())
        ->register(WikiServiceProvider::class),

    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addSearcher(WikiArticle::class, ArticleSearcher::class)
        ->addFilter(ArticleSearcher::class, Filters\CategoryIdFilter::class)
        ->addSearcher(WikiRevision::class, RevisionSearcher::class)
        ->addFilter(RevisionSearcher::class, Filters\ArticleIdFilter::class),

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
        ]),
];
