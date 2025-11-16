<?php

namespace Aichadigital\Lararoi;

use Aichadigital\Lararoi\Contracts\VatVerificationModelInterface;
use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;
use Aichadigital\Lararoi\Models\VatVerification;
use Aichadigital\Lararoi\Providers\IsvatProvider;
use Aichadigital\Lararoi\Providers\VatlayerProvider;
use Aichadigital\Lararoi\Providers\ViesApiProvider;
use Aichadigital\Lararoi\Providers\ViesRestProvider;
use Aichadigital\Lararoi\Providers\ViesSoapProvider;
use Aichadigital\Lararoi\Services\VatProviderManager;
use Aichadigital\Lararoi\Services\VatVerificationService;
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
            ->hasMigration('create_vat_verifications_table')
            ->hasCommands([
                \Aichadigital\Lararoi\Console\Commands\VerifyVatCommand::class,
                \Aichadigital\Lararoi\Console\Commands\Dev\TestVatProviderCommand::class,
                \Aichadigital\Lararoi\Console\Commands\Dev\TestVatFromFileCommand::class,
                \Aichadigital\Lararoi\Console\Commands\Dev\ListProvidersCommand::class,
                \Aichadigital\Lararoi\Console\Commands\Dev\GenerateStubsCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register VatProviderManager FIRST (required by VatVerificationService)
        $this->app->singleton(VatProviderManager::class, function ($app) {
            $config = config('lararoi', []);
            $providerOrder = $config['providers_order'] ?? ['vies_rest', 'vies_soap'];

            $manager = new VatProviderManager($providerOrder);

            // Register FREE providers
            $manager->register('vies_rest', new ViesRestProvider);
            $manager->register('vies_soap', new ViesSoapProvider($config['vies']['test_mode'] ?? false));
            $manager->register('isvat', new IsvatProvider($config['provider_config']['isvat']['use_live'] ?? false));

            // Register PAID providers if configured
            $vatlayerConfig = $config['provider_config']['vatlayer'] ?? [];
            if (isset($vatlayerConfig['api_key']) && $vatlayerConfig['api_key'] !== '') {
                $manager->register('vatlayer', new VatlayerProvider($vatlayerConfig['api_key']));
            }

            $viesapiConfig = $config['provider_config']['viesapi'] ?? [];
            if (isset($viesapiConfig['api_key']) && $viesapiConfig['api_key'] !== '') {
                $manager->register('viesapi', new ViesApiProvider(
                    $viesapiConfig['api_key'],
                    $viesapiConfig['api_secret'] ?? null,
                    $viesapiConfig['ip'] ?? null
                ));
            }

            return $manager;
        });

        // Main service binding
        $this->app->singleton(VatVerificationServiceInterface::class, function ($app) {
            return new VatVerificationService($app->make(VatProviderManager::class));
        });

        // Model binding
        $this->app->bind(VatVerificationModelInterface::class, VatVerification::class);
    }
}
