<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            /*
             | Whether this code is advertised in the storefront's top bar.
             |
             | Separate from is_active on purpose. A code can be live and usable without being
             | shouted about — a phone-only discount for a customer who complained, say — and
             | conflating the two would mean every code you create appears on the homepage.
            */
            $table->boolean('show_as_promotion')->default(false)->after('is_active');

            // The bar shows one promoted code, and picks the newest. Indexed on the exact
            // shape of that read.
            $table->index(['show_as_promotion', 'is_active', 'created_at'], 'coupons_promoted_index');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex('coupons_promoted_index');
            $table->dropColumn('show_as_promotion');
        });
    }
};
