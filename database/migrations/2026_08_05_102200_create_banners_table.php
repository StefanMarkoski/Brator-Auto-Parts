<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->enum('placement', ['home_hero', 'home_secondary'])->default('home_hero');
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('image_path');
            $table->string('mobile_image_path')->nullable();
            $table->string('link_url')->nullable();
            $table->string('link_label')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            // Lets a promotion be scheduled rather than remembered — the difference
            // between a campaign and someone's calendar reminder.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['placement', 'is_active', 'position'], 'banners_placement_active_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
