<?php

namespace LinkRobins\Wiki;

use Flarum\Database\AbstractModel;
use Flarum\Formatter\Formattable;
use Flarum\Formatter\HasFormattedContent;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reader comment on an article. Distinct from a revision: revisions are the
 * article's edit history, comments are conversation about it. Soft-deletable so
 * editors can moderate without losing the thread.
 */
class WikiComment extends AbstractModel implements Formattable
{
    use HasFormattedContent;
    use SoftDeletes;

    protected $table = 'linkrobins_wiki_comments';

    public $timestamps = true;

    // user_id and article_id are set by the resource controller; content is the
    // only attribute the client controls directly.
    protected $fillable = [
        'content',
    ];

    protected $dates = [
        'deleted_at',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(WikiArticle::class, 'article_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
