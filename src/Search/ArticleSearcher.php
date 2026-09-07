<?php

namespace LinkRobins\Wiki\Search;

use Flarum\Search\Database\AbstractSearcher;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use LinkRobins\Wiki\Access\WikiAbilities;
use LinkRobins\Wiki\WikiArticle;

/**
 * Searcher for wiki articles. Articles are public, so the only visibility rule
 * concerns soft-deleted rows: editors see them in the list (rendered with a
 * "deleted" treatment) so they can restore or permanently remove them, while
 * everyone else gets the default SoftDeletes scope that hides trashed rows.
 *
 * Mirrors WikiArticleResource::scope, which applies the same rule on Show.
 */
class ArticleSearcher extends AbstractSearcher
{
    /**
     * Order unpositioned articles last when sorting by the manual position.
     *
     * MySQL sorts NULL first ascending, which would put every article nobody
     * has arranged above the ones someone deliberately ordered. The extra term
     * goes on before the parent's, so it is the primary ordering, and the rest
     * of the requested sort still breaks ties underneath it.
     *
     * This override is also the only place that can do it: the model has a
     * searcher, so Api\Endpoint\Index takes the search path and never applies
     * the resource's own sorts.
     */
    protected function applySort(DatabaseSearchState $state, ?array $sort = null, bool $sortIsDefault = false): void
    {
        if (! $sortIsDefault && is_array($sort) && array_key_exists('position', $sort)) {
            $query = $state->getQuery();

            // Raw SQL bypasses the query grammar, so the table prefix has to be
            // applied by hand or this breaks every install that uses one.
            $position = $query->getQuery()->getGrammar()->wrap('linkrobins_wiki_articles.position');

            $query->orderByRaw("$position IS NULL");
        }

        parent::applySort($state, $sort, $sortIsDefault);
    }

    public function getQuery(User $actor): Builder
    {
        $query = WikiArticle::query()
            ->select('linkrobins_wiki_articles.*')
            ->withCount('revisions as revision_count');

        if (WikiAbilities::isEditor($actor)) {
            $query->withTrashed();
        }

        // The Index endpoint runs through here, not the resource's scope, so
        // the draft rule has to be applied again or every listing and every
        // search would leak unfinished articles.
        WikiAbilities::scopeVisibleDrafts($query, $actor);

        return $query;
    }
}
