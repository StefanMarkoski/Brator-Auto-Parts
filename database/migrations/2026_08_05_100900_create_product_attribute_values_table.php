<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->ulidColumn('product_id');
            $table->ulidColumn('attribute_id');
            $table->string('value_string')->nullable();
            $table->decimal('value_number', 12, 3)->nullable();
            $table->ulidColumn('attribute_option_id')->nullable();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('attribute_id')->references('id')->on('attributes')->cascadeOnDelete();
            $table->foreign('attribute_option_id')->references('id')->on('attribute_options')->cascadeOnDelete();

            $table->unique(['product_id', 'attribute_id'], 'product_attribute_values_unique');

            // The two indexes that decide listing-page speed. Both are COVERING for
            // their filter: the query resolves entirely inside the index without
            // touching the table. Designed in from the first migration rather than
            // added after someone complains the shop is slow.
            $table->index(
                ['attribute_id', 'attribute_option_id', 'product_id'],
                'pav_attribute_option_product_index'
            );
            $table->index(
                ['attribute_id', 'value_number', 'product_id'],
                'pav_attribute_number_product_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
    }
};
