<?php

use Flarum\Database\Migration;

// Whether the article is still being written. Defaults to false so every
// existing article stays published; only articles saved as a draft from here
// on are hidden from readers.
return Migration::addColumns('linkrobins_wiki_articles', [
    'is_draft' => ['boolean', 'default' => false],
]);
