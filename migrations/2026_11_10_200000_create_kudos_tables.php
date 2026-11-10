<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * 🚨 Index and foreign key names are left to Laravel, which prefixes them with
 * the table prefix: hand-written names are not prefixed, and MySQL foreign key
 * names are database-wide, so two forums sharing a database would clash.
 * The longest generated name is 41 characters before the prefix
 * (MigrationTest holds every name to 64 with a 9-character prefix).
 */
return [
    'up' => function (Builder $schema) {
        $schema->create('kudos', function (Blueprint $table) {
            $table->increments('id');
            // Null once the giver's account is gone: the thanks stays, from "a former member".
            $table->unsignedInteger('giver_id')->nullable();
            $table->unsignedInteger('receiver_id');
            // The first day of the week it was given in (the forum's week start and timezone).
            $table->date('week');
            $table->string('note', 500);
            $table->unsignedInteger('post_id')->nullable();
            $table->string('style', 20);
            // approved, or pending while the moderation queue is on.
            $table->string('status', 10)->default('approved');
            $table->dateTime('created_at');
            $table->dateTime('approved_at')->nullable();
            // A moderator removed the note: the kudos stands, its words don't show.
            $table->dateTime('note_hidden_at')->nullable();
            $table->unsignedInteger('note_hidden_by')->nullable();

            // One kudos per giver per receiver per week, even if two requests race.
            $table->unique(['giver_id', 'receiver_id', 'week']);
            $table->index(['week', 'status']);
            $table->index(['receiver_id', 'status']);
            $table->index(['status', 'id']);

            $table->foreign('giver_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('receiver_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('post_id')->references('id')->on('posts')->nullOnDelete();
            $table->foreign('note_hidden_by')->references('id')->on('users')->nullOnDelete();
        });

        // One row per member who has given kudos: the row a give locks, so two at once can't both pass the allowance.
        $schema->create('kudos_givers', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // The most-thanked member of each finished week. Unique per week: the scheduler can run twice, the spotlight is set once.
        $schema->create('kudos_spotlights', function (Blueprint $table) {
            $table->increments('id');
            $table->date('week')->unique();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('count');
            // Which group the spotlight joined them to (only when they weren't in it already), so it can be taken back next week.
            $table->unsignedInteger('group_id')->nullable();
            $table->unsignedInteger('badge_id')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('rewarded_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('kudos_spotlights');
        $schema->dropIfExists('kudos_givers');
        $schema->dropIfExists('kudos');
    },
];
