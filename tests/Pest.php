<?php

use Aichadigital\Lararoi\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

// sqlite :memory: rides RefreshDatabase (one migrate per process + rollback
// transactions). On real MySQL (LARAROI_TEST_DB_DRIVER=mysql, the AID-325
// release gate) DDL statements implicitly commit and break out of the
// wrapping transaction — the preflight tests create/drop tables — so each
// test starts from a bare `migrate:fresh` instead. Deliberately NOT the
// DatabaseMigrations trait: its teardown runs `migrate:rollback`, whose
// down() paths explode against the mangled mid-test states the preflight
// tests build on purpose.
if (env('LARAROI_TEST_DB_DRIVER') === 'mysql') {
    uses(TestCase::class)
        ->beforeEach(function () {
            $this->artisan('migrate:fresh');
        })
        ->in(__DIR__);
} else {
    uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);
}
