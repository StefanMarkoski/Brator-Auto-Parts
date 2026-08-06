<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->ulidPrimary();

            /*
             | Ten characters, stored uppercase, unique.
             |
             | Uppercase in the COLUMN rather than only in the form, because a code gets typed
             | by a customer and read aloud down the phone. Matching is then a plain equality
             | on a unique index instead of a case-insensitive scan.
            */
            $table->string('code', 10)->unique();

            // Whole percent. A "12.5% off" coupon is a rounding argument nobody needs, and
            // the discount lands in minor units anyway.
            $table->unsignedTinyInteger('discount_percent');

            /*
             | The optional threshold. NULL means "applies to any basket"; a value means the
             | net subtotal must reach it first. Nullable rather than 0-means-none, because
             | 0 is a real amount and "no minimum" is the absence of one.
            */
            $table->unsignedInteger('minimum_order_minor')->nullable();

            $table->boolean('is_active')->default(true);

            // How many receipts have used it. Not a limit — just the number staff will want
            // when deciding whether a code is worth keeping.
            $table->unsignedInteger('times_used')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'code'], 'coupons_active_code_index');
        });

        Schema::table('receipts', function (Blueprint $table) {
            /*
             | Snapshotted onto the receipt, like every other figure on it.
             |
             | The coupon's CODE and the discount it gave are recorded here, not looked up
             | later: a receipt has to still read correctly after the coupon is deactivated,
             | its percentage changed, or the row deleted. Same reason the lines snapshot the
             | product name and price.
            */
            $table->unsignedInteger('discount_minor')->default(0)->after('subtotal_minor');
            $table->string('coupon_code', 10)->nullable()->after('discount_minor');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn(['discount_minor', 'coupon_code']);
        });

        Schema::dropIfExists('coupons');
    }
};
