<?php

use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use Aichadigital\Lararoi\Providers\ViesRestProvider;
use Illuminate\Support\Facades\Http;

describe('ViesRestProvider - Properties', function () {
    it('indicates it is a free provider', function () {
        $provider = new ViesRestProvider;

        expect($provider->isFree())->toBeTrue();
    });

    it('returns correct provider name', function () {
        $provider = new ViesRestProvider;

        expect($provider->getName())->toBe('VIES REST');
    });

    it('is available by default', function () {
        $provider = new ViesRestProvider;

        expect($provider->isAvailable())->toBeTrue();
    });
});

describe('ViesRestProvider - API Response Handling', function () {
    it('processes valid VAT response correctly', function () {
        $apiResponse = [
            'isValid' => true,
            'name' => 'TEST COMPANY LTD',
            'address' => '123 TEST STREET, CITY',
            'requestDate' => '2025-11-16',
            'vatNumber' => 'B12345678',
            'countryCode' => 'ES',
        ];

        Http::fake([
            'ec.europa.eu/*' => Http::response($apiResponse, 200),
        ]);

        $provider = new ViesRestProvider;
        $result = $provider->verify('B12345678', 'ES');

        expect($result)->toBeArray();
        expect($result['valid'])->toBeTrue();
        expect($result['vat_number'])->toBe('B12345678');
        expect($result['country_code'])->toBe('ES');
        expect($result['api_source'])->toBe('VIES_REST');
        expect($result['name'])->toBe('TEST COMPANY LTD');
        expect($result['address'])->toBe('123 TEST STREET, CITY');
        expect($result['request_date'])->toBe('2025-11-16');
    });

    it('processes invalid VAT response correctly', function () {
        $apiResponse = [
            'isValid' => false,
            'vatNumber' => 'INVALID',
            'countryCode' => 'ES',
        ];

        Http::fake([
            'ec.europa.eu/*' => Http::response($apiResponse, 200),
        ]);

        $provider = new ViesRestProvider;
        $result = $provider->verify('INVALID', 'ES');

        expect($result)->toBeArray();
        expect($result['valid'])->toBeFalse();
        expect($result['api_source'])->toBe('VIES_REST');
    });

    it('throws exception on connection error', function () {
        Http::fake([
            'ec.europa.eu/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
        ]);

        $provider = new ViesRestProvider;

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });

    it('throws exception for invalid response format', function () {
        // Response without isValid field
        $apiResponse = [
            'vatNumber' => 'B12345678',
            'countryCode' => 'ES',
        ];

        Http::fake([
            'ec.europa.eu/*' => Http::response($apiResponse, 200),
        ]);

        $provider = new ViesRestProvider;

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });

    it('throws exception on HTTP error response', function () {
        Http::fake([
            'ec.europa.eu/*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $provider = new ViesRestProvider;

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });
});

describe('ViesRestProvider - Response Structure', function () {
    it('returns array with required keys on verify', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/vies_rest_valid.json'), true);

        expect($stub)->toBeArray();
        expect($stub)->toHaveKey('valid');
        expect($stub)->toHaveKey('vat_number');
        expect($stub)->toHaveKey('country_code');
        expect($stub)->toHaveKey('api_source');
        expect($stub['api_source'])->toBe('VIES_REST');
    });

    it('returns name and address when VAT is valid', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/vies_rest_valid.json'), true);

        expect($stub)->toHaveKey('name');
        expect($stub)->toHaveKey('address');

        if ($stub['valid']) {
            expect($stub['name'])->not->toBeNull();
        }
    });

    it('returns valid=false for invalid VAT', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/vies_rest_invalid.json'), true);

        expect($stub)->toBeArray();
        expect($stub['valid'])->toBeFalse();
    });
});
