<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->ulidColumn('brand_id')->nullable();

            // NET of VAT. VAT is added at checkout (schema plan, "VAT" section).
            $table->unsignedInteger('price_minor');
            $table->unsignedInteger('sale_price_minor')->nullable();

            // Running total of stock_movements, cached here because summing the
            // ledger for every row of every listing page would be ruinous.
            $table->integer('stock_quantity')->default(0);
            $table->enum('stock_status', ['in_stock', 'out_of_stock', 'on_backorder'])
                ->default('in_stock');
            $table->enum('condition', ['new', 'refurbished', 'used'])->default('new');
            $table->unsignedInteger('weight_grams')->nullable();

            // Caches: the listing page shows stars on every card, and averaging
            // per card is exactly the read that kills a catalogue page.
            $table->decimal('rating_avg', 2, 1)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();

            // Kept on the hot row deliberately (see schema plan §3): InnoDB stores
            // longText off-page anyway, so a 1:1 split would buy little and cost a
            // permanent join. Listing queries must select explicit columns instead.
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();

            // Index shapes match the real listing queries (schema plan §9).
            $table->index(['is_active', 'brand_id'], 'products_active_brand_index');
            $table->index(['is_active', 'price_minor'], 'products_active_price_index');
            $table->index(['is_active', 'rating_avg'], 'products_active_rating_index');
            $table->index(['is_active', 'published_at'], 'products_active_published_index');
        });

        // Fulltext for the theme's search box. Added separately because Laravel's
        // fluent builder and MySQL fulltext disagree less when it is explicit.
        DB::statement('ALTER TABLE products ADD FULLTEXT products_name_sku_fulltext (name, sku)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
