<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            // The branch driver: cod | credit
            $table->string('account_type_requested')->index();
            $table->string('status')->default('submitted')->index();

            // Applicant — becomes the first reseller_owner on approval.
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone');

            // Premises (both forms)
            $table->boolean('premises_owned')->nullable();
            $table->string('landlord_name')->nullable();
            $table->string('landlord_address')->nullable();
            $table->string('landlord_tel')->nullable();
            $table->string('period_at_address')->nullable();

            // Credit branch only
            $table->decimal('credit_limit_requested', 15, 4)->nullable();
            $table->char('credit_limit_requested_currency', 3)->default('ZAR');
            $table->unsignedSmallInteger('credit_terms_requested_days')->nullable();
            $table->string('annual_turnover_band')->nullable();

            // CGIC submission packet — encrypted at the model layer, hence text. Submit-only, not queryable.
            $table->text('cgic_payload')->nullable();
            $table->string('cgic_status')->default('not_required');
            $table->string('cgic_reference')->nullable();
            $table->text('cgic_outcome_notes')->nullable();
            $table->timestamp('cgic_decided_at')->nullable();
            $table->foreignId('cgic_decided_by')->nullable()->constrained('users')->nullOnDelete();

            // Consent — immutable once set (enforced in the domain layer).
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version')->nullable();
            $table->timestamp('popia_consent_at')->nullable();
            $table->timestamp('credit_enquiry_consent_at')->nullable();

            $table->timestamp('submitted_at')->nullable();

            // Review trail
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_applications');
    }
};
