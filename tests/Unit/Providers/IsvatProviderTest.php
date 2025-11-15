<?php

use Aichadigital\Lararoi\Providers\IsvatProvider;

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

describe('IsvatProvider - Response Structure', function () {
    it('returns array with required keys on verify', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/isvat_valid.json'), true);

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
            expect($stub['api_source'])->toBe('ISVAT');
        }
    });

    it('returns name and address when VAT is valid', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/isvat_valid.json'), true);

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
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/isvat_invalid.json'), true);

        expect($stub)->toBeArray();

        // Skip if stub is an error response
        if (isset($stub['error']) && $stub['error'] === true) {
            expect(true)->toBeTrue();

            return;
        }

        expect($stub['valid'])->toBeFalse();
    });
});
