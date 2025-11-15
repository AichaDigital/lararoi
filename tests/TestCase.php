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

        // Load migrations
        $migration = include __DIR__.'/../database/migrations/create_vat_verifications_table.php.stub';
        $migration->up();
    }
}
