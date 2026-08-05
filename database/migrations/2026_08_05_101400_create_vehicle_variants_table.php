<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The level a shopper actually picks: "2.0 TDI 2015-2019", not just "Passat".
        // This is the theme's "Sub Model" dropdown, with Engine and Year alongside.
        Schema::create('vehicle_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('vehicle_models')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('year_from');
            // Null means still in production.
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->string('engine_code', 32)->nullable();
            $table->enum('fuel_type', ['petrol', 'diesel', 'hybrid', 'electric', 'lpg'])
                ->default('petrol');
            $table->unsignedSmallInteger('power_kw')->nullable();
            $table->unsignedSmallInteger('engine_cc')->nullable();
            $table->string('body_type', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Drives the cascading Year -> Make -> Model -> Sub Model -> Engine picker.
            $table->index(['model_id', 'year_from', 'year_to'], 'vehicle_variants_model_years_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_variants');
    }
};
