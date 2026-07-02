<?php

namespace Aichadigital\Lararoi\Tests;

use Aichadigital\Lararoi\LararoiServiceProvider;
use Illuminate\Database\Migrations\Migrator;
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

        // Register the package migrations directory so RefreshDatabase discovers
        // every migration file automatically. The migrator globs *_*.php, so a
        // new migration is picked up without editing this file. See the umbrella
        // CLAUDE.md lesson (2026-07-02): use afterResolving('migrator') + path()
        // (migrates up only, no rollback in teardown) rather than a hardcoded
        // include or defineDatabaseMigrations().
        $app->afterResolving('migrator', function (Migrator $migrator): void {
            $migrator->path(__DIR__.'/../database/migrations');
        });
    }
}
