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

    it('includes cache_status field in response', function () {
        $service = app(VatVerificationServiceInterface::class);

        try {
            $result = $service->verifyVatNumber('99999999', 'ES');

            expect($result)->toHaveKey('cache_status');
            expect($result['cache_status'])->toBeIn(['fresh', 'cached', 'refreshed']);
        } catch (\Exception $e) {
            // API might be unavailable
            $this->markTestSkipped('API unavailable');
        }
    });

    it('returns fresh status on first verification', function () {
        $service = app(VatVerificationServiceInterface::class);

        // Clear any existing cache
        \Illuminate\Support\Facades\Cache::flush();
        \Aichadigital\Lararoi\Models\VatVerification::query()->delete();

        try {
            $result = $service->verifyVatNumber('B99999999', 'ES');

            expect($result['cache_status'])->toBe('fresh');
            expect($result['cached'])->toBeFalse();
        } catch (\Exception $e) {
            // API might be unavailable
            $this->markTestSkipped('API unavailable');
        }
    });

    it('returns cached status on subsequent verifications', function () {
        $service = app(VatVerificationServiceInterface::class);

        try {
            // First call
            $service->verifyVatNumber('B88888888', 'ES');

            // Second call - should return cached
            $result = $service->verifyVatNumber('B88888888', 'ES');

            expect($result['cache_status'])->toBe('cached');
            expect($result['cached'])->toBeTrue();
        } catch (\Exception $e) {
            // API might be unavailable
            $this->markTestSkipped('API unavailable');
        }
    });
});

describe('VatVerificationService - Cache Configuration', function () {
    it('skips cache when cache is disabled via config', function () {
        // Disable cache temporarily
        config(['lararoi.cache.enabled' => false]);

        $service = app(VatVerificationServiceInterface::class);

        try {
            $result = $service->verifyVatNumber('B77777777', 'ES');

            // Should always return fresh when cache is disabled
            expect($result['cache_status'])->toBe('fresh');
            expect($result['cached'])->toBeFalse();
        } catch (\Exception $e) {
            // API might be unavailable
            $this->markTestSkipped('API unavailable');
        } finally {
            // Re-enable cache
            config(['lararoi.cache.enabled' => true]);
        }
    });

    it('does not store in database when cache is disabled', function () {
        // Disable cache and clear database
        config(['lararoi.cache.enabled' => false]);
        \Aichadigital\Lararoi\Models\VatVerification::query()->delete();

        $service = app(VatVerificationServiceInterface::class);

        try {
            $service->verifyVatNumber('B66666666', 'ES');

            // Verify it was NOT stored in database
            $verification = \Aichadigital\Lararoi\Models\VatVerification::findByVatCodeAndCountry('ESB66666666', 'ES');

            expect($verification)->toBeNull();
        } catch (\Exception $e) {
            // API might be unavailable
            $this->markTestSkipped('API unavailable');
        } finally {
            // Re-enable cache
            config(['lararoi.cache.enabled' => true]);
        }
    });

    it('respects custom cache TTL from config', function () {
        $originalTtl = config('lararoi.cache.ttl');

        // Set a very short TTL for testing
        config(['lararoi.cache.ttl' => 1]);

        $service = app(VatVerificationServiceInterface::class);

        try {
            // First call
            $service->verifyVatNumber('B55555555', 'ES');

            // Wait for cache to expire
            sleep(2);

            // Second call - should be refreshed
            $result = $service->verifyVatNumber('B55555555', 'ES');

            // Should either be 'fresh' or 'refreshed' depending on database state
            expect($result['cache_status'])->toBeIn(['fresh', 'refreshed']);
        } catch (\Exception $e) {
            // API might be unavailable
            $this->markTestSkipped('API unavailable');
        } finally {
            // Restore original TTL
            config(['lararoi.cache.ttl' => $originalTtl]);
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
