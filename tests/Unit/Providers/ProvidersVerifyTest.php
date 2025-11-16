<?php

use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use Aichadigital\Lararoi\Providers\IsvatProvider;
use Aichadigital\Lararoi\Providers\VatlayerProvider;
use Aichadigital\Lararoi\Providers\ViesApiProvider;
use Aichadigital\Lararoi\Providers\ViesRestProvider;
use Illuminate\Support\Facades\Http;

describe('ViesRestProvider - Verify Method', function () {
    it('handles successful API response', function () {
        Http::fake([
            'ec.europa.eu/*' => Http::response([
                'isValid' => true,
                'name' => 'Test Company',
                'address' => 'Test Address',
                'vatNumber' => 'B12345678',
                'countryCode' => 'ES',
                'requestDate' => '2024-01-01',
            ], 200),
        ]);

        $provider = new ViesRestProvider;
        $result = $provider->verify('B12345678', 'ES');

        expect($result['valid'])->toBeTrue();
        expect($result['name'])->toBe('Test Company');
        expect($result['api_source'])->toBe('VIES_REST');
    });

    it('handles invalid VAT response', function () {
        Http::fake([
            'ec.europa.eu/*' => Http::response([
                'isValid' => false,
                'vatNumber' => 'B99999999',
                'countryCode' => 'ES',
            ], 200),
        ]);

        $provider = new ViesRestProvider;
        $result = $provider->verify('B99999999', 'ES');

        expect($result['valid'])->toBeFalse();
    });

    it('throws exception when API is unavailable', function () {
        Http::fake([
            'ec.europa.eu/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
            },
        ]);

        $provider = new ViesRestProvider;

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });

    it('throws exception when response format is invalid', function () {
        Http::fake([
            'ec.europa.eu/*' => Http::response(['invalid' => 'format'], 200),
        ]);

        $provider = new ViesRestProvider;

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });
});

describe('IsvatProvider - Verify Method', function () {
    it('handles successful API response', function () {
        Http::fake([
            'www.isvat.eu/*' => Http::response([
                'valid' => true,
                'name' => ['0' => 'Test Company'],
                'address' => ['0' => 'Test Address'],
                'vatNumber' => 'B12345678',
                'countryCode' => 'ES',
            ], 200),
        ]);

        $provider = new IsvatProvider(false);
        $result = $provider->verify('B12345678', 'ES');

        expect($result['valid'])->toBeTrue();
        expect($result['api_source'])->toBe('ISVAT');
        expect($result['name'])->toBe('Test Company');
        expect($result['address'])->toBe('Test Address');
    });

    it('uses live endpoint when configured', function () {
        Http::fake([
            'www.isvat.eu/*' => Http::response([
                'valid' => true,
                'vatNumber' => 'B12345678',
                'countryCode' => 'ES',
            ], 200),
        ]);

        $provider = new IsvatProvider(true);
        $result = $provider->verify('B12345678', 'ES');

        expect($result['valid'])->toBeTrue();
    });

    it('handles missing optional fields', function () {
        Http::fake([
            'www.isvat.eu/*' => Http::response([
                'valid' => false,
            ], 200),
        ]);

        $provider = new IsvatProvider;
        $result = $provider->verify('B99999999', 'ES');

        expect($result['valid'])->toBeFalse();
        expect($result['name'])->toBeNull();
        expect($result['request_date'])->toBeNull();
    });

    it('throws exception on API error', function () {
        Http::fake([
            'www.isvat.eu/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
        ]);

        $provider = new IsvatProvider;

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });
});

describe('VatlayerProvider - Verify Method', function () {
    it('handles successful API response', function () {
        Http::fake([
            'apilayer.net/*' => Http::response([
                'valid' => true,
                'company_name' => 'Test Company',
                'company_address' => 'Test Address',
                'vat_number' => 'ESB12345678',
                'country_code' => 'ES',
            ], 200),
        ]);

        $provider = new VatlayerProvider('test_key');
        $result = $provider->verify('B12345678', 'ES');

        expect($result['valid'])->toBeTrue();
        expect($result['api_source'])->toBe('VATLAYER');
    });

    it('handles API error response', function () {
        Http::fake([
            'apilayer.net/*' => Http::response([
                'error' => ['info' => 'Invalid API key'],
            ], 200),
        ]);

        $provider = new VatlayerProvider('invalid_key');

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });

    it('throws exception when not available', function () {
        $provider = new VatlayerProvider(null);

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });
});

describe('ViesApiProvider - Verify Method', function () {
    it('handles successful API response', function () {
        Http::fake([
            'viesapi.eu/*' => Http::response([
                'valid' => true,
                'traderName' => 'Test Company',
                'traderAddress' => 'Test Address',
                'vatNumber' => 'B12345678',
                'countryCode' => 'ES',
            ], 200),
        ]);

        $provider = new ViesApiProvider('test_key');
        $result = $provider->verify('B12345678', 'ES');

        expect($result['valid'])->toBeTrue();
        expect($result['api_source'])->toBe('VIESAPI');
    });

    it('includes IP headers when configured', function () {
        Http::fake([
            'viesapi.eu/*' => Http::response([
                'valid' => true,
                'vatNumber' => 'B12345678',
                'countryCode' => 'ES',
            ], 200),
        ]);

        $provider = new ViesApiProvider('test_key', null, '127.0.0.1');
        $result = $provider->verify('B12345678', 'ES');

        expect($result['valid'])->toBeTrue();
    });

    it('throws exception when not available', function () {
        $provider = new ViesApiProvider(null);

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });
});
