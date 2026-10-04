<?php

use Flarum\Group\Group;
use Illuminate\Database\Schema\Builder;

/**
 * Let members comment out of the box. Commenting never had a seed, so on a
 * fresh install only admins could comment until someone found the permission
 * in the grid.
 *
 * Only seeds when no group holds the permission yet: a forum where an admin
 * has already granted it to some groups made a choice, and widening it to
 * every member on upgrade would undo that.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        if ($db->table('group_permission')->where('permission', 'lr-wiki.comment')->exists()) {
            return;
        }

        if ($db->table('groups')->where('id', Group::MEMBER_ID)->doesntExist()) {
            return;
        }

        $db->table('group_permission')->insert([
            'group_id' => Group::MEMBER_ID,
            'permission' => 'lr-wiki.comment',
        ]);
    },

    // Nothing to undo safely: by now the grant may be one an admin chose.
    'down' => function (Builder $schema) {
    },
];
