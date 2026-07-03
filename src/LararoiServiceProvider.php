<?php

namespace Aichadigital\Lararoi;

use Aichadigital\Lararoi\Console\Commands\Dev\GenerateStubsCommand;
use Aichadigital\Lararoi\Console\Commands\Dev\ListProvidersCommand;
use Aichadigital\Lararoi\Console\Commands\Dev\TestVatFromFileCommand;
use Aichadigital\Lararoi\Console\Commands\Dev\TestVatProviderCommand;
use Aichadigital\Lararoi\Console\Commands\PruneVerificationQueriesCommand;
use Aichadigital\Lararoi\Console\Commands\VerifyVatCommand;
use Aichadigital\Lararoi\Contracts\VatVerificationModelInterface;
use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;
use Aichadigital\Lararoi\Contracts\VerificationQueryModelInterface;
use Aichadigital\Lararoi\Contracts\VerificationTrackerInterface;
use Aichadigital\Lararoi\Models\VatVerification;
use Aichadigital\Lararoi\Models\VerificationQuery;
use Aichadigital\Lararoi\Providers\IsvatProvider;
use Aichadigital\Lararoi\Providers\VatlayerProvider;
use Aichadigital\Lararoi\Providers\ViesApiProvider;
use Aichadigital\Lararoi\Providers\ViesRestProvider;
use Aichadigital\Lararoi\Providers\ViesSoapProvider;
use Aichadigital\Lararoi\Services\VatProviderManager;
use Aichadigital\Lararoi\Services\VatVerificationService;
use Aichadigital\Lararoi\Services\VerificationTracker;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service Provider for Lararoi using Spatie Package Tools
 *
 * This ensures proper integration with Laravel and correct test setup
 */
class LararoiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('lararoi')
            ->hasConfigFile()
            ->hasMigrations([
                '2025_01_01_000001_create_vat_verifications_table',
                '2026_07_03_000001_rename_vat_verifications_to_roi',
                '2026_07_03_000002_create_roi_verification_queries_table',
            ])
            ->hasCommands([
                VerifyVatCommand::class,
                TestVatProviderCommand::class,
                TestVatFromFileCommand::class,
                ListProvidersCommand::class,
                GenerateStubsCommand::class,
                PruneVerificationQueriesCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register VatProviderManager FIRST (required by VatVerificationService)
        $this->app->singleton(VatProviderManager::class, function ($app) {
            $config = config('lararoi', []);
            $providerOrder = $config['providers_order'] ?? VatProviderManager::DEFAULT_PROVIDER_ORDER;

            $manager = new VatProviderManager($providerOrder);

            // Register FREE providers
            $manager->register('vies_rest', new ViesRestProvider);
            $manager->register('vies_soap', new ViesSoapProvider($config['vies']['test_mode'] ?? false));
            $manager->register('isvat', new IsvatProvider($config['provider_config']['isvat']['use_live'] ?? false));

            // Register PAID providers when enabled (defaults true) and an API key is set.
            // enabled gates explicit activation; api_key enables the transport.
            $vatlayerConfig = $config['provider_config']['vatlayer'] ?? [];
            if (($vatlayerConfig['enabled'] ?? true) && isset($vatlayerConfig['api_key']) && $vatlayerConfig['api_key'] !== '') {
                $manager->register('vatlayer', new VatlayerProvider($vatlayerConfig['api_key']));
            }

            $viesapiConfig = $config['provider_config']['viesapi'] ?? [];
            if (($viesapiConfig['enabled'] ?? true) && isset($viesapiConfig['api_key']) && $viesapiConfig['api_key'] !== '') {
                $manager->register('viesapi', new ViesApiProvider(
                    $viesapiConfig['api_key'],
                    $viesapiConfig['api_secret'] ?? null,
                    $viesapiConfig['ip'] ?? null
                ));
            }

            return $manager;
        });

        // Tracking / audit binding (ADR-002 D3/D4)
        $this->app->singleton(VerificationTrackerInterface::class, function ($app) {
            return new VerificationTracker;
        });

        // Main service binding
        $this->app->singleton(VatVerificationServiceInterface::class, function ($app) {
            return new VatVerificationService(
                $app->make(VatProviderManager::class),
                $app->make(VerificationTrackerInterface::class),
            );
        });

        // Model bindings (both cache and tracking models are swappable)
        $this->app->bind(VatVerificationModelInterface::class, VatVerification::class);
        $this->app->bind(VerificationQueryModelInterface::class, VerificationQuery::class);
    }
}
