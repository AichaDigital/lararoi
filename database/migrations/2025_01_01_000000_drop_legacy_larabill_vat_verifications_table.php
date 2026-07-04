<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade preflight for consumers coming from larabill 3.x (AID-324,
 * hardened by AID-325 after the post-ship adversarial review of v1.0.3).
 *
 * larabill <= 3.x created its own `vat_verifications` table (migration
 * 2024_12_01_000007) with a schema that diverges from the one this package
 * creates and later renames to `roi_vat_verifications`: a UNIQUE composite
 * (vat_code, country_code) index where lararoi expects the plain composite
 * it drops by name, `company_address` as string instead of text, and so on.
 * Letting the legacy table through a naive hasTable guard would crash the
 * rename migration halfway and leave a drifted schema behind.
 *
 * A drop demands DOUBLE proof (v1.0.4 hardening):
 *
 * - the PHYSICAL fingerprint — the UNIQUE composite index without the plain
 *   one, the shape only larabill's migration produced; and
 * - the larabill LEDGER row, which corroborates but is never sufficient
 *   alone: it only proves the migration once ran, so a copied, squashed or
 *   stale ledger must never cost a table (adversarial P1:52/P1:33).
 *
 * The legacy table is a disposable, TTL-bound VIES cache, but its rows may
 * hold residual evidence value (raw VIES responses): the packaged upgrade
 * guidance recommends exporting it before the first migrate. Without double
 * proof the migration aborts loudly; the only exit is the explicit operator
 * hatch `lararoi.upgrade.assume_legacy_vat_table` (env
 * LARAROI_ASSUME_LEGACY_VAT_TABLE), a human decision taken after verifying
 * and exporting the table.
 *
 * Operational caveats, all deliberate:
 *
 * - `migrate --pretend` is misleading here: pretend mode returns empty
 *   results for selects, so hasTable()/ledger probes can branch as if
 *   nothing exists — the real run may drop or abort where the dry run
 *   showed a no-op.
 * - The drop runs BEFORE the ledger cleanup: if the process dies between
 *   the two, the re-run sees no table and no-ops (the orphaned row is
 *   inert). The inverse order would leave a table without proof and turn
 *   the re-run into a hard abort.
 * - down() restores nothing: the dropped table was a disposable cache and
 *   the hatch instructions demand an export first.
 *
 * The filename sorts before every other package migration so the migrator
 * always runs this preflight first on a fresh adoption.
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

        [$hasPlainComposite, $hasUniqueComposite] = $this->compositeIndexFingerprint();

        $repository = $this->migrationRepository();
        $ran = $repository->getRan();

        // Mid-upgrade lararoi state (create ran on an earlier attempt, rename
        // pending): the physical lararoi shape — the plain composite index the
        // rename migration drops by name — corroborated by the canonical
        // create row is the one state the rename is entitled to finish.
        if ($hasPlainComposite && in_array(self::LARAROI_CREATE_MIGRATION, $ran, true)) {
            return;
        }

        $legacyProven = $hasUniqueComposite
            && ! $hasPlainComposite
            && in_array(self::LEGACY_LARABILL_MIGRATION, $ran, true);

        if ($legacyProven || $this->operatorAssumesLegacy()) {
            Schema::drop('vat_verifications');

            $repository->delete((object) ['migration' => self::LEGACY_LARABILL_MIGRATION]);

            return;
        }

        throw new RuntimeException(
            'lararoi: a `vat_verifications` table exists but it cannot be doubly proven (physical index '
            .'fingerprint + migrations-ledger row) to be larabill 3.x\'s disposable VIES cache, so it will '
            .'not be dropped automatically. If the table belongs to your application, rename or back it up, '
            .'then re-run `php artisan migrate`. If you have verified it IS the legacy larabill cache, '
            .'export it first (e.g. `mysqldump <db> vat_verifications > vat_verifications-pre-lararoi.sql`) '
            .'and set LARAROI_ASSUME_LEGACY_VAT_TABLE=true (config `lararoi.upgrade.assume_legacy_vat_table`) '
            .'so this preflight can claim it. See larabill\'s UPGRADE-4.0.md.'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Nothing to restore: the dropped table was a disposable legacy cache
        // and the hatch instructions demand an export before forcing the drop.
    }

    /**
     * Physical fingerprint of the composite (vat_code, country_code) indexes,
     * matched structurally (columns + uniqueness, never index names) so
     * database prefixes cannot skew the verdict.
     *
     * @return array{0: bool, 1: bool} [plain composite present, unique composite present]
     */
    private function compositeIndexFingerprint(): array
    {
        $hasPlain = false;
        $hasUnique = false;

        foreach (Schema::getIndexes('vat_verifications') as $index) {
            $columns = array_map('strtolower', (array) ($index['columns'] ?? []));

            if ($columns !== ['vat_code', 'country_code'] || ($index['primary'] ?? false)) {
                continue;
            }

            if ($index['unique'] ?? false) {
                $hasUnique = true;
            } else {
                $hasPlain = true;
            }
        }

        return [$hasPlain, $hasUnique];
    }

    /**
     * The framework repository is the single source of truth for where the
     * ledger lives (custom migrations table, connection or repository
     * binding) — never re-derive it from config by hand.
     */
    private function migrationRepository(): MigrationRepositoryInterface
    {
        return app('migration.repository');
    }

    /**
     * Explicit operator decision for unproven states. Read through config —
     * the package's mergeConfigFrom guarantees the `upgrade` key exists even
     * when the consumer's published config predates it, and the config layer
     * (unlike a direct env() call) survives config:cache.
     */
    private function operatorAssumesLegacy(): bool
    {
        return (bool) config('lararoi.upgrade.assume_legacy_vat_table', false);
    }
};
