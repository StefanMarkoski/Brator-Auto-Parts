<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Content is deliberately the thin context: posts, pages, contact submissions.
     * Grouped in one migration because they are one logical change — standing up the
     * Content context — and none of them has interesting structure.
     *
     * posts stays in the MVP even though the blog PAGES are out of scope: it feeds the
     * product page's "Guide & Blog" block and the homepage's "Articles & Reviews"
     * strip, both of which the theme already ships.
     */
    public function up(): void
    {
        Schema::create('post_categories', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body');
            $table->string('cover_path')->nullable();
            $table->ulidColumn('author_id')->nullable();
            $table->ulidColumn('post_category_id')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('post_category_id')->references('id')->on('post_categories')->nullOnDelete();
            $table->index(['is_published', 'published_at'], 'posts_published_index');
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body');
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('contact_submissions', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index('handled_at', 'contact_submissions_handled_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_submissions');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('post_categories');
    }
};
