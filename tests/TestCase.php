<?php

namespace Aichadigital\Lararoi\Tests;

use Aichadigital\Lararoi\LararoiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LararoiServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        // Default engine: sqlite :memory: (fast, transaction-friendly).
        // LARAROI_TEST_DB_DRIVER=mysql switches the suite to a real MySQL —
        // the release gate for the destructive preflight migration, whose
        // fingerprint logic rides on Schema::getIndexes() metadata that
        // sqlite alone cannot vouch for (AID-325).
        $app['config']->set('database.default', 'testing');

        if (env('LARAROI_TEST_DB_DRIVER') === 'mysql') {
            $app['config']->set('database.connections.testing', [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => env('DB_DATABASE', 'lararoi_test'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ]);
        } else {
            $app['config']->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        }

        // Setup default cache to use array driver
        $app['config']->set('cache.default', 'array');

        // NOTE: the package migrations directory is deliberately NOT registered
        // here. The service provider's boot() loads it via loadMigrationsFrom()
        // (PACKAGE_DEVELOPMENT_STANDARDS.md), so the whole suite pins that the
        // provider — not the test harness — feeds the migrator. A TestCase-side
        // registration masked the missing provider load until AID-323.
    }
}
