<?php

namespace LinkRobins\Wiki\Access;

use Flarum\User\User;

/**
 * Single source of truth for the wiki permission checks shared across the
 * policies, searchers, resources and service provider.
 *
 *   createArticle -- start a new article
 *   editArticles  -- edit and moderate (soft-delete / restore) any article,
 *                    not just one's own
 *
 * Authors can always edit their own articles regardless of editArticles; that
 * ownership check lives in WikiArticlePolicy. Permanent (force-)deletion is
 * admin-only and is not represented here.
 */
class WikiAbilities
{
    public const CREATE_ARTICLE = 'linkrobins-wiki.createArticle';
    public const EDIT_ARTICLES = 'linkrobins-wiki.editArticles';
    public const COMMENT = 'linkrobins-wiki.comment';
    public const VIEW_HISTORY = 'linkrobins-wiki.viewHistory';

    /**
     * Whether the actor may edit and moderate any article (admins always can).
     */
    public static function isEditor(User $actor): bool
    {
        if ($actor->isGuest()) {
            return false;
        }

        return $actor->isAdmin() || $actor->hasPermission(self::EDIT_ARTICLES);
    }

    /**
     * Whether the actor may post comments on articles (admins always can).
     */
    public static function canComment(User $actor): bool
    {
        if ($actor->isGuest()) {
            return false;
        }

        return $actor->isAdmin() || $actor->hasPermission(self::COMMENT);
    }

    /**
     * Whether the actor may view an article's revision history. Unlike the
     * other abilities this one is open to guests: it's seeded to the guest
     * group (visible to everyone) and admins can restrict it per group.
     */
    public static function canViewHistory(User $actor): bool
    {
        return $actor->isAdmin() || $actor->hasPermission(self::VIEW_HISTORY);
    }

    /**
     * Whether the actor may start new articles (admins always can).
     */
    public static function canCreate(User $actor): bool
    {
        if ($actor->isGuest()) {
            return false;
        }

        return $actor->isAdmin() || $actor->hasPermission(self::CREATE_ARTICLE);
    }
}
