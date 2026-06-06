<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Read-optimised one-way mirror of Zoho Books items. Zoho is the source of
 * truth — we upsert by the unique zoho_item_id and never write back. No soft
 * deletes: items removed in Zoho are marked inactive, not deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('zoho_item_id')->unique();
            $table->string('sku')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('rate', 15, 4);
            $table->char('rate_currency', 3)->default('ZAR');
            $table->decimal('stock_on_hand', 12, 2)->default(0);
            $table->string('unit')->nullable();
            $table->string('brand')->nullable()->index();
            $table->string('category')->nullable()->index();
            $table->string('image_url')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamp('zoho_last_modified_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
