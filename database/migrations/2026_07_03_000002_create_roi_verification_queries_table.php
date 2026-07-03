<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-consumer tracking / audit log (ADR-002 D3, AID-310).
 *
 * `roi_verification_queries` is the append-only store of record for
 * "who verified what, when". Unlike the `roi_vat_verifications` cache
 * (one current row per NIF), this table is never updated: each recorded
 * verification is a new row, and expiry is a physical prune (D5), so there
 * are no soft-deletes.
 *
 * The composite indexes match the actual consumer-scoped read predicates
 * (D3 / Codex P2): every read filters on `consumer` first, so there is no
 * plain single-column index a consumer-scoped query would ignore. The lone
 * `retention_until` index backs the prune scan.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roi_verification_queries', function (Blueprint $table) {
            $table->id();
            $table->string('consumer');
            $table->string('subject_reference')->nullable();
            $table->string('vat_code', 50);
            $table->string('country_code', 2);
            $table->boolean('is_valid');
            $table->string('api_source', 50);
            $table->boolean('cache_hit');
            $table->json('response_snapshot')->nullable();
            $table->timestamp('queried_at');
            $table->timestamp('retention_until')->nullable();
            $table->timestamps();

            // Consumer-scoped read predicates (D3). Order matters: the leading
            // `consumer` column serves every read; the remaining columns narrow it.
            $table->index(['consumer', 'subject_reference']);
            $table->index(['consumer', 'vat_code', 'country_code']);
            $table->index(['consumer', 'queried_at']);

            // Prune scan (D5): deletes rows whose retention_until has passed.
            $table->index(['retention_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roi_verification_queries');
    }
};
