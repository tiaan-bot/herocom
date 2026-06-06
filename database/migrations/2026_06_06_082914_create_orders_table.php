<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal-side order record that produces a Zoho Sales Order. Prices/address are
 * snapshotted at placement and immutable. No soft deletes — orders are permanent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('order_number')->nullable()->unique(); // set immediately post-insert: HD-000123
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('placed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('placed')->index();

            $table->decimal('subtotal_ex_vat', 15, 4)->default(0);
            $table->char('currency', 3)->default('ZAR');
            $table->decimal('discount_percent_applied', 5, 2)->default(0);

            // Delivery address snapshot (default from company).
            $table->string('delivery_address_line1');
            $table->string('delivery_address_line2')->nullable();
            $table->string('delivery_city');
            $table->string('delivery_province');
            $table->string('delivery_postal_code');
            $table->char('delivery_country_code', 2)->default('ZA');
            $table->text('customer_note')->nullable();

            // Zoho push state — separate from order status.
            $table->string('zoho_salesorder_id')->nullable()->unique();
            $table->string('zoho_push_status')->default('pending')->index();
            $table->text('zoho_push_error')->nullable();
            $table->timestamp('zoho_pushed_at')->nullable();

            // Decision trail.
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
