<?php

namespace LinkRobins\Wiki\Access;

use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;
use LinkRobins\Wiki\WikiComment;

/**
 * Per-comment permissions.
 *
 *   update -- the author (edit own / soft-delete own) or an editor (moderate any)
 *   delete -- admin only (permanent removal of an already soft-deleted comment)
 */
class WikiCommentPolicy extends AbstractPolicy
{
    public function update(User $actor, WikiComment $comment): bool
    {
        if (WikiAbilities::isEditor($actor)) {
            return true;
        }
        return $this->isAuthor($actor, $comment);
    }

    public function delete(User $actor, WikiComment $comment): bool
    {
        return $actor->isAdmin();
    }

    protected function isAuthor(User $actor, WikiComment $comment): bool
    {
        return ! $actor->isGuest()
            && $comment->user_id !== null
            && (int) $comment->user_id === (int) $actor->id;
    }
}
