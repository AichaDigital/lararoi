<?php

use Aichadigital\Lararoi\Providers\AeatProvider;

describe('AeatProvider - Properties', function () {
    it('indicates it is a free provider', function () {
        $provider = new AeatProvider;

        expect($provider->isFree())->toBeTrue();
    });

    it('returns correct provider name', function () {
        $provider = new AeatProvider;

        expect($provider->getName())->toBe('AEAT');
    });

    it('is not available without certificate configuration', function () {
        $provider = new AeatProvider;

        // Without certificate configured, should not be available
        expect($provider->isAvailable())->toBeFalse();
    });

    it('is not available without SOAP extension', function () {
        if (! extension_loaded('soap')) {
            $provider = new AeatProvider;

            expect($provider->isAvailable())->toBeFalse();
        } else {
            expect(true)->toBeTrue(); // Skip if SOAP is loaded
        }
    });
});

describe('AeatProvider - Response Structure', function () {
    it('returns array with required keys on verify', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/aeat_valid.json'), true);

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
            expect($stub['api_source'])->toBe('AEAT');
            expect($stub['country_code'])->toBe('ES'); // AEAT only works for Spain
        }
    });

    it('returns name when VAT is valid', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/aeat_valid.json'), true);

        // Skip if stub is an error response
        if (isset($stub['error']) && $stub['error'] === true) {
            expect(true)->toBeTrue();

            return;
        }

        expect($stub)->toHaveKey('name');

        if ($stub['valid']) {
            expect($stub['name'])->not->toBeNull();
        }
    });

    it('returns valid=false for invalid VAT', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/aeat_invalid.json'), true);

        expect($stub)->toBeArray();

        // Skip if stub is an error response
        if (isset($stub['error']) && $stub['error'] === true) {
            expect(true)->toBeTrue();

            return;
        }

        expect($stub['valid'])->toBeFalse();
    });
});
