<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basket_lines', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->ulidColumn('basket_id');
            $table->ulidColumn('product_id');
            $table->unsignedSmallInteger('quantity')->default(1);
            // Snapshot at add-time, NET of VAT. Re-validated at placement.
            $table->unsignedInteger('unit_price_minor');
            $table->timestamps();

            $table->foreign('basket_id')->references('id')->on('baskets')->cascadeOnDelete();
            // Restrict, not cascade: deleting a product should not silently empty
            // someone's basket without anyone noticing.
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();

            $table->unique(['basket_id', 'product_id'], 'basket_lines_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basket_lines');
    }
};
