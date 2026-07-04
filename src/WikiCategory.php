<?php

namespace LinkRobins\Wiki;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $color
 * @property string|null $icon
 * @property int $position
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WikiArticle> $articles
 */
class WikiCategory extends AbstractModel
{
    protected $table = 'linkrobins_wiki_categories';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(WikiArticle::class, 'category_id');
    }
}
