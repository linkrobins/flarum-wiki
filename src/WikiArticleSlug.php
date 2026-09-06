<?php

namespace LinkRobins\Wiki;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A slug an article used to answer to. Kept so a renamed article's old links
 * keep working instead of 404ing.
 *
 * @property int $id
 * @property int $article_id
 * @property string $slug
 * @property \Carbon\Carbon|null $created_at
 */
class WikiArticleSlug extends AbstractModel
{
    protected $table = 'linkrobins_wiki_article_slugs';

    public $timestamps = false;

    protected $fillable = [
        'article_id',
        'slug',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(WikiArticle::class, 'article_id');
    }
}
