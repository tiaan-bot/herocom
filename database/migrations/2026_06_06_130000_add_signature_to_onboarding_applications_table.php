<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-form declaration & signature capture (Stream B1). The drawn signature PNG
 * lives on the private onboarding documents disk; only the path is stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_applications', function (Blueprint $table): void {
            $table->string('signed_by_name')->nullable()->after('decision_notes');
            $table->string('signed_by_capacity')->nullable()->after('signed_by_name');
            $table->timestamp('signed_at')->nullable()->after('signed_by_capacity');
            $table->string('signature_path')->nullable()->after('signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_applications', function (Blueprint $table): void {
            $table->dropColumn(['signed_by_name', 'signed_by_capacity', 'signed_at', 'signature_path']);
        });
    }
};
