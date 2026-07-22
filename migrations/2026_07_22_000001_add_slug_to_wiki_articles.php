<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

/**
 * Adds the nullable unique `slug` column to articles and backfills it from
 * existing titles. Deliberately self-contained (no extension classes) so it
 * stays valid however the extension's code evolves; the runtime rules live in
 * LinkRobins\Wiki\Slug and match what's applied here: lowercase kebab-case,
 * never purely numeric, never the reserved `new` path segment, unique across
 * all articles including soft-deleted ones.
 */
return [
    'up' => function (Illuminate\Database\Schema\Builder $schema) {
        if (! $schema->hasColumn('linkrobins_wiki_articles', 'slug')) {
            $schema->table('linkrobins_wiki_articles', function (Blueprint $table) {
                // 191 keeps the unique index inside utf8mb4 key-length limits
                // on older MySQL; runtime caps slugs at 150 anyway.
                $table->string('slug', 191)->nullable();
            });
        }

        // Backfill before the unique index exists, tracking every assigned
        // slug so duplicate titles can't collide. Chunked so a large wiki
        // doesn't load every row at once.
        $connection = $schema->getConnection();

        $used = $connection->table('linkrobins_wiki_articles')
            ->whereNotNull('slug')
            ->pluck('slug')
            ->all();
        $used = array_fill_keys(array_map('strval', $used), true);

        $connection->table('linkrobins_wiki_articles')
            ->whereNull('slug')
            ->orderBy('id')
            ->chunkById(100, function ($articles) use ($connection, &$used) {
                foreach ($articles as $article) {
                    $slug = Str::slug(Str::limit(trim((string) $article->title), 150, ''));

                    if ($slug === '') {
                        // Nothing slug-like in the title (e.g. fully non-latin):
                        // leave null, the id URL keeps working.
                        continue;
                    }

                    if ($slug === 'new' || preg_match('/^\d+$/', $slug) || isset($used[$slug])) {
                        $slug .= '-'.$article->id;
                    }

                    $used[$slug] = true;

                    $connection->table('linkrobins_wiki_articles')
                        ->where('id', $article->id)
                        ->update(['slug' => $slug]);
                }
            });

        // Race-safe unique index: another process may have created it between
        // our check and now, and index-existence probes aren't portable across
        // MySQL/Postgres/SQLite, so just try and tolerate "already exists".
        try {
            $schema->table('linkrobins_wiki_articles', function (Blueprint $table) {
                $table->unique('slug');
            });
        } catch (\Throwable $e) {
            // Index already present.
        }
    },

    'down' => function (Illuminate\Database\Schema\Builder $schema) {
        if (! $schema->hasColumn('linkrobins_wiki_articles', 'slug')) {
            return;
        }

        try {
            $schema->table('linkrobins_wiki_articles', function (Blueprint $table) {
                $table->dropUnique(['slug']);
            });
        } catch (\Throwable $e) {
            // Index never created (up() may have failed between column and index).
        }

        $schema->table('linkrobins_wiki_articles', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    },
];
