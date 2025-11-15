<?php

namespace Aichadigital\Lararoi\Console\Commands\Dev;

use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Developer command: Test VAT numbers from file
 *
 * Reads VAT numbers from tests/stubs/vat_numbers.txt and verifies them
 * using the complete service (with cache and fallback).
 */
class TestVatFromFileCommand extends Command
{
    protected $signature = 'lararoi:dev:test-from-file
                            {--file=tests/stubs/vat_numbers.txt : Path to file with VAT numbers}
                            {--provider= : Force a specific provider}
                            {--json : Show responses in JSON}';

    protected $description = 'Test VAT numbers from file (development only)';

    protected VatVerificationServiceInterface $vatService;

    public function __construct(VatVerificationServiceInterface $vatService)
    {
        parent::__construct();
        $this->vatService = $vatService;
    }

    public function handle(): int
    {
        $filePath = $this->option('file');

        if (! File::exists($filePath)) {
            $this->error("❌ File not found: {$filePath}");

            return self::FAILURE;
        }

        $content = File::get($filePath);
        $lines = explode("\n", $content);

        $this->info("📄 Reading VAT numbers from: <comment>{$filePath}</comment>");
        $this->newLine();

        $results = [];
        $lineNumber = 0;

        foreach ($lines as $line) {
            $lineNumber++;
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Parse line: VAT_NUMBER [COUNTRY] [DESCRIPTION]
            $parts = preg_split('/\s+/', $line, 3);
            $vatFull = $parts[0] ?? '';

            if (empty($vatFull)) {
                continue;
            }

            // Extract country and VAT number
            if (strlen($vatFull) >= 4) {
                $countryCode = strtoupper(substr($vatFull, 0, 2));
                $vatNumber = substr($vatFull, 2);
            } else {
                $this->warn("⚠️  Line {$lineNumber}: Invalid format '{$vatFull}'");

                continue;
            }

            $description = $parts[2] ?? '';

            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("Line {$lineNumber}: <comment>{$countryCode}{$vatNumber}</comment>");
            if ($description) {
                $this->line("Description: <comment>{$description}</comment>");
            }

            try {
                $startTime = microtime(true);
                $result = $this->vatService->verifyVatNumber($vatNumber, $countryCode);
                $duration = round((microtime(true) - $startTime) * 1000, 2);

                $this->line('  ✅ Valid: '.($result['is_valid'] ? '<fg=green>Yes</fg=green>' : '<fg=red>No</fg=red>'));
                $this->line("  📡 Source: <comment>{$result['api_source']}</comment>");
                $this->line('  💾 Cached: '.($result['cached'] ? '<fg=cyan>Yes</fg=cyan>' : '<fg=yellow>No</fg=yellow>'));
                $this->line("  ⏱️  Time: {$duration}ms");

                if ($result['company_name']) {
                    $this->line('  🏢 Name: '.$result['company_name']);
                }

                $results[] = [
                    'line' => $lineNumber,
                    'vat' => $countryCode.$vatNumber,
                    'result' => $result,
                    'duration' => $duration,
                    'success' => true,
                ];
            } catch (\Exception $e) {
                $this->error('  ❌ Error: '.$e->getMessage());
                $results[] = [
                    'line' => $lineNumber,
                    'vat' => $countryCode.$vatNumber,
                    'error' => $e->getMessage(),
                    'success' => false,
                ];
            }

            $this->newLine();
        }

        // Summary
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 Summary:');
        $total = count($results);
        $success = collect($results)->where('success', true)->count();
        $errors = collect($results)->where('success', false)->count();
        $valid = collect($results)->where('result.is_valid', true)->count();
        $invalid = collect($results)->where('result.is_valid', false)->count();

        $this->line("  Total processed: {$total}");
        $this->line("  ✅ Successful: {$success}");
        $this->line("  ❌ Errors: {$errors}");
        $this->line("  ✓ Valid: {$valid}");
        $this->line("  ✗ Invalid: {$invalid}");

        if ($this->option('json')) {
            $this->newLine();
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
