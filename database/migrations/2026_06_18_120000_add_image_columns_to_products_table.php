<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-item product images mirrored one-way from Zoho Books. We store the bytes
 * on a private R2 disk and serve them through a gated route — so the old
 * `image_url` column (Zoho's authenticated hosted URL, never usable directly)
 * is dropped in favour of a stored path. `image_document_id` is the
 * change-detection key: unchanged => no re-download (stays within Zoho's cap).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('image_url');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('image_document_id')->nullable()->after('category');
            $table->string('image_path')->nullable()->after('image_document_id');
            $table->string('image_mime')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['image_document_id', 'image_path', 'image_mime']);
            $table->string('image_url')->nullable()->after('category');
        });
    }
};
