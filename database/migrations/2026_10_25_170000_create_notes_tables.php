<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index('parent_id');
        });

        // Self-referencing keys can only be added once the primary key exists.
        Schema::table('nodes', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('nodes')->cascadeOnDelete();
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('content')->default('');
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('node_topic', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('node_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('topic_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_link')->default(false);
            $table->timestamps();

            $table->unique(['node_id', 'topic_id']);
            $table->index('topic_id');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('topic_id')->constrained()->cascadeOnDelete();
            $table->string('value');

            $table->unique(['topic_id', 'value']);
            $table->index('value');
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('topic_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('node_topic');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('nodes');
    }
};
