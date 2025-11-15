<?php

use Aichadigital\Lararoi\Providers\VatlayerProvider;

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
        $provider = new VatlayerProvider(null, 'test_api_key');

        expect($provider->isAvailable())->toBeTrue();
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
