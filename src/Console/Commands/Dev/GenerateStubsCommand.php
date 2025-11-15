<?php

namespace Aichadigital\Lararoi\Console\Commands\Dev;

use Aichadigital\Lararoi\Services\VatProviderManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Command to generate stubs from real API responses
 *
 * This command connects to real APIs using test VAT numbers
 * and generates stubs that will be used in tests.
 *
 * IMPORTANT: The VAT numbers used here are for testing and MUST NOT be real.
 */
class GenerateStubsCommand extends Command
{
    protected $signature = 'lararoi:dev:generate-stubs
                            {--provider= : Specific provider to test (optional)}
                            {--output=tests/stubs : Output directory}';

    protected $description = 'Generate stubs from real API responses for use in tests';

    protected VatProviderManager $providerManager;

    public function __construct(VatProviderManager $providerManager)
    {
        parent::__construct();
        $this->providerManager = $providerManager;
    }

    public function handle(): int
    {
        $this->info('🔧 Generando stubs de respuestas de APIs...');
        $this->newLine();

        $outputDir = $this->option('output');
        $specificProvider = $this->option('provider');

        // Create directory if it doesn't exist
        if (! File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        // Safe test VAT numbers (for Guardian to ignore)
        // These are fictional numbers that follow the format but are not real
        $testVats = [
            'valid' => [
                'ES' => 'B99999999',  // Valid format but fictional
                'DE' => 'DE999999999', // Valid format but fictional
                'FR' => 'FR99999999999', // Valid format but fictional
                'IT' => 'IT99999999999', // Valid format but fictional
            ],
            'invalid' => [
                'ES' => 'B00000000',  // Valid format but invalid
                'DE' => 'DE000000000', // Valid format but invalid
            ],
        ];

        $providers = $specificProvider
            ? [$specificProvider => $this->providerManager->getProvider($specificProvider)]
            : $this->providerManager->getProviders();

        if (count($providers) === 0) {
            $this->error('No providers available');

            return self::FAILURE;
        }

        $generated = 0;
        $errors = 0;

        foreach ($providers as $providerName => $provider) {
            if (! $provider || ! $provider->isAvailable()) {
                $this->warn("⏭️  Skipping {$providerName} (not available)");

                continue;
            }

            $this->line("📡 Testing provider: <comment>{$providerName}</comment>");

            try {
                // Test with valid VAT
                $this->line('  → Testing valid VAT...');
                $validResponse = $this->testProvider($provider, 'ES', $testVats['valid']['ES']);

                if ($validResponse) {
                    $this->saveStub($outputDir, $providerName, 'valid', $validResponse);
                    $this->info('  ✓ Valid stub generated');
                    $generated++;
                }

                // Test with invalid VAT
                $this->line('  → Testing invalid VAT...');
                $invalidResponse = $this->testProvider($provider, 'ES', $testVats['invalid']['ES']);

                if ($invalidResponse) {
                    $this->saveStub($outputDir, $providerName, 'invalid', $invalidResponse);
                    $this->info('  ✓ Invalid stub generated');
                    $generated++;
                }

            } catch (\Exception $e) {
                $this->error("  ✗ Error: {$e->getMessage()}");
                $errors++;
            }

            $this->newLine();
        }

        $this->newLine();
        $this->info("✅ Generados {$generated} stubs");
        if ($errors > 0) {
            $this->warn("⚠️  {$errors} errores");
        }

        return self::SUCCESS;
    }

    protected function testProvider($provider, string $country, string $vat): ?array
    {
        try {
            $result = $provider->verify($vat, $country);

            // Normalizar respuesta para el stub
            return [
                'valid' => $result['valid'] ?? false,
                'vat_number' => $result['vat_number'] ?? $vat,
                'country_code' => $result['country_code'] ?? $country,
                'name' => $result['name'] ?? null,
                'address' => $result['address'] ?? null,
                'request_date' => $result['request_date'] ?? null,
                'api_source' => $result['api_source'] ?? 'UNKNOWN',
                'raw_response' => $result, // Complete response for reference
            ];
        } catch (\Exception $e) {
            // For errors, save the error type
            return [
                'error' => true,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'vat_number' => $vat,
                'country_code' => $country,
            ];
        }
    }

    protected function saveStub(string $outputDir, string $providerName, string $type, array $data): void
    {
        $filename = "{$outputDir}/{$providerName}_{$type}.json";
        File::put($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
