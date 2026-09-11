<?php

namespace LinkRobins\Wiki\Event;

use Flarum\User\User;
use LinkRobins\Wiki\WikiArticle;

/**
 * An article was deleted.
 */
class ArticleDeleted
{
    public function __construct(
        public WikiArticle $article,
        public User $actor
    ) {
    }
}
