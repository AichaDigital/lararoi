<?php

declare(strict_types=1);

namespace Aichadigital\Lararoi\Console\Commands;

use Aichadigital\Lararoi\Contracts\VatProviderInterface;
use Aichadigital\Lararoi\Services\VatProviderManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Interactive command to verify VAT numbers with detailed analysis
 *
 * This command provides an interactive interface using Laravel Prompts
 * to verify VAT numbers, analyze responses, and compare provider results.
 * It's designed for end users, not just developers.
 */
class VerifyVatCommand extends Command
{
    protected $signature = 'lararoi:verify
                            {--provider= : Force a specific provider}
                            {--vat= : VAT number (with or without country prefix)}
                            {--country= : Country code (2 letters)}
                            {--name=* : Company name (optional, for validation)}';

    protected $description = 'Interactively verify a VAT number and analyze the response';

    protected VatProviderManager $providerManager;

    public function __construct(VatProviderManager $providerManager)
    {
        parent::__construct();
        $this->providerManager = $providerManager;
    }

    public function handle(): int
    {
        $this->info('🔍 Lararoi - VAT Number Verification');
        $this->newLine();

        // Get VAT number and country FIRST (needed to filter providers)
        $vatData = $this->getVatData();
        if (! $vatData) {
            return self::FAILURE;
        }

        $vatNumber = $vatData['vat'];
        $countryCode = $vatData['country'];

        // Get optional company name
        $companyName = $this->getCompanyName();

        // Get provider (show all available, user can choose)
        $provider = $this->getProvider();
        if (! $provider) {
            return self::FAILURE;
        }

        // Perform verification
        $this->newLine();
        $this->info("🔄 Verifying VAT number: <fg=cyan>{$vatNumber}</> ({$countryCode})");
        if ($companyName) {
            $this->line("   Company name: <fg=gray>{$companyName}</>");
        }
        $this->line("   Provider: <fg=yellow>{$provider->getName()}</>");
        $this->newLine();

        try {
            $result = $provider->verify($vatNumber, $countryCode);

            $this->displayResult($result, $companyName);
        } catch (\Aichadigital\Lararoi\Exceptions\ApiUnavailableException $e) {
            $this->error('❌ Error during verification:');

            // Show more detailed error message if available
            $previous = $e->getPrevious();
            if ($previous && $previous->getMessage() !== $e->getMessage()) {
                $this->line("   {$previous->getMessage()}");
            } else {
                $this->line("   {$e->getMessage()}");
            }

            // Show full response for analysis (especially for SOAP/XML)
            $this->displayErrorResponse($provider, $e);

            $this->newLine();

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('❌ Error during verification:');
            $this->line("   {$e->getMessage()}");
            $this->newLine();

            // Show full response for analysis
            $this->displayErrorResponse($provider, $e);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Get provider selection using interactive prompt
     */
    protected function getProvider(): ?VatProviderInterface
    {
        $forcedProvider = $this->option('provider');

        if ($forcedProvider) {
            $provider = $this->providerManager->getProvider($forcedProvider);
            if (! $provider) {
                $this->error("❌ Provider '{$forcedProvider}' not found or not available.");
                $this->newLine();
                $this->listAvailableProviders();

                return null;
            }

            if (! $provider->isAvailable()) {
                $this->error("❌ Provider '{$forcedProvider}' is not available (missing configuration).");
                $this->newLine();
                $this->listAvailableProviders();

                return null;
            }

            return $provider;
        }

        // Interactive selection
        $availableProviders = $this->getAvailableProvidersList();

        if ($availableProviders->isEmpty()) {
            $this->error('❌ No providers are available. Please check your configuration.');
            $this->newLine();

            return null;
        }

        // Use Laravel Prompts if available, otherwise fallback to traditional method
        if (class_exists(\Laravel\Prompts\Prompt::class)) {
            $options = $availableProviders->map(fn ($p, $name) => "{$p['name']} ({$p['type']})")->values()->all();
            $selected = \Laravel\Prompts\select(
                label: 'Select a provider:',
                options: $options,
                default: 0
            );

            $selectedIndex = array_search($selected, $options);
            $providerName = $availableProviders->keys()->get($selectedIndex);
        } else {
            // Fallback to traditional method
            $this->info('Available providers:');
            $options = [];
            $index = 0;
            foreach ($availableProviders as $name => $info) {
                $options[$index] = $name;
                $this->line("  [{$index}] {$info['name']} ({$info['type']})");
                $index++;
            }
            $this->newLine();

            $selectedIndex = (int) $this->ask('Select provider number', '0');
            if (! isset($options[$selectedIndex])) {
                $this->error('Invalid selection.');

                return null;
            }
            $providerName = $options[$selectedIndex];
        }

        return $this->providerManager->getProvider($providerName);
    }

    /**
     * Get VAT number and country code
     */
    protected function getVatData(): ?array
    {
        $vatInput = $this->option('vat');
        $countryInput = $this->option('country');

        // If provided via options, use them
        if ($vatInput && $countryInput) {
            $vatNumber = $this->normalizeVatNumber($vatInput, $countryInput);

            return [
                'vat' => $vatNumber,
                'country' => strtoupper($countryInput),
            ];
        }

        // Interactive mode
        if (class_exists(\Laravel\Prompts\Prompt::class)) {
            $vatInput = \Laravel\Prompts\text(
                label: 'Enter VAT number:',
                placeholder: 'B12345678 or ESB12345678',
                required: true,
                validate: fn ($value) => empty(trim($value)) ? 'VAT number is required' : null
            );

            $countryInput = \Laravel\Prompts\text(
                label: 'Enter country code (2 letters):',
                placeholder: 'ES, DE, FR, etc.',
                default: 'ES',
                required: true,
                validate: fn ($value) => strlen($value) !== 2 ? 'Country code must be 2 letters' : null
            );
        } else {
            // Fallback
            $vatInput = $this->ask('Enter VAT number (with or without country prefix)', '');
            $countryInput = $this->ask('Enter country code (2 letters)', 'ES');
        }

        if (empty($vatInput) || empty($countryInput)) {
            $this->error('❌ VAT number and country code are required.');

            return null;
        }

        $vatNumber = $this->normalizeVatNumber($vatInput, $countryInput);
        $countryCode = strtoupper($countryInput);

        return [
            'vat' => $vatNumber,
            'country' => $countryCode,
        ];
    }

    /**
     * Get optional company name
     */
    protected function getCompanyName(): ?string
    {
        /** @var array<int, string|null>|string|null $nameOption */
        $nameOption = $this->option('name');

        if ($nameOption === null) {
            // Option not provided, ask interactively
        } elseif (is_array($nameOption)) {
            // Handle array case (from =* in signature)
            $nameInput = ! empty($nameOption) ? implode(' ', $nameOption) : '';

            return ! empty(trim($nameInput)) ? trim($nameInput) : null;
        } else {
            // Handle string case
            $nameInput = (string) $nameOption;

            return ! empty(trim($nameInput)) ? trim($nameInput) : null;
        }

        // Interactive mode - only if --name not provided at all
        if (class_exists(\Laravel\Prompts\Prompt::class)) {
            $nameInput = \Laravel\Prompts\text(
                label: 'Enter company name (optional, for validation):',
                placeholder: 'Leave empty to skip',
                default: ''
            );
        } else {
            $nameInput = $this->ask('Enter company name (optional, for validation)', '');
        }

        return ! empty(trim((string) $nameInput)) ? trim((string) $nameInput) : null;
    }

    /**
     * Normalize VAT number by removing country prefix if present
     */
    protected function normalizeVatNumber(string $vatInput, string $countryCode): string
    {
        $vatInput = strtoupper(trim($vatInput));
        $countryCode = strtoupper(trim($countryCode));

        // Remove country prefix if present (e.g., "ESB12345678" -> "B12345678")
        if (Str::startsWith($vatInput, $countryCode)) {
            $vatInput = Str::substr($vatInput, strlen($countryCode));
        }

        return $vatInput;
    }

    /**
     * Get list of available providers for selection
     */
    protected function getAvailableProvidersList(): \Illuminate\Support\Collection
    {
        $providers = $this->providerManager->getProviders();
        $available = collect();

        foreach ($providers as $name => $provider) {
            if ($provider->isAvailable()) {
                $available->put($name, [
                    'name' => $provider->getName(),
                    'type' => $provider->isFree() ? 'FREE' : 'PAID',
                    'provider' => $provider,
                ]);
            }
        }

        return $available;
    }

    /**
     * List available providers
     */
    protected function listAvailableProviders(): void
    {
        $this->info('Available providers:');
        $this->newLine();

        $freeProviders = $this->providerManager->getFreeProviders();
        $paidProviders = $this->providerManager->getPaidProviders();

        if ($freeProviders->isNotEmpty()) {
            $this->line('🆓 FREE Providers:');
            foreach ($freeProviders as $name => $provider) {
                $status = $provider->isAvailable() ? '✅' : '❌';
                $this->line("   {$status} {$name} ({$provider->getName()})");
            }
            $this->newLine();
        }

        if ($paidProviders->isNotEmpty()) {
            $this->line('💰 PAID Providers:');
            foreach ($paidProviders as $name => $provider) {
                $status = $provider->isAvailable() ? '✅' : '❌';
                $this->line("   {$status} {$name} ({$provider->getName()})");
            }
            $this->newLine();
        }
    }

    /**
     * Display verification result in a formatted way
     */
    protected function displayResult(array $result, ?string $expectedName = null): void
    {
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // Validity status
        if ($result['valid']) {
            $this->info('✅ VAT Number is VALID');
        } else {
            $this->error('❌ VAT Number is INVALID');
        }

        $this->newLine();
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // Details table
        $this->table(
            ['Field', 'Value'],
            [
                ['VAT Number', $result['vat_number'] ?? 'N/A'],
                ['Country Code', $result['country_code'] ?? 'N/A'],
                ['API Source', $result['api_source'] ?? 'N/A'],
                ['Company Name', $result['name'] ?? '<fg=gray>Not provided</>'],
                ['Address', $result['address'] ?? '<fg=gray>Not provided</>'],
                ['Request Date', $result['request_date'] ?? '<fg=gray>Not provided</>'],
            ]
        );

        // Name validation if provided
        if ($expectedName && $result['name']) {
            $this->newLine();
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('📝 Name Validation:');
            $this->newLine();

            $expectedNormalized = $this->normalizeCompanyName($expectedName);
            $actualNormalized = $this->normalizeCompanyName($result['name']);

            $similarity = $this->calculateSimilarity($expectedNormalized, $actualNormalized);

            if ($similarity >= 0.8) {
                $this->info("   ✅ Names match ({$similarity}% similarity)");
            } elseif ($similarity >= 0.5) {
                $this->warn("   ⚠️  Names are similar but not identical ({$similarity}% similarity)");
            } else {
                $this->error("   ❌ Names do not match ({$similarity}% similarity)");
            }

            $this->line("   Expected: <fg=cyan>{$expectedName}</>");
            $this->line("   Actual:   <fg=cyan>{$result['name']}</>");
        }

        // Raw response (for debugging)
        $this->newLine();
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📋 Raw Response:');
        $this->newLine();
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->newLine();
    }

    /**
     * Normalize company name for comparison
     */
    protected function normalizeCompanyName(string $name): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9\s]/i', '', $name)));
    }

