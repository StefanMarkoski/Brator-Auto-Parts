<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            // Drives the theme's "Featured Brands" strip: active brands in order.
            $table->index(['is_active', 'position'], 'brands_active_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
