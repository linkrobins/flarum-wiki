<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// Reader reports about an article. Flarum's own flags cannot hold these: the
// `flags` table keys on `post_id` with a foreign key into `posts`, and a wiki
// article never has a row there, so reports need their own home.
return Migration::createTableIfNotExists('linkrobins_wiki_reports', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('article_id')->unsigned();
    $table->integer('user_id')->unsigned()->nullable();
    // A short key from a fixed list (off_topic, inaccurate, ...) plus the
    // reporter's own words, which is what usually explains the report.
    $table->string('reason', 50);
    $table->text('detail')->nullable();
    // Null while the report is open. Kept rather than deleted on resolve, so a
    // repeatedly reported article still shows its history to an editor.
    $table->timestamp('resolved_at')->nullable();
    $table->integer('resolved_by_user_id')->unsigned()->nullable();
    $table->timestamps();

    $table->index('article_id');
    $table->index('user_id');
    // The admin list only ever wants the open ones, newest first.
    $table->index(['resolved_at', 'created_at']);

    $table->foreign('article_id')
        ->references('id')->on('linkrobins_wiki_articles')
        ->cascadeOnDelete();
    $table->foreign('user_id')
        ->references('id')->on('users')
        ->nullOnDelete();
    $table->foreign('resolved_by_user_id')
        ->references('id')->on('users')
        ->nullOnDelete();
});
