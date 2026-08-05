<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Integer keys throughout Fitment: this is reference data, joined on every
        // filtered search and never linked to publicly.
        Schema::create('vehicle_makes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Also feeds the theme's "Featured Makes" strip, hence logo and position.
            $table->string('logo_path')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'position'], 'vehicle_makes_active_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_makes');
    }
};
