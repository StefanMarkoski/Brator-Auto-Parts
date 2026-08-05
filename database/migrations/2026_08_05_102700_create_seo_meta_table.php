<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Polymorphic: products, categories, posts and pages all need the same few SEO
        // fields. As columns that is ~20 columns spread over four tables, changed in
        // four migrations every time SEO wants another field. One table, one migration
        // — and it keeps the products hot row narrow, which is the whole objective.
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->string('seoable_type');
            $table->ulidColumn('seoable_id');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('og_image_path')->nullable();
            $table->boolean('noindex')->default(false);
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id'], 'seo_meta_seoable_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
