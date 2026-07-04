<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AID-324 + AID-325: the upgrade preflight may drop larabill 3.x's legacy
 * `vat_verifications` table (a disposable VIES cache) — but a drop demands
 * DOUBLE proof: the physical index fingerprint of the table (legacy larabill
 * carries the UNIQUE composite and lacks the plain composite; lararoi's own
 * create produces the plain composite) AND the larabill ledger row. The
 * ledger alone only proves a migration once ran, not that the CURRENT table
 * is larabill's (adversarial findings P1:52/P1:33 on v1.0.3): stale/copied
 * ledgers or re-timestamped published migrations must never cause a drop.
 * An explicit operator flag is the only escape hatch for unproven states.
 *
 * The tests drive the migration objects directly against prepared database
 * states because RefreshDatabase has already run the package migrations
 * before each test.
 */
const AID324_LEGACY_LEDGER_ROW = '2024_12_01_000007_create_vat_verifications_table';
const AID324_LARAROI_CREATE_ROW = '2025_01_01_000001_create_vat_verifications_table';

function aid324Migration(string $filename): Migration
{
    return require __DIR__.'/../../database/migrations/'.$filename;
}

function aid324Preflight(): Migration
{
    return aid324Migration('2025_01_01_000000_drop_legacy_larabill_vat_verifications_table.php');
}

/**
 * The exact schema larabill 3.x's 2024_12_01_000007 migration created —
 * UNIQUE composite index (not the plain one lararoi's rename drops by name)
 * and string company_address. This is the physical legacy fingerprint.
 */
