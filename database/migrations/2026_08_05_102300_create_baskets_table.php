<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baskets', function (Blueprint $table) {
            $table->ulidPrimary();
            // Guests only — there are no customer accounts on this shop.
            $table->string('session_token', 64)->unique();
            // Remembers the shopper's selected vehicle alongside their basket.
            $table->foreignId('vehicle_variant_id')->nullable()
                ->constrained('vehicle_variants')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('expires_at', 'baskets_expires_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baskets');
    }
};
