<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('legal_name');
            $table->string('trading_name')->nullable();
            $table->string('entity_type');
            $table->string('registration_number')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('nature_of_business')->nullable();

            $table->string('status')->default('pending')->index();

            // Credit / pricing
            $table->string('credit_terms')->default('eft_upfront');
            $table->decimal('credit_limit', 15, 4)->nullable();
            $table->char('credit_limit_currency', 3)->default('ZAR');
            $table->unsignedSmallInteger('credit_terms_days')->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);

            // Registered / billing address
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('province');
            $table->string('postal_code');
            $table->char('country_code', 2)->default('ZA');
            $table->char('currency', 3)->default('ZAR');

            // Zoho idempotency anchor — null until approved + pushed.
            $table->string('zoho_customer_id')->nullable()->unique();

            // Decision trail
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('suspension_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
