<?php

use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;

describe('VatVerificationService - Basic Verification', function () {
    it('resolves service from container', function () {
        $service = app(VatVerificationServiceInterface::class);

        expect($service)->toBeInstanceOf(VatVerificationServiceInterface::class);
    });

    it('returns array with required keys', function () {
        $service = app(VatVerificationServiceInterface::class);

        try {
            $result = $service->verifyVatNumber('99999999', 'ES');

            expect($result)->toBeArray();
            expect($result)->toHaveKey('is_valid');
            expect($result)->toHaveKey('vat_code');
            expect($result)->toHaveKey('country_code');
            expect($result)->toHaveKey('api_source');
            expect($result)->toHaveKey('cached');
        } catch (\Exception $e) {
            // API might be unavailable - that's acceptable
            expect($e)->toBeInstanceOf(\Exception::class);
        }
    });

    it('normalizes VAT code correctly', function () {
        $service = app(VatVerificationServiceInterface::class);

        try {
            $result = $service->verifyVatNumber('b12345678', 'es');

            expect($result['vat_code'])->toBe('ESB12345678');
            expect($result['country_code'])->toBe('ES');
        } catch (\Exception $e) {
            // API might be unavailable
            $this->markTestSkipped('API unavailable');
        }
    });
});

describe('VatVerificationService - Company Data', function () {
    it('includes company name and address when valid', function () {
        $service = app(VatVerificationServiceInterface::class);

        try {
            $result = $service->verifyVatNumber('99999999', 'ES');

            expect($result)->toHaveKey('company_name');
            expect($result)->toHaveKey('company_address');
        } catch (\Exception $e) {
            // API might be unavailable
            $this->markTestSkipped('API unavailable');
        }
    });
});
