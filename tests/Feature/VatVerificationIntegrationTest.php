<?php

use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;
use Aichadigital\Lararoi\Models\VatVerification;

describe('VatVerification - Complete Integration', function () {
    it('verifies VAT and saves to database', function () {
        // Load stub data
        $stubData = json_decode(file_get_contents(__DIR__.'/../stubs/vies_rest_valid.json'), true);

        // Create a mock provider that returns stub data
        $mockProvider = Mockery::mock(\Aichadigital\Lararoi\Contracts\VatProviderInterface::class);
        $mockProvider->shouldReceive('isAvailable')->andReturn(true);
        $mockProvider->shouldReceive('getName')->andReturn('MOCK_PROVIDER');
        $mockProvider->shouldReceive('isFree')->andReturn(true);
        $mockProvider->shouldReceive('verify')
            ->andReturn([
                'valid' => true,
                'vat_number' => 'B12345678',
                'country_code' => 'ES',
                'name' => 'TEST COMPANY SL',
                'address' => 'TEST ADDRESS 123',
                'request_date' => now()->toIso8601String(),
                'api_source' => 'MOCK_PROVIDER',
            ]);

        // Replace provider manager with our mock
        $manager = app(\Aichadigital\Lararoi\Services\VatProviderManager::class);
        $manager->register('mock', $mockProvider);
        $manager->setProviderOrder(['mock']);

        $service = app(VatVerificationServiceInterface::class);
        $result = $service->verifyVatNumber('B12345678', 'ES');

        expect($result)->toBeArray();
        expect($result['is_valid'])->toBeBool();

        // Verify it was saved to DB
        $verification = VatVerification::where('vat_code', $result['vat_code'])
            ->where('country_code', $result['country_code'])
            ->first();

        expect($verification)->not->toBeNull();
        expect($verification->is_valid)->toBe($result['is_valid']);
    });

    it('uses database cache for repeated verifications', function () {
        // Create a mock that will only be called once
        $mockProvider = Mockery::mock(\Aichadigital\Lararoi\Contracts\VatProviderInterface::class);
        $mockProvider->shouldReceive('isAvailable')->andReturn(true);
        $mockProvider->shouldReceive('getName')->andReturn('MOCK_PROVIDER');
        $mockProvider->shouldReceive('isFree')->andReturn(true);
        $mockProvider->shouldReceive('verify')
            ->once() // Should only be called once, second time should use cache
            ->andReturn([
                'valid' => true,
                'vat_number' => 'B87654321',
                'country_code' => 'ES',
                'name' => 'CACHED COMPANY SL',
                'address' => 'CACHED ADDRESS',
                'request_date' => now()->toIso8601String(),
                'api_source' => 'MOCK_PROVIDER',
            ]);

        $manager = app(\Aichadigital\Lararoi\Services\VatProviderManager::class);
        $manager->register('mock', $mockProvider);
        $manager->setProviderOrder(['mock']);

        $service = app(VatVerificationServiceInterface::class);

        // First verification - calls API
        $result1 = $service->verifyVatNumber('B87654321', 'ES');

        // Second verification - should use cache
        $result2 = $service->verifyVatNumber('B87654321', 'ES');

        expect($result2['cached'])->toBeTrue();
        expect($result1['vat_code'])->toBe($result2['vat_code']);
    });

    it('stores verification metadata correctly', function () {
        $mockProvider = Mockery::mock(\Aichadigital\Lararoi\Contracts\VatProviderInterface::class);
        $mockProvider->shouldReceive('isAvailable')->andReturn(true);
        $mockProvider->shouldReceive('getName')->andReturn('MOCK_PROVIDER');
        $mockProvider->shouldReceive('isFree')->andReturn(true);
        $mockProvider->shouldReceive('verify')
            ->andReturn([
                'valid' => true,
                'vat_number' => 'B11111111',
                'country_code' => 'ES',
                'name' => 'METADATA TEST SL',
                'address' => 'METADATA ADDRESS',
                'request_date' => now()->toIso8601String(),
                'api_source' => 'MOCK_PROVIDER',
            ]);

        $manager = app(\Aichadigital\Lararoi\Services\VatProviderManager::class);
        $manager->register('mock', $mockProvider);
        $manager->setProviderOrder(['mock']);

        $service = app(VatVerificationServiceInterface::class);
        $result = $service->verifyVatNumber('B11111111', 'ES');

        $verification = VatVerification::where('vat_code', $result['vat_code'])->first();

        expect($verification)->not->toBeNull();
        expect($verification->api_source)->toBe('MOCK_PROVIDER');
        expect($verification->verified_at)->not->toBeNull();
        expect($verification->company_name)->toBe('METADATA TEST SL');
    });
});

describe('VatVerification - Model Scopes', function () {
    it('filters by VAT code', function () {
        // Create a test verification
        VatVerification::create([
            'vat_code' => 'TEST123',
            'country_code' => 'ES',
            'is_valid' => true,
            'company_name' => 'Test Company',
            'api_source' => 'TEST',
            'verified_at' => now(),
        ]);

        $verification = VatVerification::byVatCode('TEST123')->first();

        expect($verification)->not->toBeNull();
        expect($verification->vat_code)->toBe('TEST123');
    });

    it('filters by country', function () {
        // Create test verifications
        VatVerification::create([
            'vat_code' => 'TESTDE',
            'country_code' => 'DE',
            'is_valid' => true,
            'api_source' => 'TEST',
            'verified_at' => now(),
        ]);

        $verifications = VatVerification::byCountry('DE')->get();

        expect($verifications)->not->toBeEmpty();
        expect($verifications->first()->country_code)->toBe('DE');
    });

    it('filters valid verifications', function () {
        // Create test verifications
        VatVerification::create([
            'vat_code' => 'VALIDTEST',
            'country_code' => 'ES',
            'is_valid' => true,
            'api_source' => 'TEST',
            'verified_at' => now(),
        ]);

        VatVerification::create([
            'vat_code' => 'INVALIDTEST',
            'country_code' => 'ES',
            'is_valid' => false,
            'api_source' => 'TEST',
            'verified_at' => now(),
        ]);

        $validVerifications = VatVerification::valid()->get();

        expect($validVerifications)->not->toBeEmpty();
        foreach ($validVerifications as $verification) {
            expect($verification->is_valid)->toBeTrue();
        }
    });
});
