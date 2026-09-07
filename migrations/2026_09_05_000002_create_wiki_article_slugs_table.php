<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// Every slug an article has previously answered to, so renaming one does not
// break the links people have already shared. Rows are kept for soft-deleted
// articles too: a restore should bring its old URLs back with it.
return Migration::createTable(
    'linkrobins_wiki_article_slugs',
    function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('article_id');
        $table->string('slug', 191)->unique();
        $table->dateTime('created_at')->nullable();

        $table->foreign('article_id')
            ->references('id')
            ->on('linkrobins_wiki_articles')
            ->onDelete('cascade');
    }
);
