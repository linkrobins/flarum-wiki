<?php

namespace LinkRobins\Wiki;

use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Formatter\Formatter;
use Psr\Log\LoggerInterface;

class WikiServiceProvider extends AbstractServiceProvider
{
    public function boot(Formatter $formatter, LoggerInterface $log): void
    {
        // Plug Flarum's formatter into the article and revision models so
        // their bodies run through the same Markdown/BBCode pipeline
        // discussions use. The parsed source is stored in `content`; rendered
        // HTML is produced on demand via formatContent() at serialize time.
        WikiArticle::setFormatter($formatter);
        WikiRevision::setFormatter($formatter);
        WikiComment::setFormatter($formatter);

        // Snapshot the article into the revision history on every save that
        // changes the title or body. The first save records the article as
        // created; subsequent edits record each new version. last_edited_at /
        // last_edited_by_user_id are set on the article by the resource before
        // it saves, so the snapshot can attribute the edit.
        WikiArticle::created(function (WikiArticle $article) use ($log) {
            try {
                static::writeRevision($article);
            } catch (\Throwable $e) {
                $log->warning('[linkrobins/wiki] initial revision write failed', ['exception' => $e]);
            }
        });

        WikiArticle::updated(function (WikiArticle $article) use ($log) {
            try {
                if ($article->wasChanged('title') || $article->wasChanged('content')) {
                    static::writeRevision($article);
                }
            } catch (\Throwable $e) {
                $log->warning('[linkrobins/wiki] revision write failed', ['exception' => $e]);
            }
        });
    }

    protected static function writeRevision(WikiArticle $article): void
    {
        $revision = new WikiRevision();
        $revision->article_id = $article->id;
        $revision->user_id    = $article->last_edited_by_user_id ?? $article->user_id;
        $revision->title      = $article->title;
        $revision->content    = $article->content;
        $revision->save();
    }
}
