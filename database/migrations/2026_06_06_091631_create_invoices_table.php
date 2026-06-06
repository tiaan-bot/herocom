<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-way mirror of Zoho Books invoices (Zoho owns/creates them). Upsert by the
 * unique zoho_invoice_id; money fields are VAT-inclusive financials as Zoho reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->string('zoho_invoice_id')->unique();
            $table->string('zoho_customer_id')->index();
            $table->string('invoice_number')->index();
            $table->string('status')->default('unknown')->index();

            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            $table->decimal('subtotal_ex_vat', 15, 4)->default(0);
            $table->decimal('tax_total', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->decimal('balance', 15, 4)->default(0);
            $table->char('currency', 3)->default('ZAR');

            $table->string('payment_url')->nullable();

            $table->timestamp('zoho_last_modified_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
