<?php

use Flarum\Database\Migration;

// Optional manual order within a listing. Null means "unordered", which is
// every existing article, so nothing moves until someone sets a position; the
// listings sort nulls last and fall back to the date order they used before.
return Migration::addColumns('linkrobins_wiki_articles', [
    'position' => ['integer', 'nullable' => true],
]);
