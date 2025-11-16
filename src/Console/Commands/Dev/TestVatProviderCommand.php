<?php

namespace Aichadigital\Lararoi\Console\Commands\Dev;

use Aichadigital\Lararoi\Contracts\VatProviderInterface;
use Aichadigital\Lararoi\Services\VatProviderManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Developer command: Test VAT providers with real APIs
 *
 * Exclusive use for development. Queries real APIs and displays
 * the responses obtained.
 */
class TestVatProviderCommand extends Command
{
    protected $signature = 'lararoi:dev:test-provider
                            {vat : VAT number without country prefix}
                            {country : Country code (2 letters, e.g.: ES, DE, MT)}
                            {provider? : Provider name (vies_rest, vies_soap, isvat, vatlayer, viesapi)}
                            {--json : Show response in JSON format}
                            {--all : Test all available providers}';

    protected $description = 'Test a VAT provider with a real API (development only)';

    protected VatProviderManager $providerManager;

    public function __construct(VatProviderManager $providerManager)
    {
        parent::__construct();
        $this->providerManager = $providerManager;
    }

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->testAllProviders();
        }

        $providerName = $this->argument('provider');

        if (! $providerName) {
            $this->error('❌ Provider name is required when not using --all option');
            $this->info('Usage: lararoi:dev:test-provider <vat> <country> <provider>');
            $this->info('   or: lararoi:dev:test-provider <vat> <country> --all');

            return self::FAILURE;
        }

        $vatNumber = $this->argument('vat');
        $countryCode = strtoupper($this->argument('country'));

        $this->info("🔍 Testing provider: <comment>{$providerName}</comment>");
        $this->info("📋 VAT: <comment>{$countryCode}{$vatNumber}</comment>");
        $this->newLine();

        $provider = $this->providerManager->getProviders()->get($providerName);

        if (! $provider) {
            $this->error("❌ Provider '{$providerName}' not found.");
            $this->info('Available providers: '.$this->providerManager->getProviders()->keys()->implode(', '));

            return self::FAILURE;
        }

        if (! $provider->isAvailable()) {
            $this->error("❌ Provider '{$providerName}' is not available.");
            if (! $provider->isFree()) {
                $this->warn('💡 This provider requires configuration (API key, certificate, etc.)');
            }

            return self::FAILURE;
        }

        $this->displayProviderInfo($provider);

        try {
            $startTime = microtime(true);
            $result = $provider->verify($vatNumber, $countryCode);
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->newLine();
            $this->info("✅ <fg=green>Verification successful</fg=green> (Time: {$duration}ms)");
            $this->newLine();

            if ($this->option('json')) {
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->displayResult($result);
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ <fg=red>Error verifying:</fg=red>');
            $this->error('   '.$e->getMessage());

            if ($this->getOutput()->isVerbose()) {
                $this->newLine();
                $this->line('<comment>Stack trace:</comment>');
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    protected function testAllProviders(): int
    {
        $vatNumber = $this->argument('vat');
        $countryCode = strtoupper($this->argument('country'));

        $this->info('🔍 Testing ALL available providers');
        $this->info("📋 VAT: <comment>{$countryCode}{$vatNumber}</comment>");
        $this->newLine();

        $providers = $this->providerManager->getProviders();
        $results = [];

        foreach ($providers as $name => $provider) {
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("Provider: <comment>{$name}</comment>");

            if (! $provider->isAvailable()) {
                $this->warn('  ⚠️  Not available (requires configuration)');
                $results[$name] = ['status' => 'unavailable'];

                continue;
            }

            try {
                $startTime = microtime(true);
                $result = $provider->verify($vatNumber, $countryCode);
                $duration = round((microtime(true) - $startTime) * 1000, 2);

                $this->line('  ✅ Valid: '.($result['valid'] ? '<fg=green>Yes</fg=green>' : '<fg=red>No</fg=red>'));
                $this->line("  ⏱️  Time: {$duration}ms");
                if (! empty($result['name'])) {
                    $this->line('  🏢 Name: '.Str::limit($result['name'], 50));
                }

                $results[$name] = [
                    'status' => 'success',
                    'result' => $result,
                    'duration' => $duration,
                ];
            } catch (\Exception $e) {
                $this->error('  ❌ Error: '.$e->getMessage());
                $results[$name] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }

            $this->newLine();
        }

        // Summary
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 Summary:');
        $success = collect($results)->where('status', 'success')->count();
        $errors = collect($results)->where('status', 'error')->count();
        $unavailable = collect($results)->where('status', 'unavailable')->count();

        $this->line("  ✅ Successful: {$success}");
        $this->line("  ❌ Errors: {$errors}");
        $this->line("  ⚠️  Not available: {$unavailable}");

        if ($this->option('json')) {
            $this->newLine();
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function displayProviderInfo(VatProviderInterface $provider): void
    {
        $this->line('📌 Provider information:');
        $this->line("   Name: <comment>{$provider->getName()}</comment>");
        $this->line('   Type: '.($provider->isFree() ? '<fg=green>Free</fg=green>' : '<fg=yellow>Paid</fg=yellow>'));
        $this->line('   Available: '.($provider->isAvailable() ? '<fg=green>Yes</fg=green>' : '<fg=red>No</fg=red>'));
    }

    protected function displayResult(array $result): void
    {
        $this->table(
            ['Field', 'Value'],
            [
                ['Valid', $result['valid'] ? '<fg=green>Yes</fg=green>' : '<fg=red>No</fg=red>'],
                ['VAT Code', ($result['country_code'] ?? '').($result['vat_number'] ?? '')],
                ['Country', $result['country_code'] ?? 'N/A'],
                ['Name', $result['name'] ?? '<fg=gray>N/A</fg=gray>'],
                ['Address', $result['address'] ? Str::limit($result['address'], 60) : '<fg=gray>N/A</fg=gray>'],
                ['Date', $result['request_date'] ?? '<fg=gray>N/A</fg=gray>'],
                ['API Source', $result['api_source'] ?? 'UNKNOWN'],
            ]
        );
    }
}
