<?php

use Aichadigital\Lararoi\Providers\ViesSoapProvider;

describe('ViesSoapProvider - Properties', function () {
    it('indicates it is a free provider', function () {
        $provider = new ViesSoapProvider;

        expect($provider->isFree())->toBeTrue();
    });

    it('returns correct provider name', function () {
        $provider = new ViesSoapProvider;

        expect($provider->getName())->toBe('VIES SOAP');
    });

    it('is available when SOAP extension is loaded', function () {
        $provider = new ViesSoapProvider;

        expect($provider->isAvailable())->toBe(extension_loaded('soap'));
    });
});

describe('ViesSoapProvider - Response Structure', function () {
    it('returns array with required keys on verify', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/vies_soap_valid.json'), true);

        expect($stub)->toBeArray();
        expect($stub)->toHaveKey('valid');
        expect($stub)->toHaveKey('vat_number');
        expect($stub)->toHaveKey('country_code');
        expect($stub)->toHaveKey('api_source');
        expect($stub['api_source'])->toBe('VIES_SOAP');
    });

    it('returns name and address when VAT is valid', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/vies_soap_valid.json'), true);

        expect($stub)->toHaveKey('name');
        expect($stub)->toHaveKey('address');

        if ($stub['valid']) {
            expect($stub['name'])->not->toBeNull();
        }
    });

    it('returns valid=false for invalid VAT', function () {
        $stub = json_decode(file_get_contents(__DIR__.'/../../stubs/vies_soap_invalid.json'), true);

        expect($stub)->toBeArray();
        expect($stub['valid'])->toBeFalse();
    });
});
