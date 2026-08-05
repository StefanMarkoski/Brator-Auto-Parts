<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One mechanism instead of is_hot / is_essential / is_new / is_bestseller
        // columns creeping onto products — that is how a products table reaches sixty
        // columns. "New arrivals" is a rule (published_at desc); "what's hot" is a
        // staff choice; "best sellers" is a rule today that staff will want to
        // override the first time a supplier pays for placement. `type` switches a
        // strip between the two with no migration, and a fifth strip is a seeder row.
        Schema::create('product_collections', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->enum('type', ['manual', 'automatic'])->default('manual');
            $table->json('rule')->nullable();
            $table->unsignedSmallInteger('limit')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_collections');
    }
};
