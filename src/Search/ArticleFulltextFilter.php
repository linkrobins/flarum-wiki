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
        // prefix, so every fragment below uses a grammar-wrapped identifier.
        $grammar = $query->getQuery()->getGrammar();
        $wrapped = [
            'title' => $grammar->wrap('linkrobins_wiki_articles.title'),
            'content' => $grammar->wrap('linkrobins_wiki_articles.content'),
        ];

        // Every LIKE names its escape character. SQLite has no default one, so
        // without ESCAPE a search for "50%" or "user_name" treated the escaped
        // wildcard as a literal backslash and found nothing there.
        $query->where(function ($query) use ($driver, $term, $wrapped) {
            foreach (['title', 'content'] as $column) {
                match ($driver) {
                    'pgsql' => $query->orWhereRaw("{$wrapped[$column]} ILIKE ? ESCAPE '!'", [$term]),
                    'sqlite' => $query->orWhereRaw("LOWER({$wrapped[$column]}) LIKE ? ESCAPE '!'", [mb_strtolower($term)]),
                    default => $query->orWhereRaw("{$wrapped[$column]} LIKE ? ESCAPE '!'", [$term]),
                };
            }
        });

        $titleMatch = $driver === 'pgsql'
            ? "CASE WHEN {$wrapped['title']} ILIKE ? ESCAPE '!' THEN 0 ELSE 1 END"
            : "CASE WHEN LOWER({$wrapped['title']}) LIKE ? ESCAPE '!' THEN 0 ELSE 1 END";

        $query->orderByRaw($titleMatch, [mb_strtolower($term)]);
    }

    /**
     * Stop a user's % or _ from turning into a wildcard, and a backslash from
     * escaping whatever follows it.
     */
    private function escapeLike(string $value): string
    {
        // '!' rather than a backslash: a backslash means different things in
        // MySQL and PostgreSQL string literals, '!' means nothing to either.
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
