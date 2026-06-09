<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zoho-owned visibility flag, driven by the "Sync to portal" checkbox
 * (api_name cf_sync_to_portal) on the Items module. Default false — a product
 * stays hidden from the dealer portal until a sync confirms the tick. Written
 * on every sync run from the Zoho value (Zoho wins), unlike is_featured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('sync_to_portal')->default(false)->index()->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('sync_to_portal');
        });
    }
};
