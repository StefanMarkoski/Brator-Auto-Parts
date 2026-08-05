<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Without this table every listing page shows every filter in the system,
        // and someone browsing oil filters gets asked about wheel offset. It also
        // makes the filter sidebar a single indexed lookup instead of "scan the
        // products in this category to discover which attributes they use" — which
        // is the listing query all over again, doubled.
        Schema::create('category_attributes', function (Blueprint $table) {
            $table->id();
            $table->ulidColumn('category_id');
            $table->ulidColumn('attribute_id');
            $table->unsignedSmallInteger('position')->default(0);

            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('attribute_id')->references('id')->on('attributes')->cascadeOnDelete();
            $table->unique(['category_id', 'attribute_id'], 'category_attributes_unique');
            $table->index(['category_id', 'position'], 'category_attributes_category_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_attributes');
    }
};
