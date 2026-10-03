<?php

namespace LinkRobins\Wiki\Sitemap;

use Carbon\Carbon;
use Flarum\User\Guest;
use FoF\Sitemap\Resources\Resource;
use FoF\Sitemap\Sitemap\Frequency;
use Illuminate\Database\Eloquent\Builder;
use LinkRobins\Wiki\Access\WikiAbilities;
use LinkRobins\Wiki\WikiArticle;

/**
 * Wiki articles in fof/sitemap's sitemap, on forums that run it.
 *
 * Lists exactly what a guest can open: published articles that are not
 * deleted (the model's default scope) and not drafts (the same rule the API
 * applies). So the sitemap never points at a 404 and never leaves out an
 * article a visitor could read.
 */
class ArticleSitemapResource extends Resource
{
    public function query(): Builder
    {
        $query = WikiArticle::query()->select(['id', 'slug', 'is_draft', 'last_edited_at', 'updated_at', 'created_at']);

        return WikiAbilities::scopeVisibleDrafts($query, new Guest());
    }

    /**
     * The parent leaves $model untyped, so the type lives here rather than in
     * the signature: a native type would narrow the parameter, which PHP
     * rejects.
     *
     * @param WikiArticle $model
     */
    public function url($model): string
    {
        // The slug URL is the canonical one; the article page says so too.
        return $this->generateRouteUrl('linkrobins-wiki.show', [
            'id' => $model->slug ?: $model->id,
        ]);
    }

    public function priority(): float
    {
        // Reference material: just under discussions, which fof/sitemap
        // gives 0.9.
        return 0.8;
    }

    public function frequency(): string
    {
        return Frequency::WEEKLY;
    }

    /** @param WikiArticle $model */
    public function lastModifiedAt($model): Carbon
    {
        return $model->last_edited_at ?? $model->updated_at ?? $model->created_at ?? Carbon::now();
    }

    /**
     * Recently edited articles are worth revisiting sooner, as fof/sitemap
     * does for discussions.
     *
     * @param WikiArticle $model
     */
    public function dynamicFrequency($model): string
    {
        $days = $this->lastModifiedAt($model)->diffInDays(Carbon::now(), true);

        if ($days < 7) {
            return Frequency::DAILY;
        }
        if ($days < 30) {
            return Frequency::WEEKLY;
        }

        return Frequency::MONTHLY;
    }
}
