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
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup default cache to use array driver
        $app['config']->set('cache.default', 'array');

        // NOTE: the package migrations directory is deliberately NOT registered
        // here. The service provider's boot() loads it via loadMigrationsFrom()
        // (PACKAGE_DEVELOPMENT_STANDARDS.md), so the whole suite pins that the
        // provider — not the test harness — feeds the migrator. A TestCase-side
        // registration masked the missing provider load until AID-323.
    }
}
