<?php

namespace LinkRobins\Wiki\Event;

use Flarum\User\User;
use LinkRobins\Wiki\WikiReport;

/**
 * A reader reported an article.
 *
 * Dispatched so the audit log can record it (see the Audit extender in
 * extend.php) and so other extensions have something to hook, which nothing in
 * this extension offered before.
 */
class ArticleReported
{
    public function __construct(
        public WikiReport $report,
        public User $actor
    ) {
    }
}
