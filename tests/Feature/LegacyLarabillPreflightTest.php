<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AID-324: the upgrade preflight clears larabill 3.x's legacy
 * `vat_verifications` table (a disposable VIES cache) when — and only when —
 * the migrations ledger proves the table is larabill's, and aborts loudly
 * otherwise. The tests drive the migration objects directly against prepared
 * database states because RefreshDatabase has already run the package
 * migrations before each test.
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
 * and string company_address.
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

it('no-ops on a database without a vat_verifications table', function () {
    expect(Schema::hasTable('vat_verifications'))->toBeFalse();

    aid324Preflight()->up();

    expect(Schema::hasTable('roi_vat_verifications'))->toBeTrue();
});

it('drops a ledger-proven legacy larabill table together with its orphaned ledger row', function () {
    // Pre-adoption state: no roi cache table yet. (Also required on SQLite,
    // where index names are database-global and the renamed roi table still
    // holds the legacy vat_verifications_* index names.)
    Schema::drop('roi_vat_verifications');
    DB::table('migrations')->where('migration', AID324_LARAROI_CREATE_ROW)->delete();
    DB::table('migrations')->insert(['migration' => AID324_LEGACY_LEDGER_ROW, 'batch' => 1]);
    aid324CreateLegacyLarabillTable();

    aid324Preflight()->up();

    expect(Schema::hasTable('vat_verifications'))->toBeFalse()
        ->and(DB::table('migrations')->where('migration', AID324_LEGACY_LEDGER_ROW)->exists())->toBeFalse();
});

it('carries the legacy case through the full chain to the canonical roi schema', function () {
    // A larabill 3.x consumer adopting lararoi: no roi cache table yet, the
    // legacy table and its ledger row are all that exist.
    Schema::drop('roi_vat_verifications');
    DB::table('migrations')->where('migration', AID324_LARAROI_CREATE_ROW)->delete();
    DB::table('migrations')->insert(['migration' => AID324_LEGACY_LEDGER_ROW, 'batch' => 1]);
    aid324CreateLegacyLarabillTable();

    aid324Preflight()->up();
    aid324Migration('2025_01_01_000001_create_vat_verifications_table.php')->up();
    aid324Migration('2026_07_03_000001_rename_vat_verifications_to_roi.php')->up();

    // The rename crashed halfway on the legacy schema before the preflight
    // existed (it drops the plain composite index by name; the legacy table
    // only had a UNIQUE one). Landing here with the canonical shape is the
    // whole point of the fix.
    expect(Schema::hasTable('roi_vat_verifications'))->toBeTrue()
        ->and(Schema::hasTable('vat_verifications'))->toBeFalse()
        ->and(Schema::hasColumn('roi_vat_verifications', 'deleted_at'))->toBeFalse()
        ->and(Schema::hasColumn('roi_vat_verifications', 'verified_at'))->toBeTrue();
});

it('leaves a mid-upgrade lararoi-owned table alone when the create is already in the ledger', function () {
    // RefreshDatabase left the lararoi create row in the ledger; a table named
    // vat_verifications alongside it means "create ran, rename pending".
    Schema::create('vat_verifications', function (Blueprint $table) {
        $table->id();
    });

    aid324Preflight()->up();

    expect(Schema::hasTable('vat_verifications'))->toBeTrue();
});

it('aborts loudly instead of claiming a table the ledger cannot prove is larabill', function () {
    Schema::drop('roi_vat_verifications');
    DB::table('migrations')->where('migration', AID324_LARAROI_CREATE_ROW)->delete();
    aid324CreateLegacyLarabillTable();

    expect(fn () => aid324Preflight()->up())
        ->toThrow(RuntimeException::class, 'UPGRADE-4.0');

    expect(Schema::hasTable('vat_verifications'))->toBeTrue();
});

it('sorts before the create migration so the migrator always runs the preflight first', function () {
    $files = collect(glob(__DIR__.'/../../database/migrations/*.php'))
        ->map(fn (string $path) => basename($path))
        ->sort()
        ->values();

    expect($files->search('2025_01_01_000000_drop_legacy_larabill_vat_verifications_table.php'))
        ->toBeLessThan($files->search('2025_01_01_000001_create_vat_verifications_table.php'));
});
