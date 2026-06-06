<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('open')->index();
            $table->timestamps();
        });

        // At most one OPEN cart per user (partial unique index; pg + sqlite support WHERE).
        DB::statement("CREATE UNIQUE INDEX carts_one_open_per_user ON carts (user_id) WHERE status = 'open'");
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
