<?php

namespace Aichadigital\Lararoi;

use Aichadigital\Lararoi\Contracts\VatVerificationModelInterface;
use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;
use Aichadigital\Lararoi\Models\VatVerification;
use Aichadigital\Lararoi\Providers\AeatProvider;
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
            $manager->register('isvat', new IsvatProvider(null, $config['provider_config']['isvat']['use_live'] ?? false));

            // Register AEAT if configured
            $aeatConfig = $config['aeat'] ?? [];
            $hasP12 = ! empty($aeatConfig['p12_path']) && file_exists($aeatConfig['p12_path']);
            $hasCertKey = ! empty($aeatConfig['cert_path']) && ! empty($aeatConfig['key_path'])
                && file_exists($aeatConfig['cert_path']) && file_exists($aeatConfig['key_path']);

            if ($hasP12 || $hasCertKey) {
                $manager->register('aeat', new AeatProvider(
                    $aeatConfig['cert_path'] ?? null,
                    $aeatConfig['key_path'] ?? null,
                    $aeatConfig['p12_path'] ?? null,
                    $aeatConfig['endpoint'] ?? null,
                    $aeatConfig['passphrase'] ?? null
                ));
            }

            // Register PAID providers if configured
            $vatlayerConfig = $config['provider_config']['vatlayer'] ?? [];
            if (! empty($vatlayerConfig['api_key'])) {
                $manager->register('vatlayer', new VatlayerProvider(null, $vatlayerConfig['api_key']));
            }

            $viesapiConfig = $config['provider_config']['viesapi'] ?? [];
            if (! empty($viesapiConfig['api_key'])) {
                $manager->register('viesapi', new ViesApiProvider(
                    null,
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
