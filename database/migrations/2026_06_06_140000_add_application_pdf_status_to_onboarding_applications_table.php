<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generation state of the system-built application-form PDF (Stream B2) so admins
 * can see "pending/generated/failed" and regenerate from Filament.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_applications', function (Blueprint $table): void {
            $table->string('application_pdf_status')->default('pending')->after('signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_applications', function (Blueprint $table): void {
            $table->dropColumn('application_pdf_status');
        });
    }
};
