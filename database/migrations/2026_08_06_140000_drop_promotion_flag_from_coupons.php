<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | show_as_promotion was a second switch beside is_active, and it turned out to be a
     | distinction without a difference: staff switch a code on when they want it used, and a
     | code they want used is a code they want seen. Two switches meant every code needed two
     | clicks to do the obvious thing, and a code could sit live-but-hidden by accident.
     |
     | is_active now means both: usable AND advertised in the shop's top bar. The bar lists
     | every live code, one line each.
    */
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex('coupons_promoted_index');
            $table->dropColumn('show_as_promotion');
        });

        Schema::table('coupons', function (Blueprint $table) {
            // The bar's read is now "every active code, newest first".
            $table->index(['is_active', 'created_at'], 'coupons_advertised_index');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex('coupons_advertised_index');
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('show_as_promotion')->default(false)->after('is_active');
            $table->index(['show_as_promotion', 'is_active', 'created_at'], 'coupons_promoted_index');
        });
    }
};
