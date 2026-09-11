<?php

namespace LinkRobins\Wiki\Event;

use Flarum\User\User;
use LinkRobins\Wiki\WikiArticle;

/**
 * An article was restored.
 */
class ArticleRestored
{
    public function __construct(
        public WikiArticle $article,
        public User $actor
    ) {
    }
}
