<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rendered from seeded data for the MVP; submission comes later. The table is
        // required now because the theme's product page ships the review block, and
        // hiding markup would itself be a styling change.
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->ulidColumn('product_id');
            $table->string('author_name');
            $table->string('author_email');
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body');
            $table->boolean('is_approved')->default(false);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->index(['product_id', 'is_approved', 'created_at'], 'product_reviews_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
