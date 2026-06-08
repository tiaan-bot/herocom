<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The credit application's account contact person, distinct from the main
 * applicant/owner. Nullable — only collected on the credit branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_applications', function (Blueprint $table): void {
            $table->string('account_contact_name')->nullable()->after('contact_phone');
            $table->string('account_contact_email')->nullable()->after('account_contact_name');
            $table->string('account_contact_phone')->nullable()->after('account_contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_applications', function (Blueprint $table): void {
            $table->dropColumn(['account_contact_name', 'account_contact_email', 'account_contact_phone']);
        });
    }
};
