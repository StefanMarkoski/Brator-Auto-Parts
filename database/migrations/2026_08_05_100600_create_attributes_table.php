<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The theme's listing page filters on Origins, Diameter, Width,
        // Colour/Finish, Offset, Materials, Ratings, Price and Brands. Everything
        // except the last three is an attribute. These are rows and not columns
        // because every category needs a different set — diameter and offset matter
        // for wheels and are meaningless on an oil filter.
        Schema::create('attributes', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->string('code')->unique();
            $table->string('label');
            $table->enum('type', ['text', 'number', 'boolean', 'option'])->default('option');
            $table->string('unit', 16)->nullable();
            $table->boolean('is_filterable')->default(true);
            // Picks which EXISTING theme control renders this filter. Never new markup.
            $table->enum('filter_widget', ['checkbox', 'range', 'swatch'])->default('checkbox');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['is_filterable', 'position'], 'attributes_filterable_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
