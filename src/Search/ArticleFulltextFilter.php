<?php

namespace LinkRobins\Wiki\Search;

use Flarum\Search\AbstractFulltextFilter;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\SearchState;

/**
 * Text search over wiki articles, applied whenever a request carries filter[q].
 *
 * Deliberately LIKE rather than a MySQL FULLTEXT index: a wiki holds hundreds of
 * articles rather than the hundreds of thousands of posts a forum accumulates,
 * so a scan is cheap, and it keeps the extension working the same way on every
 * database the CI matrix covers without shipping an index migration. It also
 * matches partial words, which a FULLTEXT index does not, and that is what
 * people expect when searching a handbook for "instal".
 *
 * Title matches sort ahead of body matches, since an article named after the
 * search term is nearly always the one being looked for.
 */
class ArticleFulltextFilter extends AbstractFulltextFilter
{
    /**
     * @param DatabaseSearchState $state
     */
    public function search(SearchState $state, string $value): void
    {
        $query = $state->getQuery();
        $term = '%'.$this->escapeLike($value).'%';

        // pgsql needs ILIKE and sqlite has no case-insensitive LIKE for
        // non-ASCII, so both get explicit lowering; MySQL and MariaDB are
        // case-insensitive already under their default collations.
        $driver = $query->getConnection()->getDriverName();

        // Raw SQL bypasses the query grammar, which is what applies the table
        // prefix, so every raw fragment below uses a hand-wrapped identifier.
        // The non-raw builder calls can keep the plain name.
        $grammar = $query->getQuery()->getGrammar();
        $wrapped = [
            'title' => $grammar->wrap('linkrobins_wiki_articles.title'),
            'content' => $grammar->wrap('linkrobins_wiki_articles.content'),
        ];

        $query->where(function ($query) use ($driver, $term, $wrapped) {
            foreach (['title', 'content'] as $column) {
                $plain = 'linkrobins_wiki_articles.'.$column;

                match ($driver) {
                    'pgsql' => $query->orWhere($plain, 'ilike', $term),
                    'sqlite' => $query->orWhereRaw("LOWER({$wrapped[$column]}) LIKE ?", [mb_strtolower($term)]),
                    default => $query->orWhere($plain, 'like', $term),
                };
            }
        });

        $titleMatch = $driver === 'pgsql'
            ? "CASE WHEN {$wrapped['title']} ILIKE ? THEN 0 ELSE 1 END"
            : "CASE WHEN LOWER({$wrapped['title']}) LIKE ? THEN 0 ELSE 1 END";

        $query->orderByRaw($titleMatch, [mb_strtolower($term)]);
    }

    /**
     * Stop a user's % or _ from turning into a wildcard, and a backslash from
     * escaping whatever follows it.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
