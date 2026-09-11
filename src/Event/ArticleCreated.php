<?php

namespace LinkRobins\Wiki\Event;

use Flarum\User\User;
use LinkRobins\Wiki\WikiArticle;

/**
 * An article was created.
 */
class ArticleCreated
{
    public function __construct(
        public WikiArticle $article,
        public User $actor
    ) {
    }
}
