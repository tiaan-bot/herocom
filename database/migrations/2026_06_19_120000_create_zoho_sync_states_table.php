<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit per-entity incremental sync cursor. Decoupled from the mirrored rows
 * so a partially-completed run (timeout / mid-pagination error) never advances
 * the "changed since" watermark past items it failed to process. Stored as a
 * true UTC instant; advanced only after a changed set is fully paginated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoho_sync_states', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->timestamp('last_modified_cursor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_sync_states');
    }
};
