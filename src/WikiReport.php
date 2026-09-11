<?php

namespace LinkRobins\Wiki;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reader's report about an article.
 *
 * Flarum's flags cannot carry these. The `flags` table keys on `post_id` with a
 * foreign key into `posts`, and a wiki article never has a row there, so this
 * is a parallel structure rather than an integration. Reports are logged to
 * flarum/audit as well, so an editor who lives in the audit trail sees them
 * without watching a second inbox.
 *
 * @property int $id
 * @property int $article_id
 * @property int|null $user_id
 * @property string $reason
 * @property string|null $detail
 * @property \Carbon\Carbon|null $resolved_at
 * @property int|null $resolved_by_user_id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read WikiArticle|null $article
 * @property-read User|null $user
 * @property-read User|null $resolvedBy
 */
class WikiReport extends AbstractModel
{
    /**
     * The reasons a reader may pick. Kept as a closed list so the admin list can
     * group and translate them, with the reporter's own words in `detail`.
     *
     * @var list<string>
     */
    public const REASONS = ['inaccurate', 'outdated', 'off_topic', 'duplicate', 'other'];

    protected $table = 'linkrobins_wiki_reports';

    public $timestamps = true;

    /** @var list<string> */
    protected $dates = [
        'resolved_at',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(WikiArticle::class, 'article_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
