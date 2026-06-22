<?php

namespace LinkRobins\Wiki\Search;

use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use LinkRobins\Wiki\Access\WikiAbilities;
use LinkRobins\Wiki\WikiComment;

/**
 * Searcher for article comments. Comments are public; the only rule is that
 * soft-deleted comments stay visible to editors (for moderation) and are
 * hidden from everyone else by the default SoftDeletes scope. Scoped to one
 * article via the articleId filter.
 */
class CommentSearcher extends AbstractSearcher
{
    public function getQuery(User $actor): Builder
    {
        $query = WikiComment::query()->select('linkrobins_wiki_comments.*');

        if (WikiAbilities::isEditor($actor)) {
            $query->withTrashed();
        }

        return $query;
    }
}
