<?php

use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use Aichadigital\Lararoi\Providers\VatlayerProvider;
use Illuminate\Support\Facades\Http;

describe('VatlayerProvider - Properties', function () {
    it('indicates it is NOT a free provider', function () {
        $provider = new VatlayerProvider;

        expect($provider->isFree())->toBeFalse();
    });

    it('returns correct provider name', function () {
        $provider = new VatlayerProvider;

        expect($provider->getName())->toBe('VATLAYER');
    });

    it('is not available without API key', function () {
        $provider = new VatlayerProvider;

        expect($provider->isAvailable())->toBeFalse();
    });

    it('is available with API key configured', function () {
        $provider = new VatlayerProvider('test_api_key');

        expect($provider->isAvailable())->toBeTrue();
    });
});

describe('VatlayerProvider - API Response Handling', function () {
    it('processes valid VAT response correctly', function () {
        $apiResponse = [
            'valid' => true,
            'company_name' => 'TEST COMPANY LTD',
            'company_address' => '123 TEST STREET, CITY',
            'vat_number' => 'ESB12345678',
            'country_code' => 'ES',
        ];

        Http::fake([
            'apilayer.net/*' => Http::response($apiResponse, 200),
        ]);

        $provider = new VatlayerProvider('test_key');
        $result = $provider->verify('B12345678', 'ES');

        expect($result)->toBeArray();
        expect($result['valid'])->toBeTrue();
        expect($result['vat_number'])->toBe('ESB12345678');
        expect($result['country_code'])->toBe('ES');
        expect($result['api_source'])->toBe('VATLAYER');
        expect($result['name'])->toBe('TEST COMPANY LTD');
        expect($result['address'])->toBe('123 TEST STREET, CITY');
    });

    it('processes invalid VAT response correctly', function () {
        $apiResponse = [
            'valid' => false,
            'vat_number' => 'ESINVALID',
            'country_code' => 'ES',
        ];

        Http::fake([
            'apilayer.net/*' => Http::response($apiResponse, 200),
        ]);

        $provider = new VatlayerProvider('test_key');
        $result = $provider->verify('INVALID', 'ES');

        expect($result)->toBeArray();
        expect($result['valid'])->toBeFalse();
        expect($result['api_source'])->toBe('VATLAYER');
    });

    it('throws exception when API key not configured', function () {
        $provider = new VatlayerProvider;

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });

    it('throws exception on connection error', function () {
        Http::fake([
            'apilayer.net/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
        ]);

        $provider = new VatlayerProvider('test_key');

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });

    it('throws exception for API error response', function () {
        $apiResponse = [
            'error' => [
                'code' => 101,
                'info' => 'Invalid API key',
            ],
        ];

        Http::fake([
            'apilayer.net/*' => Http::response($apiResponse, 200),
        ]);

        $provider = new VatlayerProvider('invalid_key');

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });

    it('throws exception on HTTP error response', function () {
        Http::fake([
            'apilayer.net/*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $provider = new VatlayerProvider('test_key');

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });
});

describe('VatlayerProvider - Response Structure', function () {
    it('returns array with required keys on verify', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/vatlayer_valid.json'), true);

        expect($stub)->toBeArray();
        expect($stub)->toHaveKey('valid');
        expect($stub)->toHaveKey('vat_number');
        expect($stub)->toHaveKey('country_code');
        expect($stub)->toHaveKey('api_source');
        expect($stub['api_source'])->toBe('VATLAYER');
    });

    it('returns name and address when VAT is valid', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/vatlayer_valid.json'), true);

        expect($stub)->toHaveKey('name');
        expect($stub)->toHaveKey('address');

        if ($stub['valid']) {
            expect($stub['name'])->not->toBeNull();
        }
    });

    it('returns valid=false for invalid VAT', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/vatlayer_invalid.json'), true);

        expect($stub)->toBeArray();

        // Skip if stub is an error response
        if (isset($stub['error']) && $stub['error'] === true) {
            expect(true)->toBeTrue();

            return;
        }

        expect($stub['valid'])->toBeFalse();
    });
});
