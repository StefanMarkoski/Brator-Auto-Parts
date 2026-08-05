<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The most common real search on a parts site is pasting the number off the
        // old part. number_normalized strips spaces, dashes and case so that
        // "A 000 989 82 01" and "a000989820 1" both land on the same product.
        Schema::create('product_cross_references', function (Blueprint $table) {
            $table->id();
            $table->ulidColumn('product_id');
            $table->string('number');
            $table->string('number_normalized', 64);
            $table->enum('type', ['oem', 'manufacturer', 'competitor', 'ean'])->default('oem');
            $table->string('brand_hint')->nullable();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->index('number_normalized', 'pcr_number_normalized_index');
            $table->index('product_id', 'pcr_product_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_cross_references');
    }
};
