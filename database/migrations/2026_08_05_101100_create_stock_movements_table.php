<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stock as a ledger. "Why does this say four when we have two?" is
        // unanswerable against a bare integer column, and a ledger is the only
        // honest way to reconcile an importer and a human writing the same number.
        // products.stock_quantity is the cached running total, written in the same
        // transaction as the movement so it cannot drift.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->ulidColumn('product_id');
            $table->integer('delta');
            $table->enum('reason', [
                'import', 'manual_adjustment', 'sale', 'cancellation', 'stocktake',
            ]);
            $table->string('reference_type')->nullable();
            $table->ulidColumn('reference_id')->nullable();
            $table->string('note')->nullable();
            $table->ulidColumn('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->index(['product_id', 'created_at'], 'stock_movements_product_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
