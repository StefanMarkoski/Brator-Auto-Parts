<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->ulidColumn('parent_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();

            // Materialized ancestor path, e.g. "/braking/discs/". Turns breadcrumbs
            // and "everything beneath this category" into one indexed lookup instead
            // of a recursive walk on every page render.
            $table->string('path', 512)->default('');
            $table->unsignedTinyInteger('depth')->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            // Denormalized cache: the category nav renders on every page and would
            // otherwise COUNT per node. Owned by a listener on product writes.
            $table->unsignedInteger('products_count')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['parent_id', 'position'], 'categories_parent_position_index');
            $table->index('path', 'categories_path_index');
            $table->index(['is_active', 'position'], 'categories_active_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
