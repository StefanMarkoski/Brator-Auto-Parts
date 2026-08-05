<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The snapshot columns are the POINT of a receipt, not redundancy. A receipt
        // records what was bought at a moment in time. If it reads live product data,
        // then renaming a part or fixing a price silently rewrites history and every
        // old receipt quietly becomes a lie. This is the most common way a shop's
        // records rot, and four snapshot columns prevent it permanently.
        //
        // This is the one place in the schema where duplication is correct, because a
        // receipt is a historical document and not a view of current state.
        Schema::create('receipt_lines', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->ulidColumn('receipt_id');
            $table->ulidColumn('product_id')->nullable();

            $table->string('product_name_snapshot');
            $table->string('product_sku_snapshot');
            $table->string('brand_name_snapshot')->nullable();

            $table->unsignedInteger('unit_price_minor');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('line_total_minor');

            // Snapshotted because rates change by law and an old receipt must keep
            // the rate it was actually charged at. Per line rather than per receipt so
            // a future reduced-rate band needs no migration.
            $table->decimal('vat_rate', 5, 2);
            $table->unsignedInteger('vat_minor');

            $table->timestamps();

            $table->foreign('receipt_id')->references('id')->on('receipts')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();

            $table->index('receipt_id', 'receipt_lines_receipt_index');
            // Feeds the bought-together co-occurrence job.
            $table->index('product_id', 'receipt_lines_product_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_lines');
    }
};
