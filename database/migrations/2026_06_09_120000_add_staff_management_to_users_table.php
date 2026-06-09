<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff management: a deactivation flag (blocks login) and soft deletes.
 *
 * The plain email unique index is replaced with a partial unique index scoped to
 * non-deleted rows, so a soft-deleted account's email can be reused by a new
 * (active) account without colliding with the trashed row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('company_id');
            $table->softDeletes();
        });

        // Belt-and-suspenders backfill: the NOT NULL DEFAULT true above already
        // sets every pre-existing row active on Postgres 11+, but an explicit
        // update guarantees existing staff (e.g. the bootstrap super_admin) stay
        // active regardless of engine/column-nullability quirks. softDeletes()
        // leaves deleted_at null, so no existing user is ever trashed by this run.
        DB::table('users')->update(['is_active' => true]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['email']);
        });

        // Uniqueness only among live (non-trashed) accounts.
        DB::statement('CREATE UNIQUE INDEX users_email_active_unique ON users (email) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX users_email_active_unique');

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('email');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn('is_active');
        });
    }
};
