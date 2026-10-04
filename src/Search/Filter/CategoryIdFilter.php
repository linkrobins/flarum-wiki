<?php

namespace LinkRobins\Wiki\Search\Filter;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * `filter[categoryId]=N` narrows an article listing to a single category, and
 * `filter[categoryId]=none` to the articles filed under no category (the
 * wiki home page's "Other" group and its "See all" page).
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class CategoryIdFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'categoryId';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        if ($value === 'none') {
            $state->getQuery()->whereNull('linkrobins_wiki_articles.category_id', 'and', $negate);

            return;
        }

        $ids = $this->asIntArray($value);
        if (empty($ids)) {
            return;
        }
        $state->getQuery()->whereIn(
            'linkrobins_wiki_articles.category_id',
            $ids,
            'and',
            $negate
        );
    }
}
