<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema ownership (ADR-002 D1/D2, AID-312).
 *
 * lararoi owns its tables under an unambiguous `roi_` prefix so no consumer
 * collides by picking a natural generic name. This migration renames the cache
 * table, enforces the "one current row per NIF" invariant with a UNIQUE index
 * (backstopping the atomic upsert in VatVerificationService::persistVerification),
 * and drops columns that were created but never used by the model.
 *
 * It is a NEW migration, not an edit of the historical create migration: editing
 * the create migration would not re-run on an already-migrated database, leaving
 * the model pointing at a table that does not exist.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Rename the table. In SQLite the existing indexes keep their original
        //    `vat_verifications_*` names after the rename.
        Schema::rename('vat_verifications', 'roi_vat_verifications');

        // 2. Drop the unused columns first. On SQLite a column drop can rebuild the
        //    table; doing it before the index swap means the rebuild only recreates
        //    the pre-existing indexes and can never lose the new UNIQUE one.
        //    - checked_at / expires_at: never exposed by the model; expiry is
        //      derived from verified_at + cache.ttl.
        //    - deleted_at: SoftDeletes is removed (a soft-deleted row would keep
        //      occupying the UNIQUE slot below).
        Schema::table('roi_vat_verifications', function (Blueprint $table) {
            $table->dropColumn(['checked_at', 'expires_at', 'deleted_at']);
        });

        // 3. Swap the plain composite index for a UNIQUE one enforcing a single
        //    current row per (vat_code, country_code). The historical index was
        //    named by Laravel convention on the old table name; drop it explicitly.
        Schema::table('roi_vat_verifications', function (Blueprint $table) {
            $table->dropIndex('vat_verifications_vat_code_country_code_index');
            $table->unique(['vat_code', 'country_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the index swap: drop the UNIQUE index and restore the plain one
        // under its original name.
        Schema::table('roi_vat_verifications', function (Blueprint $table) {
            $table->dropUnique(['vat_code', 'country_code']);
            $table->index(['vat_code', 'country_code'], 'vat_verifications_vat_code_country_code_index');
        });

        // Re-add the dropped columns.
        Schema::table('roi_vat_verifications', function (Blueprint $table) {
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->softDeletes();
        });

        // Rename back to the historical name.
        Schema::rename('roi_vat_verifications', 'vat_verifications');
    }
};
