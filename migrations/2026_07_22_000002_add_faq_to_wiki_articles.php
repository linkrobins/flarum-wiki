<?php

use Flarum\Database\Migration;

// JSON list of {question, answer} pairs shown as an accordion under the
// article; null means the article has no FAQ. Answers hold the formatter's
// parsed representation, like the article body.
return Migration::addColumns('linkrobins_wiki_articles', [
    'faq' => ['mediumText', 'nullable' => true],
]);
