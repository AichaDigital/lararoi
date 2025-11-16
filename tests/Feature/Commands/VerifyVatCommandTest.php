<?php

use Illuminate\Support\Facades\Http;

describe('VerifyVatCommand - Basic Execution', function () {
    it('can execute with valid VAT and country parameters', function () {
        Http::fake([
            'ec.europa.eu/*' => Http::response([
                'isValid' => true,
                'name' => 'TEST COMPANY LTD',
                'address' => '123 TEST STREET',
                'vatNumber' => 'B12345678',
                'countryCode' => 'ES',
                'requestDate' => '2025-11-16',
            ], 200),
        ]);

        $this->artisan('lararoi:verify', [
            '--vat' => 'B12345678',
            '--country' => 'ES',
            '--provider' => 'vies_rest',
        ])
            ->expectsOutputToContain('VAT Number Verification')
            ->expectsOutputToContain('B12345678')
            ->assertExitCode(0);
    });

    it('handles invalid VAT number gracefully', function () {
        Http::fake([
            'ec.europa.eu/*' => Http::response([
                'isValid' => false,
                'vatNumber' => 'INVALID',
                'countryCode' => 'ES',
            ], 200),
        ]);

        $this->artisan('lararoi:verify', [
            '--vat' => 'INVALID',
            '--country' => 'ES',
            '--provider' => 'vies_rest',
        ])
            ->expectsOutputToContain('VAT Number Verification')
            ->assertExitCode(0);
    });

    it('handles API errors gracefully', function () {
        Http::fake([
            'ec.europa.eu/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
            },
        ]);

        $this->artisan('lararoi:verify', [
            '--vat' => 'B12345678',
            '--country' => 'ES',
            '--provider' => 'vies_rest',
        ])
            ->expectsOutputToContain('Error')
            ->assertExitCode(1);
    });

    it('accepts VAT number with country prefix', function () {
        Http::fake([
            'ec.europa.eu/*' => Http::response([
                'isValid' => true,
                'name' => 'TEST COMPANY',
                'address' => 'TEST ADDRESS',
                'vatNumber' => 'B12345678',
                'countryCode' => 'ES',
            ], 200),
        ]);

        $this->artisan('lararoi:verify', [
            '--vat' => 'ESB12345678',
            '--country' => 'ES',
            '--provider' => 'vies_rest',
        ])
            ->expectsOutputToContain('B12345678')
            ->assertExitCode(0);
    });
});

describe('VerifyVatCommand - Company Name Validation', function () {
    it('validates company name when provided', function () {
        Http::fake([
            'ec.europa.eu/*' => Http::response([
                'isValid' => true,
                'name' => 'ADOBE SYSTEMS SOFTWARE IRELAND LTD',
                'address' => 'TEST ADDRESS',
                'vatNumber' => '6364992H',
                'countryCode' => 'IE',
            ], 200),
        ]);

        $this->artisan('lararoi:verify', [
            '--vat' => '6364992H',
            '--country' => 'IE',
            '--provider' => 'vies_rest',
            '--name' => ['Adobe', 'Systems'],
        ])
            ->expectsOutputToContain('ADOBE SYSTEMS')
            ->assertExitCode(0);
    });

    it('handles name mismatch detection', function () {
        Http::fake([
            'ec.europa.eu/*' => Http::response([
                'isValid' => true,
                'name' => 'DIFFERENT COMPANY NAME LTD',
                'address' => 'TEST ADDRESS',
                'vatNumber' => 'B12345678',
                'countryCode' => 'ES',
            ], 200),
        ]);

        $this->artisan('lararoi:verify', [
            '--vat' => 'B12345678',
            '--country' => 'ES',
            '--provider' => 'vies_rest',
            '--name' => ['Expected', 'Company', 'Name'],
        ])
            ->expectsOutputToContain('DIFFERENT COMPANY')
            ->assertExitCode(0);
    });
});

describe('VerifyVatCommand - Provider Selection', function () {
    it('uses specified provider when given', function () {
        Http::fake([
            'ec.europa.eu/*' => Http::response([
                'isValid' => true,
                'name' => 'TEST',
                'vatNumber' => 'B12345678',
                'countryCode' => 'ES',
            ], 200),
        ]);

        $this->artisan('lararoi:verify', [
            '--vat' => 'B12345678',
            '--country' => 'ES',
            '--provider' => 'vies_rest',
        ])
            ->expectsOutputToContain('Verifying')
            ->expectsOutputToContain('B12345678')
            ->assertExitCode(0);
    });

    it('handles invalid provider gracefully', function () {
        $this->artisan('lararoi:verify', [
            '--vat' => 'B12345678',
            '--country' => 'ES',
            '--provider' => 'invalid_provider',
        ])
            ->assertExitCode(1);
    });
});

