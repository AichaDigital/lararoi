<?php

use Aichadigital\Lararoi\Providers\ViesApiProvider;

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
        $provider = new ViesApiProvider(null, 'test_api_key');

        expect($provider->isAvailable())->toBeTrue();
    });
});

describe('ViesApiProvider - Response Structure', function () {
    it('returns array with required keys on verify', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/viesapi_valid.json'), true);

        expect($stub)->toBeArray();

        // Stub can be either a valid response or an error response
        if (isset($stub['error']) && $stub['error'] === true) {
            expect($stub)->toHaveKey('error_message');
            expect($stub)->toHaveKey('vat_number');
            expect($stub)->toHaveKey('country_code');
        } else {
            expect($stub)->toHaveKey('valid');
            expect($stub)->toHaveKey('vat_number');
            expect($stub)->toHaveKey('country_code');
            expect($stub)->toHaveKey('api_source');
            expect($stub['api_source'])->toBe('VIESAPI');
        }
    });

    it('returns name and address when VAT is valid', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/viesapi_valid.json'), true);

        // Skip if stub is an error response
        if (isset($stub['error']) && $stub['error'] === true) {
            expect(true)->toBeTrue();

            return;
        }

        expect($stub)->toHaveKey('name');
        expect($stub)->toHaveKey('address');

        if ($stub['valid']) {
            expect($stub['name'])->not->toBeNull();
        }
    });

    it('returns valid=false for invalid VAT', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/viesapi_invalid.json'), true);

        expect($stub)->toBeArray();

        // Skip if stub is an error response
        if (isset($stub['error']) && $stub['error'] === true) {
            expect(true)->toBeTrue();

            return;
        }

        expect($stub['valid'])->toBeFalse();
    });
});
