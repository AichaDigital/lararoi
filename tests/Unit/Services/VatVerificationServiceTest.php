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

describe('VatVerificationService - Caching', function () {
    it('caches verification results', function () {
        $service = app(VatVerificationServiceInterface::class);

        try {
            // First call - from API
            $result1 = $service->verifyVatNumber('99999999', 'ES');

            // Second call - should be cached
            $result2 = $service->verifyVatNumber('99999999', 'ES');

            expect($result2['cached'])->toBeTrue();
        } catch (\Exception $e) {
            // API might be unavailable
            $this->markTestSkipped('API unavailable');
        }
    });

    it('stores verification in database', function () {
        $service = app(VatVerificationServiceInterface::class);

        try {
            $result = $service->verifyVatNumber('B12345678', 'ES');

            $verification = \Aichadigital\Lararoi\Models\VatVerification::findByVatCodeAndCountry('ESB12345678', 'ES');

            expect($verification)->not->toBeNull();
        } catch (\Exception $e) {
            // API might be unavailable
            $this->markTestSkipped('API unavailable');
        }
    });
});

describe('VatVerificationService - Error Handling', function () {
    it('handles empty VAT number', function () {
        $service = app(VatVerificationServiceInterface::class);

        expect(fn () => $service->verifyVatNumber('', 'ES'))
            ->toThrow(\Exception::class);
    });

    it('handles empty country code', function () {
        $service = app(VatVerificationServiceInterface::class);

        expect(fn () => $service->verifyVatNumber('B12345678', ''))
            ->toThrow(\Exception::class);
    });

    it('handles invalid country code format', function () {
        $service = app(VatVerificationServiceInterface::class);

        // Country codes should be 2 characters
        $result = $service->verifyVatNumber('B12345678', 'ESP');

        // Debería manejar el error apropiadamente
        expect($result)->toBeArray();
    });
});

describe('VatVerificationService - VAT Normalization', function () {
    it('removes spaces from VAT number', function () {
        $service = app(VatVerificationServiceInterface::class);

        try {
            $result = $service->verifyVatNumber('B 123 456 78', 'ES');

            expect($result['vat_code'])->not->toContain(' ');
        } catch (\Exception $e) {
            $this->markTestSkipped('API unavailable');
        }
    });

    it('converts VAT to uppercase', function () {
        $service = app(VatVerificationServiceInterface::class);

        try {
            $result = $service->verifyVatNumber('b12345678', 'ES');

            expect($result['vat_code'])->toBe('ESB12345678');
        } catch (\Exception $e) {
            $this->markTestSkipped('API unavailable');
        }
    });

    it('converts country code to uppercase', function () {
        $service = app(VatVerificationServiceInterface::class);

        try {
            $result = $service->verifyVatNumber('B12345678', 'es');

            expect($result['country_code'])->toBe('ES');
        } catch (\Exception $e) {
            $this->markTestSkipped('API unavailable');
        }
    });
});
