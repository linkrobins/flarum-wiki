<?php

namespace LinkRobins\Wiki;

use Flarum\Database\AbstractModel;
use Flarum\Formatter\Formattable;
use Flarum\Formatter\HasFormattedContent;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use s9e\TextFormatter\Utils as TextFormatterUtils;

/**
 * @property int $id
 * @property bool $is_draft
 * @property int|null $category_id
 * @property int|null $user_id
 * @property int|null $last_edited_by_user_id
 * @property string $title
 * @property string|null $slug
 * @property string $content
 * @property string|null $faq
 * @property \Carbon\Carbon|null $last_edited_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read int|null $revision_count
 * @property-read WikiCategory|null $category
 * @property-read User|null $user
 * @property-read User|null $lastEditedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WikiRevision> $revisions
 */
class WikiArticle extends AbstractModel implements Formattable
{
    use HasFormattedContent;
    use SoftDeletes;

    protected $table = 'linkrobins_wiki_articles';

    public $timestamps = true;

    // user_id (the author) and last_edited_by_user_id are set by the resource
    // controller, never by mass assignment. title and content are the only
    // attributes the client controls directly.
    protected $fillable = [
        'title',
        'content',
        'position',
        'is_draft',
    ];

    protected $casts = [
        'last_edited_at' => 'datetime',
        'position' => 'integer',
        'is_draft' => 'boolean',
    ];

    /** @var list<string> */
    protected $dates = [
        'deleted_at',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(WikiCategory::class, 'category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lastEditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by_user_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(WikiRevision::class, 'article_id');
    }

    /**
     * @param Builder<self> $query
     */
    public static function findByAddress(Builder $query, string $address): ?self
    {
        if (preg_match('/^\d+$/', $address)) {
            return (clone $query)->find($address);
        }

        $article = (clone $query)->where('slug', $address)->first();

        if ($article) {
            return $article;
        }

        $historic = WikiArticleSlug::query()->where('slug', $address)->value('article_id');

        return $historic ? $query->find($historic) : null;
    }

    public function excerpt(int $width = 180): string
    {
        $content = (string) $this->parsed_content;

        if ($content === '') {
            return '';
        }

        $plain = trim((string) preg_replace('/\s+/u', ' ', TextFormatterUtils::removeFormatting($content)));

        return mb_strimwidth($plain, 0, $width, '…');
    }
}
