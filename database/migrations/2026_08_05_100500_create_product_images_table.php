<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->ulidColumn('product_id');
            // Files live on disk or S3, never in the database.
            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->index(['product_id', 'position'], 'product_images_product_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
