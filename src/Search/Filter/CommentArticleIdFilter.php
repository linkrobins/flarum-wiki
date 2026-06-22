<?php

namespace LinkRobins\Wiki\Search\Filter;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * `filter[articleId]=N` narrows a comment listing to a single article. The
 * comments table needs its own filter (the revision one targets a different
 * table), even though both share the `articleId` key.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class CommentArticleIdFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'articleId';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $ids = $this->asIntArray($value);
        if (empty($ids)) {
            return;
        }
        $state->getQuery()->whereIn(
            'linkrobins_wiki_comments.article_id',
            $ids,
            'and',
            $negate
        );
    }
}
