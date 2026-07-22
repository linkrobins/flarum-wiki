<?php

namespace LinkRobins\Wiki;

use Illuminate\Support\Str;

/**
 * Article slug rules, shared by the API resource's validation and the
 * auto-generation on create. Slugs are lowercase kebab-case, capped well below
 * the column length, and may never be purely numeric or a reserved path
 * segment: `/wiki/{slug}` shares its namespace with `/wiki/{id}` and
 * `/wiki/new`, so such a slug would be unreachable (or worse, shadow another
 * article's id URL).
 */
final class Slug
{
    public const MAX_LENGTH = 150;

    /**
     * Normalize arbitrary input into slug form. Returns null when nothing
     * slug-like survives (blank input, or a title with no latin letters or
     * digits), which callers treat as "no slug given".
     */
    public static function normalize(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $slug = Str::slug(Str::limit(trim($value), self::MAX_LENGTH, ''));

        return $slug === '' ? null : $slug;
    }

    /**
     * Whether a (normalized) slug can never be routed to an article.
     */
    public static function isReserved(string $slug): bool
    {
        return $slug === 'new' || preg_match('/^\d+$/', $slug) === 1;
    }

    /**
     * Whether another article (including soft-deleted ones, whose URLs must
     * stay reserved for restore) already owns this slug.
     */
    public static function isTaken(string $slug, ?int $exceptId = null): bool
    {
        $query = WikiArticle::query()->withTrashed()->where('slug', $slug);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    /**
     * A unique, routable slug for a saved article, derived from its title.
     * Falls back to suffixing the id, which is unique by construction and
     * de-reserves an all-digit base.
     */
    public static function forArticle(string $title, int $id): string
    {
        $base = self::normalize($title) ?? 'article';

        if (self::isReserved($base) || self::isTaken($base, $id)) {
            $base .= '-'.$id;
        }

        return $base;
    }
}
