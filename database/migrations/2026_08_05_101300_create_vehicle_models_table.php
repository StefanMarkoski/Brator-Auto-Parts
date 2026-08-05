<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('make_id')->constrained('vehicle_makes')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['make_id', 'slug'], 'vehicle_models_make_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_models');
    }
};
