<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// Public discussion thread attached to an article. Separate from revisions
// (which are edit history) -- comments are reader conversation.
return Migration::createTableIfNotExists('linkrobins_wiki_comments', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('article_id')->unsigned();
    $table->integer('user_id')->unsigned()->nullable();
    $table->mediumText('content');
    $table->timestamp('deleted_at')->nullable();
    $table->timestamps();

    $table->index('article_id');
    $table->index('user_id');
    $table->index('deleted_at');

    $table->foreign('article_id')
        ->references('id')->on('linkrobins_wiki_articles')
        ->cascadeOnDelete();
    $table->foreign('user_id')
        ->references('id')->on('users')
        ->nullOnDelete();
});
