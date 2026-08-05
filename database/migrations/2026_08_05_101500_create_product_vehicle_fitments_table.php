<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The largest table in the database: 50k parts x ~200 compatible variants is
        // ~10 million rows whose only job is to join two things.
        //
        // It has NO surrogate key. The primary key is (vehicle_variant_id, product_id),
        // vehicle first, and that ordering is the whole game: InnoDB stores a table in
        // primary-key order, so every part fitting one vehicle sits physically together
        // and "show me parts for my car" becomes a contiguous range scan over the table
        // itself — no secondary index, no per-row lookup. Ordered product-first, the
        // identical columns and identical storage degrade into a scan.
        //
        // Dropping the surrogate key also removes 8 bytes per row and one entire index
        // from the biggest table we have.
        Schema::create('product_vehicle_fitments', function (Blueprint $table) {
            $table->foreignId('vehicle_variant_id')->constrained('vehicle_variants')->cascadeOnDelete();
            $table->ulidColumn('product_id');

            // NOT redundant with the variant's own years: a part often fits a variant
            // for only part of its production run, because a facelift changed a
            // bracket. Without these you tell a customer a part fits when it does not,
            // and eat the return and the review.
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->string('note')->nullable();

            $table->primary(['vehicle_variant_id', 'product_id'], 'pvf_primary');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

            // "Which cars does this part fit", for the product page.
            $table->index('product_id', 'pvf_product_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_vehicle_fitments');
    }
};
