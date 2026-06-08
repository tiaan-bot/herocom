<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trade references supplied on the credit application (1–3), repeatable like
 * principals. Tied to the application; removed with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_trade_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('onboarding_application_id')->constrained('onboarding_applications')->cascadeOnDelete();
            $table->string('company_name');
            $table->decimal('credit_limit', 15, 4)->nullable();
            $table->char('credit_limit_currency', 3)->default('ZAR');
            $table->string('account_held'); // cod | credit
            $table->unsignedSmallInteger('terms_days')->nullable(); // 7 | 15 | 30, when account_held = credit
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_trade_references');
    }
};
