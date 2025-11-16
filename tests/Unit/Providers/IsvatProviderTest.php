<?php

use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use Aichadigital\Lararoi\Providers\IsvatProvider;
use Illuminate\Support\Facades\Http;

describe('IsvatProvider - Properties', function () {
    it('indicates it is a free provider', function () {
        $provider = new IsvatProvider;

        expect($provider->isFree())->toBeTrue();
    });

    it('returns correct provider name', function () {
        $provider = new IsvatProvider;

        expect($provider->getName())->toBe('ISVAT');
    });

    it('is available by default', function () {
        $provider = new IsvatProvider;

        expect($provider->isAvailable())->toBeTrue();
    });
});

describe('IsvatProvider - API Response Handling', function () {
    it('normalizes valid VAT response with array values', function () {
        $stubResponse = json_decode(file_get_contents(__DIR__.'/../../stubs/isvat_valid.json'), true);

        Http::fake([
            'www.isvat.eu/*' => Http::response($stubResponse, 200),
        ]);

        $provider = new IsvatProvider;
        $result = $provider->verify('6364992H', 'IE');

        expect($result)->toBeArray();
        expect($result['valid'])->toBeTrue();
        expect($result['vat_number'])->toBe('6364992H');
        expect($result['country_code'])->toBe('IE');
        expect($result['api_source'])->toBe('ISVAT');

        // Verify that name and address are normalized from arrays to strings
        expect($result['name'])->toBeString();
        expect($result['name'])->toBe('ADOBE SYSTEMS SOFTWARE IRELAND LTD');
        expect($result['address'])->toBeString();
        expect($result['address'])->toBe('4 - 6 RIVER WALK, CITYWEST BUSINESS CAMPUS, SAGGART, DUBLIN 24');
    });

    it('handles invalid VAT response', function () {
        $stubResponse = json_decode(file_get_contents(__DIR__.'/../../stubs/isvat_invalid.json'), true);

        Http::fake([
            'www.isvat.eu/*' => Http::response($stubResponse, 200),
        ]);

        $provider = new IsvatProvider;
        $result = $provider->verify('B00000000', 'ES');

        expect($result)->toBeArray();
        expect($result['valid'])->toBeFalse();
        expect($result['api_source'])->toBe('ISVAT');
    });

    it('handles null values for name and address', function () {
        $responseData = [
            'valid' => false,
            'vatNumber' => 'INVALID',
            'countryCode' => 'ES',
        ];

        Http::fake([
            'www.isvat.eu/*' => Http::response($responseData, 200),
        ]);

        $provider = new IsvatProvider;
        $result = $provider->verify('INVALID', 'ES');

        expect($result['name'])->toBeNull();
        expect($result['address'])->toBeNull();
    });

    it('throws ApiUnavailableException on connection error', function () {
        Http::fake([
            'www.isvat.eu/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
        ]);

        $provider = new IsvatProvider;

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });

    it('handles 404 response with valid=false as invalid VAT', function () {
        // ISVAT returns 404 with {"valid":false} for invalid VAT numbers
        Http::fake([
            'www.isvat.eu/*' => Http::response(['valid' => false], 404),
        ]);

        $provider = new IsvatProvider;
        $result = $provider->verify('INVALID123', 'ES');

        expect($result)->toBeArray();
        expect($result['valid'])->toBeFalse();
        expect($result['vat_number'])->toBe('INVALID123');
        expect($result['country_code'])->toBe('ES');
        expect($result['api_source'])->toBe('ISVAT');
    });

    it('throws exception on 404 without valid JSON response', function () {
        // If 404 doesn't contain valid JSON structure, it should throw exception
        Http::fake([
            'www.isvat.eu/*' => Http::response('Invalid HTML page', 404),
        ]);

        $provider = new IsvatProvider;

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });

    it('throws exception on 500 with ReturnCode 9999 (invalid input)', function () {
        // ISVAT returns 500 with ReturnCode: "9999" for invalid country codes
        $responseBody = [
            'CacheDate' => 'live',
            'RequestCommand' => 'checkVat',
            'ReturnCode' => '9999',
            'ReturnText' => 'INVALID_INPUT',
        ];

        Http::fake([
            'www.isvat.eu/*' => Http::response($responseBody, 500),
        ]);

        $provider = new IsvatProvider;

        expect(fn () => $provider->verify('123456789', 'XX'))
            ->toThrow(ApiUnavailableException::class);
    });

    it('handles response with extra fields gracefully', function () {
        // ISVAT API includes extra fields like rate_limit, cache that should be ignored
        $responseData = [
            'valid' => true,
            'cache' => ['0' => '2025-11-15T18:18:08CET'],
            'name' => ['0' => 'TEST COMPANY'],
            'address' => ['0' => 'TEST ADDRESS'],
            'vatNumber' => 'B12345678',
            'countryCode' => 'ES',
            'rate_limit' => 95,
            'rate_info' => 'Max. 100 request per month',
        ];

        Http::fake([
            'www.isvat.eu/*' => Http::response($responseData, 200),
        ]);

        $provider = new IsvatProvider;
        $result = $provider->verify('B12345678', 'ES');

        expect($result)->toBeArray();
        expect($result['valid'])->toBeTrue();
        expect($result['name'])->toBe('TEST COMPANY');
        expect($result['address'])->toBe('TEST ADDRESS');
        // Should not include rate_limit or cache in result
        expect($result)->not->toHaveKey('rate_limit');
        expect($result)->not->toHaveKey('cache');
    });

    it('handles 200 response with missing valid field as invalid', function () {
        // Edge case: if API returns 200 but without 'valid' field
        $responseData = [
            'vatNumber' => 'B12345678',
            'countryCode' => 'ES',
        ];

        Http::fake([
            'www.isvat.eu/*' => Http::response($responseData, 200),
        ]);

        $provider = new IsvatProvider;
        $result = $provider->verify('B12345678', 'ES');

        expect($result)->toBeArray();
        expect($result['valid'])->toBeFalse(); // Should default to false
        expect($result['api_source'])->toBe('ISVAT');
    });
});

describe('IsvatProvider - Response Structure', function () {
    it('returns array with required keys on verify', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/isvat_valid.json'), true);

        expect($stub)->toBeArray();
        expect($stub)->toHaveKey('valid');

        // Verify the raw API response has the array structure
        expect($stub['name'])->toBeArray();
        expect($stub['address'])->toBeArray();
        expect($stub['name']['0'])->toBe('ADOBE SYSTEMS SOFTWARE IRELAND LTD');
        expect($stub['address']['0'])->toBe('4 - 6 RIVER WALK, CITYWEST BUSINESS CAMPUS, SAGGART, DUBLIN 24');
    });

    it('returns name and address when VAT is valid', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/isvat_valid.json'), true);

        expect($stub)->toHaveKey('name');
        expect($stub)->toHaveKey('address');

        if ($stub['valid']) {
            expect($stub['name'])->not->toBeNull();
        }
    });

    it('returns valid=false for invalid VAT', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/isvat_invalid.json'), true);

        expect($stub)->toBeArray();
        expect($stub['valid'])->toBeFalse();
    });
});
