<?php

namespace LinkRobins\Wiki\Access;

use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

/**
 * Global wiki permissions.
 *
 *   linkrobins-wiki.createArticle -- start new articles
 *   linkrobins-wiki.editArticles  -- edit / moderate any article
 *   linkrobins-wiki.viewHistory   -- view article revision history
 *
 * Admins always pass. Category management is admin-only.
 */
class GlobalPolicy extends AbstractPolicy
{
    public function createArticle(User $actor): bool
    {
        return WikiAbilities::canCreate($actor);
    }

    public function editArticles(User $actor): bool
    {
        return WikiAbilities::isEditor($actor);
    }

    public function comment(User $actor): bool
    {
        return WikiAbilities::canComment($actor);
    }

    public function viewHistory(User $actor): bool
    {
        return WikiAbilities::canViewHistory($actor);
    }

    public function manageCategories(User $actor): bool
    {
        return $actor->isAdmin();
    }
}