    /**
     * Calculate similarity between two strings (simple Levenshtein-based)
     */
    protected function calculateSimilarity(string $str1, string $str2): float
    {
        if (empty($str1) && empty($str2)) {
            return 1.0;
        }

        if (empty($str1) || empty($str2)) {
            return 0.0;
        }

        $maxLen = max(strlen($str1), strlen($str2));
        $distance = levenshtein($str1, $str2);

        return 1 - ($distance / $maxLen);
    }

    /**
     * Display full error response for analysis (XML for SOAP, JSON for REST)
     */
    protected function displayErrorResponse(VatProviderInterface $provider, \Exception $e): void
    {
        $this->newLine();
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📋 Full Error Response (for analysis):');
        $this->newLine();

        // For SOAP providers, try to get XML trace
        if ($provider instanceof \Aichadigital\Lararoi\Providers\ViesSoapProvider) {
            $this->displaySoapError($e);
        } else {
            // For REST providers, show JSON if available
            $this->displayJsonError($e);
        }
    }

    /**
     * Display SOAP error with XML trace if available
     */
    protected function displaySoapError(\Exception $e): void
    {
        $previous = $e->getPrevious();

        // Try to extract SOAP trace from exception
        $soapTrace = null;
        if (isset($e->soapTrace)) {
            $soapTrace = $e->soapTrace;
        } elseif ($previous && isset($previous->soapTrace)) {
            $soapTrace = $previous->soapTrace;
        }

        // Try to extract SOAP trace from SoapFault
        if ($previous instanceof \SoapFault) {
            $this->line('<fg=yellow>SOAP Fault Details:</>');
            $this->line("   Code: {$previous->getCode()}");
            $this->line("   Message: {$previous->getMessage()}");
            $this->newLine();

            // Try to get SOAP fault detail
            if (isset($previous->detail)) {
                $this->line('<fg=yellow>SOAP Fault Detail:</>');
                $this->displayFormattedXml($previous->detail);
                $this->newLine();
            }
        }

        // Display SOAP request/response XML if available
        if ($soapTrace) {
            if (! empty($soapTrace['last_request'])) {
                $this->line('<fg=cyan>SOAP Request XML:</>');
                $this->newLine();
                $this->displayFormattedXml($soapTrace['last_request']);
                $this->newLine();
            }

            if (! empty($soapTrace['last_response'])) {
                $this->line('<fg=cyan>SOAP Response XML:</>');
                $this->newLine();
                $this->displayFormattedXml($soapTrace['last_response']);
                $this->newLine();
            }

            // Show full trace as JSON
            $this->line('<fg=yellow>Full SOAP Trace (JSON):</>');
            $this->line(json_encode($soapTrace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->newLine();
        }

        // Show exception details as JSON for analysis
        $errorData = [
            'exception_type' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];

        if ($previous) {
            $errorData['previous_exception'] = [
                'type' => get_class($previous),
                'message' => $previous->getMessage(),
                'code' => $previous->getCode(),
            ];
        }

        $this->line('<fg=yellow>Error Details (JSON):</>');
        $this->line(json_encode($errorData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Display JSON error response
     */
    protected function displayJsonError(\Exception $e): void
    {
        $errorData = [
            'exception_type' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];

        $previous = $e->getPrevious();
        if ($previous) {
            $errorData['previous_exception'] = [
                'type' => get_class($previous),
                'message' => $previous->getMessage(),
                'code' => $previous->getCode(),
            ];
        }

        $this->line(json_encode($errorData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Display formatted XML
     */
    protected function displayFormattedXml($xml): void
    {
        if (is_string($xml)) {
            $dom = new \DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;

            if (@$dom->loadXML($xml)) {
                $this->line($dom->saveXML());
            } else {
                $this->line($xml);
            }
        } elseif (is_object($xml)) {
            // Convert object to array and then to JSON for display
            $this->line(json_encode($xml, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line(json_encode($xml, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }
}
