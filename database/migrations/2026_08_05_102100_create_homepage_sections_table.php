<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stefan's requirement: the homepage must be dynamic.
        //
        // Staff control which sections appear, in what order, their headings, and
        // which data feeds each one. They cannot invent a NEW KIND of section: every
        // section_type maps to a Blade partial cut from the theme's existing markup,
        // and a type the theme has no markup for would mean writing new markup, which
        // is the styling change that is forbidden. So this is a configurable homepage,
        // not a page builder — the correct reading of the constraint.
        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->enum('section_type', [
                'hero_banner',
                'categories_strip',
                'whats_hot',
                'featured_makes',
                'best_sellers',
                'essential_items',
                'new_arrivals',
                'articles',
                'featured_brands',
                'newsletter',
            ]);
            $table->string('heading')->nullable();
            $table->string('subheading')->nullable();
            $table->ulidColumn('product_collection_id')->nullable();

            // The one place json is correct rather than lazy: per-type options are
            // genuinely heterogeneous (a strip has a row count, a banner an autoplay
            // delay) and are only ever read whole when rendering, never filtered on.
            // Anything filterable stays a real column.
            $table->json('settings')->nullable();

            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->foreign('product_collection_id')->references('id')->on('product_collections')->nullOnDelete();
            $table->index(['is_visible', 'position'], 'homepage_sections_visible_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_sections');
    }
};
