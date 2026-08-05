<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->ulidPrimary();
            // Human-readable and sequential: BR-2026-000123.
            $table->string('receipt_number', 32)->unique();

            // Reserved hook: unused while the shop is guest-only, and the single
            // column that lets real customer accounts arrive later without reshaping
            // this table.
            $table->ulidColumn('customer_id')->nullable();

            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->text('shipping_address');
            $table->text('billing_address')->nullable();

            // All NET except vat_minor and total_minor. Always computed server-side
            // from stored prices, never read from the submitted form — fake payment
            // or not, the arithmetic is the part that becomes real.
            $table->unsignedInteger('subtotal_minor');
            $table->unsignedInteger('vat_minor');
            $table->unsignedInteger('shipping_minor')->default(0);
            $table->unsignedInteger('total_minor');

            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'placed_at'], 'receipts_status_placed_index');
            $table->index('customer_email', 'receipts_customer_email_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
