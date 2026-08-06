<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            /*
             | Where a hero image came from.
             |
             | Staff add hero images by pasting a URL, and the file is then fetched and stored
             | on our own disk rather than hot-linked. Keeping the source means the row can
             | still answer "where did this picture come from" months later, which matters when
             | somebody asks whether we are allowed to use it.
             |
             | 1024 rather than 255: Google's image URLs carry a base64-ish token and run well
             | past 255 characters, and a silently truncated URL is worse than none.
            */
            $table->string('source_url', 1024)->nullable()->after('image_path');

            /*
             | The image's real pixel size, recorded once at import.
             |
             | The hero is a full-width background, so a 561-wide picture will be upscaled and
             | look soft no matter what we do. Storing the dimensions lets the admin SHOW that
             | before it is a surprise on the homepage, instead of everyone guessing. Reading
             | them on every page render would mean opening the file every request.
            */
            $table->unsignedSmallInteger('image_width')->nullable()->after('source_url');
            $table->unsignedSmallInteger('image_height')->nullable()->after('image_width');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['source_url', 'image_width', 'image_height']);
        });
    }
};
