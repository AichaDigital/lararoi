<?php

use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use Aichadigital\Lararoi\Providers\ViesApiProvider;
use Illuminate\Support\Facades\Http;

describe('ViesApiProvider - Properties', function () {
    it('indicates it is NOT a free provider', function () {
        $provider = new ViesApiProvider;

        expect($provider->isFree())->toBeFalse();
    });

    it('returns correct provider name', function () {
        $provider = new ViesApiProvider;

        expect($provider->getName())->toBe('VIESAPI');
    });

    it('is not available without API key', function () {
        $provider = new ViesApiProvider;

        expect($provider->isAvailable())->toBeFalse();
    });

    it('is available with API key configured', function () {
        $provider = new ViesApiProvider('test_api_key');

        expect($provider->isAvailable())->toBeTrue();
    });
});

describe('ViesApiProvider - API Response Handling', function () {
    it('processes valid VAT response correctly', function () {
        $stubResponse = json_decode(file_get_contents(__DIR__.'/../../stubs/viesapi_valid.json'), true);

        Http::fake([
            'viesapi.eu/*' => Http::response($stubResponse, 200),
        ]);

        $provider = new ViesApiProvider('test_key', 'test_secret');
        $result = $provider->verify('6364992H', 'IE');

        expect($result)->toBeArray();
        expect($result['valid'])->toBeTrue();
        expect($result['vat_number'])->toBe('6364992H');
        expect($result['country_code'])->toBe('IE');
        expect($result['api_source'])->toBe('VIESAPI');
        expect($result['name'])->toBe('ADOBE SYSTEMS SOFTWARE IRELAND LTD');
        expect($result['address'])->toContain('RIVER WALK');
        expect($result['request_date'])->toBe('2025-11-15');
    });

    it('processes invalid VAT response correctly', function () {
        $stubResponse = json_decode(file_get_contents(__DIR__.'/../../stubs/viesapi_invalid.json'), true);

        Http::fake([
            'viesapi.eu/*' => Http::response($stubResponse, 200),
        ]);

        $provider = new ViesApiProvider('test_key', 'test_secret');
        $result = $provider->verify('B00000000', 'ES');

        expect($result)->toBeArray();
        expect($result['valid'])->toBeFalse();
        expect($result['api_source'])->toBe('VIESAPI');
    });

    it('handles error code 22 as invalid VAT', function () {
        $stubResponse = json_decode(file_get_contents(__DIR__.'/../../stubs/viesapi_error_22.json'), true);

        Http::fake([
            'viesapi.eu/*' => Http::response($stubResponse, 404),
        ]);

        $provider = new ViesApiProvider('test_key', 'test_secret');
        $result = $provider->verify('INVALID', 'ES');

        expect($result)->toBeArray();
        expect($result['valid'])->toBeFalse();
        expect($result['vat_number'])->toBe('INVALID');
        expect($result['country_code'])->toBe('ES');
        expect($result['api_source'])->toBe('VIESAPI');
    });

    it('handles error code 205 as invalid VAT', function () {
        $errorResponse = [
            'error' => [
                'uid' => 'abc123',
                'code' => 205,
                'description' => 'EU VAT ID is invalid',
                'details' => '',
            ],
        ];

        Http::fake([
            'viesapi.eu/*' => Http::response($errorResponse, 404),
        ]);

        $provider = new ViesApiProvider('test_key', 'test_secret');
        $result = $provider->verify('INVALID', 'DE');

        expect($result)->toBeArray();
        expect($result['valid'])->toBeFalse();
    });

    it('throws exception for error code 106 (invalid account type)', function () {
        $errorResponse = [
            'error' => [
                'uid' => 'xyz789',
                'code' => 106,
                'description' => 'Invalid account type',
                'details' => '',
            ],
        ];

        Http::fake([
            'viesapi.eu/*' => Http::response($errorResponse, 401),
        ]);

        $provider = new ViesApiProvider('test_key', 'test_secret');

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });

    it('throws exception for error code 35 (no access)', function () {
        $errorResponse = [
            'error' => [
                'uid' => 'def456',
                'code' => 35,
                'description' => 'No access, query authorization required',
                'details' => '',
            ],
        ];

        Http::fake([
            'viesapi.eu/*' => Http::response($errorResponse, 401),
        ]);

        $provider = new ViesApiProvider('test_key', 'test_secret');

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });

    it('throws exception on connection error', function () {
        Http::fake([
            'viesapi.eu/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
        ]);

        $provider = new ViesApiProvider('test_key', 'test_secret');

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });

    it('throws exception when API key not configured', function () {
        $provider = new ViesApiProvider;

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });
});

describe('ViesApiProvider - Response Structure', function () {
    it('returns array with required keys for valid response', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/viesapi_valid.json'), true);

        expect($stub)->toBeArray();
        expect($stub)->toHaveKey('valid');
        expect($stub)->toHaveKey('vatNumber');
        expect($stub)->toHaveKey('countryCode');
        expect($stub)->toHaveKey('traderName');
        expect($stub)->toHaveKey('traderAddress');
        expect($stub['valid'])->toBeTrue();
        expect($stub['traderName'])->not->toBeNull();
    });

    it('returns array with valid=false for invalid VAT', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/viesapi_invalid.json'), true);

        expect($stub)->toBeArray();
        expect($stub)->toHaveKey('valid');
        expect($stub['valid'])->toBeFalse();
    });

    it('error code 22 stub has correct structure', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/viesapi_error_22.json'), true);

        expect($stub)->toBeArray();
        expect($stub)->toHaveKey('error');
        expect($stub['error'])->toHaveKey('code');
        expect($stub['error']['code'])->toBe(22);
        expect($stub['error']['description'])->toContain('invalid');
    });
});
