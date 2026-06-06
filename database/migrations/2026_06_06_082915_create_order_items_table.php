<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();

            // Snapshots — product may change after placement.
            $table->string('sku')->nullable();
            $table->string('name');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price_list', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->char('currency', 3)->default('ZAR');
            $table->decimal('line_total_ex_vat', 15, 4);
            $table->string('zoho_item_id');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
