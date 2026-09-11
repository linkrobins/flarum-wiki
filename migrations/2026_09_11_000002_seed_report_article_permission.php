<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

// Reporting is a member action: seeded to the member group so a signed-in
// reader can raise something the day the feature lands, while a guest cannot
// fill the queue. Admins can move it in the permission grid.
return Migration::addPermissions([
    'linkrobins-wiki.reportArticle' => Group::MEMBER_ID,
]);