describe('VerifyVatCommand - Helper Methods', function () {
    it('normalizes VAT numbers correctly', function () {
        $command = new \Aichadigital\Lararoi\Console\Commands\VerifyVatCommand(
            app(\Aichadigital\Lararoi\Services\VatProviderManager::class)
        );

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('normalizeVatNumber');
        $method->setAccessible(true);

        // Test removing country prefix
        $result = $method->invoke($command, 'ESB12345678', 'ES');
        expect($result)->toBe('B12345678');

        // Test uppercase conversion
        $result = $method->invoke($command, 'b12345678', 'ES');
        expect($result)->toBe('B12345678');

        // Test trimming
        $result = $method->invoke($command, '  B12345678  ', 'ES');
        expect($result)->toBe('B12345678');
    });

    it('normalizes company names correctly', function () {
        $command = new \Aichadigital\Lararoi\Console\Commands\VerifyVatCommand(
            app(\Aichadigital\Lararoi\Services\VatProviderManager::class)
        );

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('normalizeCompanyName');
        $method->setAccessible(true);

        // Test lowercase conversion and special char removal
        $result = $method->invoke($command, 'TEST   COMPANY   LTD');
        expect($result)->toBe('test   company   ltd');

        // Test already lowercase
        $result = $method->invoke($command, 'test company ltd');
        expect($result)->toBe('test company ltd');

        // Test special characters removal
        $result = $method->invoke($command, 'TEST & COMPANY S.L.');
        expect($result)->toBe('test  company sl');
    });

    it('calculates string similarity correctly', function () {
        $command = new \Aichadigital\Lararoi\Console\Commands\VerifyVatCommand(
            app(\Aichadigital\Lararoi\Services\VatProviderManager::class)
        );

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('calculateSimilarity');
        $method->setAccessible(true);

        // Identical strings
        $similarity = $method->invoke($command, 'ADOBE SYSTEMS', 'ADOBE SYSTEMS');
        expect($similarity)->toBe(1.0);

        // Similar strings
        $similarity = $method->invoke($command, 'ADOBE SYSTEMS LTD', 'ADOBE SYSTEMS');
        expect($similarity)->toBeGreaterThan(0.7);

        // Different strings
        $similarity = $method->invoke($command, 'ADOBE SYSTEMS', 'MICROSOFT CORP');
        expect($similarity)->toBeLessThan(0.5);

        // Empty strings
        $similarity = $method->invoke($command, '', '');
        expect($similarity)->toBe(1.0);

        // One empty string
        $similarity = $method->invoke($command, 'TEST', '');
        expect($similarity)->toBe(0.0);
    });
});

describe('VerifyVatCommand - Output Formatting', function () {
    it('displays valid result with all fields', function () {
        Http::fake([
            'ec.europa.eu/*' => Http::response([
                'isValid' => true,
                'name' => 'TEST COMPANY LTD',
                'address' => '123 TEST STREET, CITY',
                'vatNumber' => 'B12345678',
                'countryCode' => 'ES',
                'requestDate' => '2025-11-16',
            ], 200),
        ]);

        $this->artisan('lararoi:verify', [
            '--vat' => 'B12345678',
            '--country' => 'ES',
            '--provider' => 'vies_rest',
        ])
            ->expectsOutputToContain('TEST COMPANY LTD')
            ->expectsOutputToContain('123 TEST STREET')
            ->expectsOutputToContain('B12345678')
            ->assertExitCode(0);
    });

    it('handles missing optional fields gracefully', function () {
        Http::fake([
            'ec.europa.eu/*' => Http::response([
                'isValid' => false,
                'vatNumber' => 'INVALID',
                'countryCode' => 'ES',
            ], 200),
        ]);

        $this->artisan('lararoi:verify', [
            '--vat' => 'INVALID',
            '--country' => 'ES',
            '--provider' => 'vies_rest',
        ])
            ->expectsOutputToContain('INVALID')
            ->assertExitCode(0);
    });
});
