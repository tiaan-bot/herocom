<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra company details collected on the credit branch of onboarding so the
 * credit application PDF matches the paper form. Nullable — COD applications
 * leave them empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->date('date_of_registration')->nullable()->after('registration_number');
            $table->string('telephone')->nullable()->after('country_code');
            $table->string('fax')->nullable()->after('telephone');
            $table->string('postal_address_line1')->nullable()->after('fax');
            $table->string('postal_province')->nullable()->after('postal_address_line1');
            $table->string('postal_postal_code')->nullable()->after('postal_province');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'date_of_registration', 'telephone', 'fax',
                'postal_address_line1', 'postal_province', 'postal_postal_code',
            ]);
        });
    }
};
