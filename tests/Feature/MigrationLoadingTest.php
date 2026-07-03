<?php

use Illuminate\Support\Facades\Schema;

/**
 * Regression tests for AID-323: the service provider — not the test harness —
 * must feed the package migrations to the migrator, so a consumer's plain
 * `php artisan migrate` creates the roi_* tables. hasMigrations() alone only
 * registered them for publishing, and a TestCase-side path registration masked
 * the gap (the suite was green while consumers got no tables).
 */
it('registers the package migrations path in the migrator via the service provider', function () {
    $expected = realpath(__DIR__.'/../../database/migrations');

    $paths = array_map(
        fn (string $path) => realpath($path) ?: $path,
        app('migrator')->paths(),
    );

    expect($expected)->not->toBeFalse()
        ->and($paths)->toContain($expected);
});

it('creates the roi_* tables when migrations run', function () {
    expect(Schema::hasTable('roi_vat_verifications'))->toBeTrue()
        ->and(Schema::hasTable('roi_verification_queries'))->toBeTrue();
});
