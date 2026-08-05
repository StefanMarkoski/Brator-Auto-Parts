<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_collection_items', function (Blueprint $table) {
            $table->id();
            $table->ulidColumn('product_collection_id');
            $table->ulidColumn('product_id');
            $table->unsignedSmallInteger('position')->default(0);

            $table->foreign('product_collection_id')->references('id')->on('product_collections')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

            $table->unique(['product_collection_id', 'product_id'], 'pci_unique');
            $table->index(['product_collection_id', 'position'], 'pci_collection_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_collection_items');
    }
};
