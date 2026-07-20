<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

// Revision history has always been visible to everyone; seeding the new
// viewHistory permission to the guest group keeps that behaviour on upgrade
// (guest permissions apply to every visitor) while letting admins restrict it
// per group from the permission grid.
return Migration::addPermissions([
    'linkrobins-wiki.viewHistory' => Group::GUEST_ID,
]);
