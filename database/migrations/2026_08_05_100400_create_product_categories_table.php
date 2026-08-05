<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A pivot, not a single category_id on products: a brake disc legitimately
        // belongs under both "Braking" and "Wheels & Hubs", and a shop that cannot
        // do that loses sales to its own navigation. is_primary gives the canonical
        // breadcrumb and URL — exactly one per product, asserted in a test.
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->ulidColumn('product_id');
            $table->ulidColumn('category_id');
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('position')->default(0);

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();

            $table->unique(['product_id', 'category_id'], 'product_categories_unique');
            // Category-first: the listing page asks "which products are in this
            // category", never the reverse, so this is the order that range-scans.
            $table->index(['category_id', 'product_id'], 'product_categories_category_product_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
