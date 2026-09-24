<?php

namespace LinkRobins\Wiki\Access;

use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;
use LinkRobins\Wiki\WikiArticle;

/**
 * Per-article permissions.
 *
 *   view   -- everyone (the wiki is public)
 *   update -- the author, or an editor (lr-wiki.editArticles)
 *   delete -- admin only (permanent removal; soft-delete / restore is the
 *             isDeleted toggle on the resource, gated by editArticles)
 */
class WikiArticlePolicy extends AbstractPolicy
{
    public function view(User $actor, WikiArticle $article): bool
    {
        return true;
    }

    public function update(User $actor, WikiArticle $article): bool
    {
        if (WikiAbilities::isEditor($actor)) {
            return true;
        }
        return $this->isAuthor($actor, $article);
    }

    public function delete(User $actor, WikiArticle $article): bool
    {
        return $actor->isAdmin();
    }

    protected function isAuthor(User $actor, WikiArticle $article): bool
    {
        return ! $actor->isGuest()
            && $article->user_id !== null
            && (int) $article->user_id === (int) $actor->id;
    }
}
