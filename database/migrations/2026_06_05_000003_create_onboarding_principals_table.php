<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_principals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_application_id')->constrained('onboarding_applications')->cascadeOnDelete();

            $table->string('full_name');
            $table->string('surname');

            // SA ID — sensitive PII, encrypted at the model layer (hence text, not string).
            $table->text('id_number');

            $table->decimal('shareholding_percent', 5, 2)->nullable();

            // Residential address — PII; private + audited.
            $table->string('residential_address_line1')->nullable();
            $table->string('residential_address_line2')->nullable();
            $table->string('residential_city')->nullable();
            $table->string('residential_province')->nullable();
            $table->string('residential_postal_code')->nullable();
            $table->char('country_code', 2)->default('ZA');

            $table->boolean('is_surety')->default(true);
            $table->boolean('married_in_community')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_principals');
    }
};
