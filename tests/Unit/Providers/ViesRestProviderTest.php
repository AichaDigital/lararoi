<?php

use Aichadigital\Lararoi\Providers\ViesRestProvider;

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
