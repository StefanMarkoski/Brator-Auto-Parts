<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_sources', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->string('name');
            $table->enum('type', ['csv', 'xml', 'api'])->default('csv');
            // Encrypted at the model layer — credentials live in here.
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_runs', function (Blueprint $table) {
            $table->ulidPrimary();
            $table->ulidColumn('source_id');
            $table->enum('status', ['queued', 'running', 'completed', 'failed'])->default('queued');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_created')->default(0);
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->string('log_path')->nullable();
            $table->timestamps();

            $table->foreign('source_id')->references('id')->on('import_sources')->cascadeOnDelete();
            $table->index(['source_id', 'created_at'], 'import_runs_source_created_index');
        });

        // A scratchpad, not history — safe to truncate.
        Schema::create('import_staging_rows', function (Blueprint $table) {
            $table->id();
            $table->ulidColumn('import_run_id');
            $table->string('external_id')->nullable();
            $table->json('payload');
            $table->enum('outcome', ['pending', 'created', 'updated', 'skipped', 'failed'])
                ->default('pending');
            $table->text('error')->nullable();
            $table->ulidColumn('product_id')->nullable();

            $table->foreign('import_run_id')->references('id')->on('import_runs')->cascadeOnDelete();
            $table->index(['import_run_id', 'outcome'], 'isr_run_outcome_index');
        });

        // How a re-import updates the right product instead of creating duplicates.
        Schema::create('product_external_refs', function (Blueprint $table) {
            $table->id();
            $table->ulidColumn('product_id');
            $table->ulidColumn('source_id');
            $table->string('external_id');

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('source_id')->references('id')->on('import_sources')->cascadeOnDelete();
            $table->unique(['source_id', 'external_id'], 'per_source_external_unique');
            $table->index('product_id', 'per_product_index');
        });

        // The entire answer to "import plus manual overrides": staff edit a field by
        // hand, we record that they own it, and the importer refuses to touch anything
        // listed here. One rule, one place. The alternative — an is_manual boolean
        // beside every column — spreads the same rule across dozens of columns and
        // will be forgotten on the next column added.
        Schema::create('product_field_overrides', function (Blueprint $table) {
            $table->id();
            $table->ulidColumn('product_id');
            $table->string('field_name', 64);
            $table->ulidColumn('overridden_by')->nullable();
            $table->timestamp('overridden_at');

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('overridden_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['product_id', 'field_name'], 'pfo_product_field_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_field_overrides');
        Schema::dropIfExists('product_external_refs');
        Schema::dropIfExists('import_staging_rows');
        Schema::dropIfExists('import_runs');
        Schema::dropIfExists('import_sources');
    }
};