function aid324CreateLegacyLarabillTable(): void
{
    Schema::create('vat_verifications', function (Blueprint $table) {
        $table->id();
        $table->string('vat_code');
        $table->string('country_code', 2);
        $table->boolean('is_valid');
        $table->string('company_name')->nullable();
        $table->string('company_address')->nullable();
        $table->string('api_source')->nullable();
        $table->json('response_data')->nullable();
        $table->timestamp('checked_at')->nullable();
        $table->timestamp('verified_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
        $table->softDeletes();

        $table->index(['vat_code', 'is_valid']);
        $table->index(['is_valid', 'verified_at']);
        $table->unique(['vat_code', 'country_code']);
    });
}

/**
 * The shape lararoi's OWN create migration (2025_01_01_000001) produces:
 * the plain composite index the rename migration later drops by name.
 */
function aid324CreateLararoiShapedTable(): void
{
    Schema::create('vat_verifications', function (Blueprint $table) {
        $table->id();
        $table->string('vat_code', 50)->index();
        $table->string('country_code', 2)->index();
        $table->boolean('is_valid')->default(false);
        $table->string('company_name')->nullable();
        $table->text('company_address')->nullable();
        $table->string('api_source', 50)->default('UNKNOWN');
        $table->json('response_data')->nullable();
        $table->timestamp('checked_at')->nullable();
        $table->timestamp('verified_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
        $table->softDeletes();

        $table->index(['vat_code', 'country_code']);
        $table->index(['vat_code', 'is_valid']);
        $table->index(['is_valid', 'verified_at']);
    });
}

/**
 * Simulate the pre-adoption slate: no roi cache table (required on SQLite,
 * where index names are database-global and the renamed roi table still
 * holds the legacy vat_verifications_* index names) and no lararoi ledger
 * rows for the cache-table chain.
 */
function aid324SimulatePreAdoptionState(): void
{
    Schema::drop('roi_vat_verifications');
    DB::table('migrations')->where('migration', AID324_LARAROI_CREATE_ROW)->delete();
}

it('no-ops on a database without a vat_verifications table', function () {
    expect(Schema::hasTable('vat_verifications'))->toBeFalse();

    aid324Preflight()->up();

    expect(Schema::hasTable('roi_vat_verifications'))->toBeTrue();
});

it('drops a doubly-proven legacy larabill table together with its orphaned ledger row', function () {
    aid324SimulatePreAdoptionState();
    DB::table('migrations')->insert(['migration' => AID324_LEGACY_LEDGER_ROW, 'batch' => 1]);
    aid324CreateLegacyLarabillTable();

    aid324Preflight()->up();

    expect(Schema::hasTable('vat_verifications'))->toBeFalse()
        ->and(DB::table('migrations')->where('migration', AID324_LEGACY_LEDGER_ROW)->exists())->toBeFalse();
});

it('carries the legacy case through the full chain to the canonical roi schema', function () {
    aid324SimulatePreAdoptionState();
    DB::table('migrations')->insert(['migration' => AID324_LEGACY_LEDGER_ROW, 'batch' => 1]);
    aid324CreateLegacyLarabillTable();

    aid324Preflight()->up();
    aid324Migration('2025_01_01_000001_create_vat_verifications_table.php')->up();
    aid324Migration('2026_07_03_000001_rename_vat_verifications_to_roi.php')->up();

    expect(Schema::hasTable('roi_vat_verifications'))->toBeTrue()
        ->and(Schema::hasTable('vat_verifications'))->toBeFalse()
        ->and(Schema::hasColumn('roi_vat_verifications', 'deleted_at'))->toBeFalse()
        ->and(Schema::hasColumn('roi_vat_verifications', 'verified_at'))->toBeTrue();
});

it('refuses to drop an app-owned homonymous table even when a stale larabill ledger row exists (wrong-drop guard)', function () {
    // Adversarial P1:52 — the ledger row only proves larabill once ran, not
    // that the CURRENT table is larabill's. Copied/squashed ledgers or a
    // table recreated by the app after larabill must never be dropped.
    // On v1.0.3 this test FAILS: the ledger row alone triggered the drop.
    aid324SimulatePreAdoptionState();
    DB::table('migrations')->insert(['migration' => AID324_LEGACY_LEDGER_ROW, 'batch' => 1]);
    Schema::create('vat_verifications', function (Blueprint $table) {
        $table->id();
        $table->string('payload');
    });
    DB::table('vat_verifications')->insert(['payload' => 'app-owned data']);

    expect(fn () => aid324Preflight()->up())
        ->toThrow(RuntimeException::class, 'UPGRADE-4.0');

    expect(Schema::hasTable('vat_verifications'))->toBeTrue()
        ->and(DB::table('vat_verifications')->count())->toBe(1);
});

it('refuses to drop a lararoi-shaped table under a stale larabill ledger row (re-timestamped publish guard)', function () {
    // Adversarial P1:33 — a consumer whose canonical lararoi table was
    // created by a re-timestamped published copy carries no canonical create
    // row in the ledger. If the old larabill row also lingers, v1.0.3
    // dropped the CANONICAL lararoi table as "legacy". The physical plain
    // composite index proves the table is lararoi's; nothing may drop it.
    aid324SimulatePreAdoptionState();
    DB::table('migrations')->insert(['migration' => AID324_LEGACY_LEDGER_ROW, 'batch' => 1]);
    aid324CreateLararoiShapedTable();

    expect(fn () => aid324Preflight()->up())
        ->toThrow(RuntimeException::class, 'UPGRADE-4.0');

    expect(Schema::hasTable('vat_verifications'))->toBeTrue();
});

it('leaves a mid-upgrade lararoi-owned table alone (plain index present, canonical create in the ledger)', function () {
    // create ran on a previous attempt, rename still pending: the physical
    // lararoi shape + the canonical create row make this the one state the
    // rename migration is entitled to finish.
    aid324SimulatePreAdoptionState();
    DB::table('migrations')->insert(['migration' => AID324_LARAROI_CREATE_ROW, 'batch' => 1]);
    aid324CreateLararoiShapedTable();

    aid324Preflight()->up();

    expect(Schema::hasTable('vat_verifications'))->toBeTrue();
});

it('aborts loudly instead of claiming a table it cannot doubly prove is larabill', function () {
    // Legacy physical shape but no larabill ledger row (schema:dump prune,
    // copied database): abort with recovery guidance, never guess.
    aid324SimulatePreAdoptionState();
    aid324CreateLegacyLarabillTable();

    expect(fn () => aid324Preflight()->up())
        ->toThrow(RuntimeException::class, 'UPGRADE-4.0');

    expect(Schema::hasTable('vat_verifications'))->toBeTrue();
});

it('honors the operator escape hatch for an unproven table (explicit human decision)', function () {
    // Adversarial P1:59 — the abort path needs an operational exit that does
    // not require SQL access mid-deploy: the operator verifies the table,
    // backs it up, and sets the flag to let the preflight claim it.
    // On v1.0.3 this test FAILS: no hatch existed, the abort was terminal.
    aid324SimulatePreAdoptionState();
    aid324CreateLegacyLarabillTable();
    config()->set('lararoi.upgrade.assume_legacy_vat_table', true);

    aid324Preflight()->up();

    expect(Schema::hasTable('vat_verifications'))->toBeFalse();
});

it('reads and cleans the ledger through the official migration repository (custom migrations table)', function () {
    // Adversarial P2:75 — deriving the ledger table by hand from config
    // diverges from custom repositories/tenancy. The framework repository is
    // the single source of truth for where the ledger lives.
    config()->set('database.migrations.table', 'custom_migration_ledger');
    app()->forgetInstance('migration.repository');

    Schema::create('custom_migration_ledger', function (Blueprint $table) {
        $table->increments('id');
        $table->string('migration');
        $table->integer('batch');
    });

    aid324SimulatePreAdoptionState();
    DB::table('custom_migration_ledger')->insert(['migration' => AID324_LEGACY_LEDGER_ROW, 'batch' => 1]);
    aid324CreateLegacyLarabillTable();

    aid324Preflight()->up();

    expect(Schema::hasTable('vat_verifications'))->toBeFalse()
        ->and(DB::table('custom_migration_ledger')->where('migration', AID324_LEGACY_LEDGER_ROW)->exists())->toBeFalse();
});

it('sorts before every other package migration so the migrator always runs the preflight first', function () {
    $files = collect(glob(__DIR__.'/../../database/migrations/*.php'))
        ->map(fn (string $path) => basename($path))
        ->sort()
        ->values();

    expect($files->first())->toBe('2025_01_01_000000_drop_legacy_larabill_vat_verifications_table.php');
});
