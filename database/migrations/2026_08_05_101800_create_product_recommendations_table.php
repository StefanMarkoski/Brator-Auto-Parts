<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Powers both "Frequently Bought Together" and "You May Also Like", which the
        // theme's product page already ships as designed markup.
        //
        // Why `source` exists: bought_together is computed from co-occurrence in
        // receipt_lines, but that needs purchase history and this shop has a
        // deliberately fake checkout. On day one the computed rows are empty and the
        // block renders blank — the feature would look broken at launch. So staff pin
        // pairs manually, manual always outranks computed, and the seeder ships
        // plausible pairs. The scheduled job takes over as real receipts accumulate.
        Schema::create('product_recommendations', function (Blueprint $table) {
            $table->id();
            $table->ulidColumn('product_id');
            $table->ulidColumn('related_product_id');
            $table->enum('type', ['bought_together', 'similar'])->default('bought_together');
            $table->enum('source', ['computed', 'manual'])->default('manual');
            $table->unsignedInteger('score')->nullable();
            $table->unsignedSmallInteger('position')->default(0);

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('related_product_id')->references('id')->on('products')->cascadeOnDelete();

            $table->unique(['product_id', 'related_product_id', 'type'], 'prec_unique');
            $table->index(['product_id', 'type', 'source', 'position'], 'prec_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_recommendations');
    }
};
