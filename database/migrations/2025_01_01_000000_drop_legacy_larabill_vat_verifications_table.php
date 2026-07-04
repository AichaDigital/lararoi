<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade preflight for consumers coming from larabill 3.x (AID-324).
 *
 * larabill <= 3.x created its own `vat_verifications` table (migration
 * 2024_12_01_000007) with a schema that diverges from the one this package
 * creates and later renames to `roi_vat_verifications`: a UNIQUE composite
 * index where lararoi expects the plain `..._vat_code_country_code_index`
 * it drops by name, `company_address` as string instead of text, and so on.
 * Letting the legacy table through a naive hasTable guard would crash the
 * rename migration halfway and leave a drifted schema behind.
 *
 * The legacy table is a disposable VIES cache (TTL-bound), so when the
 * migrations ledger PROVES the table is larabill's, it is dropped together
 * with its orphaned ledger row (larabill v4 removed the migration file) and
 * lararoi creates its canonical schema fresh. Without that proof the
 * migration aborts loudly instead of touching a table it cannot claim.
 * Recovery steps live in larabill's packaged UPGRADE-4.0.md.
 *
 * The filename sorts immediately before
 * 2025_01_01_000001_create_vat_verifications_table so the migrator always
 * runs this preflight first on a fresh adoption.
 */
return new class extends Migration
{
    private const LEGACY_LARABILL_MIGRATION = '2024_12_01_000007_create_vat_verifications_table';

    private const LARAROI_CREATE_MIGRATION = '2025_01_01_000001_create_vat_verifications_table';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('vat_verifications')) {
            return;
        }

        $ledger = $this->migrationsTable();

        // Mid-upgrade lararoi state (create ran on an earlier version, rename
        // pending): the table is lararoi's own — leave it for the rename.
        if (DB::table($ledger)->where('migration', self::LARAROI_CREATE_MIGRATION)->exists()) {
            return;
        }

        if (DB::table($ledger)->where('migration', self::LEGACY_LARABILL_MIGRATION)->exists()) {
            Schema::drop('vat_verifications');
            DB::table($ledger)->where('migration', self::LEGACY_LARABILL_MIGRATION)->delete();

            return;
        }

        throw new RuntimeException(
            'lararoi: a `vat_verifications` table already exists but the migrations ledger does not prove it is '
            .'larabill 3.x\'s legacy VIES cache, so it will not be dropped automatically. If the table belongs to '
            .'another part of your application, rename or back it up first; if it is a leftover you own, drop it '
            .'(and any stale migration-ledger rows) and re-run `php artisan migrate`. See larabill\'s UPGRADE-4.0.md.'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Nothing to restore: the dropped table was a disposable legacy cache.
    }

    private function migrationsTable(): string
    {
        // Laravel >= 11 nests the ledger table under database.migrations.table;
        // older skeletons kept a plain string. Fall back to the default name.
        $config = config('database.migrations', 'migrations');
        $table = is_array($config) ? ($config['table'] ?? null) : $config;

        return is_string($table) && $table !== '' ? $table : 'migrations';
    }
};
