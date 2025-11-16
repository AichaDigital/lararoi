<?php

namespace Aichadigital\Lararoi\Console\Commands\Dev;

use Aichadigital\Lararoi\Services\VatProviderManager;
use Illuminate\Console\Command;

/**
 * Developer command: List available providers
 */
class ListProvidersCommand extends Command
{
    protected $signature = 'lararoi:dev:list-providers';

    protected $description = 'List all available VAT providers and their status';

    protected VatProviderManager $providerManager;

    public function __construct(VatProviderManager $providerManager)
    {
        parent::__construct();
        $this->providerManager = $providerManager;
    }

    public function handle(): int
    {
        $this->info('📋 Available VAT providers:');
        $this->newLine();

        $providers = $this->providerManager->getProviders();
        $freeProviders = $this->providerManager->getFreeProviders();
        $paidProviders = $this->providerManager->getPaidProviders();

        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("🆓 FREE Providers ({$freeProviders->count()}):");
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($freeProviders->isEmpty()) {
            $this->line('  <fg=gray>No free providers registered</fg=gray>');
        } else {
            foreach ($freeProviders as $name => $provider) {
                $this->displayProvider($name, $provider);
            }
        }

        $this->newLine();
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("💰 PAID Providers ({$paidProviders->count()}):");
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($paidProviders->isEmpty()) {
            $this->line('  <fg=gray>No paid providers configured</fg=gray>');
            $this->line('  💡 Configure API keys in .env to enable them:');
            $this->line('     - VATLAYER_KEY');
            $this->line('     - VIESAPI_KEY (and optionally VIESAPI_SECRET and VIESAPI_IP)');
        } else {
            foreach ($paidProviders as $name => $provider) {
                $this->displayProvider($name, $provider);
            }
        }

        // Show providers not registered but available to configure
        $this->newLine();
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('⚙️  NOT CONFIGURED Providers:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $allPossibleProviders = ['vies_rest', 'vies_soap', 'isvat', 'vatlayer', 'viesapi'];
        $registeredProviders = $providers->keys()->toArray();
        $notRegistered = array_diff($allPossibleProviders, $registeredProviders);

        if (empty($notRegistered)) {
            $this->line('  <fg=green>✓ All providers are registered</fg=green>');
        } else {
            foreach ($notRegistered as $name) {
                $this->line("  <comment>{$name}</comment>");
                if ($name === 'vatlayer') {
                    $this->line('    💡 Requires: VATLAYER_KEY in .env');
                } elseif ($name === 'viesapi') {
                    $this->line('    💡 Requires: VIESAPI_KEY in .env');
                    $this->line('    💡 Optional: VIESAPI_SECRET and VIESAPI_IP');
                }
                $this->newLine();
            }
        }

        $this->newLine();
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 Configured fallback order:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $order = \config('lararoi.providers_order', []);
        foreach ($order as $index => $providerName) {
            $provider = $providers->get($providerName);
            $status = $provider && $provider->isAvailable()
                ? '<fg=green>✓ Available</fg=green>'
                : '<fg=red>✗ Not available</fg=red>';

            $this->line('  '.($index + 1).". <comment>{$providerName}</comment> {$status}");
        }

        return self::SUCCESS;
    }

    protected function displayProvider(string $name, $provider): void
    {
        $available = $provider->isAvailable()
            ? '<fg=green>✓ Available</fg=green>'
            : '<fg=red>✗ Not available</fg=red>';

        $this->line("  <comment>{$name}</comment>");
        $this->line("    Name: {$provider->getName()}");
        $this->line("    Status: {$available}");

        if (! $provider->isAvailable() && ! $provider->isFree()) {
            $this->line('    💡 Requires: API key configured');
        }

        $this->newLine();
    }
}
