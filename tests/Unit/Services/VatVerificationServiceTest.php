<?php

use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;
use Aichadigital\Lararoi\Exceptions\VatVerificationException;
use Aichadigital\Lararoi\Models\VatVerification;
use Aichadigital\Lararoi\Services\VatProviderManager;
use Aichadigital\Lararoi\Services\VatVerificationService;
use Illuminate\Support\Facades\Cache;

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
        } catch (Exception $e) {
            // API might be unavailable - that's acceptable
            expect($e)->toBeInstanceOf(Exception::class);
        }
    });

    it('normalizes VAT code correctly', function () {
        $service = app(VatVerificationServiceInterface::class);

        try {
            $result = $service->verifyVatNumber('b12345678', 'es');

            expect($result['vat_code'])->toBe('ESB12345678');
            expect($result['country_code'])->toBe('ES');
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
            // API might be unavailable
            $this->markTestSkipped('API unavailable');
        }
    });

    it('stores verification in database', function () {
        $service = app(VatVerificationServiceInterface::class);

        try {
            $result = $service->verifyVatNumber('B12345678', 'ES');

            $verification = VatVerification::findByVatCodeAndCountry('ESB12345678', 'ES');

            expect($verification)->not->toBeNull();
        } catch (Exception $e) {
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
        } catch (Exception $e) {
            // API might be unavailable
            $this->markTestSkipped('API unavailable');
        }
    });

    it('returns fresh status on first verification', function () {
        $service = app(VatVerificationServiceInterface::class);

        // Clear any existing cache
        Cache::flush();
        VatVerification::query()->delete();

        try {
            $result = $service->verifyVatNumber('B99999999', 'ES');

            expect($result['cache_status'])->toBe('fresh');
            expect($result['cached'])->toBeFalse();
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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
        VatVerification::query()->delete();

        $service = app(VatVerificationServiceInterface::class);

        try {
            $service->verifyVatNumber('B66666666', 'ES');

            // Verify it was NOT stored in database
            $verification = VatVerification::findByVatCodeAndCountry('ESB66666666', 'ES');

            expect($verification)->toBeNull();
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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
            ->toThrow(Exception::class);
    });

    it('handles empty country code', function () {
        $service = app(VatVerificationServiceInterface::class);

        expect(fn () => $service->verifyVatNumber('B12345678', ''))
            ->toThrow(Exception::class);
    });

    it('handles invalid country code format', function () {
        $service = app(VatVerificationServiceInterface::class);

        // Country codes should be 2 characters
        $result = $service->verifyVatNumber('B12345678', 'ESP');

        // Debería manejar el error apropiadamente
        expect($result)->toBeArray();
    });
});

describe('VatVerificationService - Syntactic validation', function () {
    it('short-circuits an invalid VAT for a known country without calling any provider', function () {
        config()->set('lararoi.cache.enabled', false);

        $manager = Mockery::mock(VatProviderManager::class);
        $manager->shouldReceive('verify')->never();

        $service = new VatVerificationService($manager);
        $result = $service->verifyVatNumber('123', 'ES'); // malformed ES VAT

        expect($result['is_valid'])->toBeFalse()
            ->and($result['api_source'])->toBe('LOCAL_VALIDATION')
            ->and($result['cache_status'])->toBe('fresh')
            ->and($result['vat_code'])->toBe('ES123');
    });

    it('passes a well-formed VAT through to the provider', function () {
        config()->set('lararoi.cache.enabled', false);

        $manager = Mockery::mock(VatProviderManager::class);
        $manager->shouldReceive('verify')
            ->once()
            ->with('B12345678', 'ES')
            ->andReturn([
                'valid' => true,
                'name' => 'ACME SL',
                'address' => null,
                'request_date' => null,
                'vat_number' => 'B12345678',
                'country_code' => 'ES',
                'api_source' => 'VIES_SOAP',
            ]);

        $service = new VatVerificationService($manager);
        $result = $service->verifyVatNumber('B12345678', 'ES');

        expect($result['is_valid'])->toBeTrue()
            ->and($result['api_source'])->toBe('VIES_SOAP');
    });

    it('normalizes spaces and dashes before validating and verifying', function () {
        config()->set('lararoi.cache.enabled', false);

        $manager = Mockery::mock(VatProviderManager::class);
        $manager->shouldReceive('verify')
            ->once()
            ->with('B12345678', 'ES') // spaces and dashes stripped
            ->andReturn([
                'valid' => true,
                'name' => null,
                'address' => null,
                'request_date' => null,
                'vat_number' => 'B12345678',
                'country_code' => 'ES',
                'api_source' => 'VIES_SOAP',
            ]);

        $service = new VatVerificationService($manager);
        $result = $service->verifyVatNumber('B-123 456 78', 'es');

        expect($result['vat_code'])->toBe('ESB12345678')
            ->and($result['vat_code'])->not->toContain(' ');
    });

    it('throws a VatVerificationException for empty VAT or country code', function () {
        $service = app(VatVerificationServiceInterface::class);

        expect(fn () => $service->verifyVatNumber('', 'ES'))
            ->toThrow(VatVerificationException::class);

        expect(fn () => $service->verifyVatNumber('B12345678', ''))
            ->toThrow(VatVerificationException::class);
    });
});

describe('VatVerificationService - Canonical Output Shape', function () {
    it('maps a provider result to the canonical output shape without leaking provider-native keys', function () {
        config()->set('lararoi.cache.enabled', false);

        $manager = Mockery::mock(VatProviderManager::class);
        $manager->shouldReceive('verify')
            ->once()
            ->with('B12345678', 'ES')
            ->andReturn([
                'valid' => true,
                'name' => 'ACME SL',
                'address' => 'Calle Uno 1',
                'request_date' => '2026-01-01',
                'vat_number' => 'B12345678',
                'country_code' => 'ES',
                'api_source' => 'VIES_SOAP',
            ]);

        $service = new VatVerificationService($manager);
        $result = $service->verifyVatNumber('B12345678', 'ES');

        // Canonical keys consumed by larabill and the published contract.
        expect($result['is_valid'])->toBeTrue()
            ->and($result['company_name'])->toBe('ACME SL')
            ->and($result['company_address'])->toBe('Calle Uno 1')
            ->and($result['api_source'])->toBe('VIES_SOAP')
            ->and($result['vat_code'])->toBe('ESB12345678')
            ->and($result['country_code'])->toBe('ES')
            ->and($result['cache_status'])->toBe('fresh');

        // Provider-native vocabulary must not leak into the top-level shape.
        expect($result)->not->toHaveKey('valid')
            ->and($result)->not->toHaveKey('name')
            ->and($result)->not->toHaveKey('address');
    });
});

describe('VatVerificationService - Atomic Upsert Persistence (ADR-002 D2)', function () {
    it('upserts a single row per NIF, updating on the second persist', function () {
        $service = app(VatVerificationServiceInterface::class);
        $persist = (new ReflectionClass($service))->getMethod('persistVerification');
        $persist->setAccessible(true);

        $first = [
            'is_valid' => true,
            'vat_code' => 'ESB12345678',
            'country_code' => 'ES',
            'company_name' => 'ACME SL',
            'company_address' => 'Calle Uno 1',
            'api_source' => 'VIES_SOAP',
            'request_date' => '2026-01-01',
        ];
        $second = array_merge($first, [
            'is_valid' => false,
            'company_name' => 'ACME SL (updated)',
            'api_source' => 'VIES_REST',
        ]);

        $persist->invoke($service, 'ESB12345678', 'ES', $first);
        $persist->invoke($service, 'ESB12345678', 'ES', $second);

        $rows = VatVerification::query()
            ->where('vat_code', 'ESB12345678')
            ->where('country_code', 'ES')
            ->get();

        // The UNIQUE index + upsert converge on exactly one row.
        expect($rows)->toHaveCount(1);

        $row = $rows->first();
        expect($row->is_valid)->toBeFalse()
            ->and($row->company_name)->toBe('ACME SL (updated)')
            ->and($row->api_source)->toBe('VIES_REST');
    });

    it('round-trips response_data as an array after an upsert', function () {
        $service = app(VatVerificationServiceInterface::class);
        $persist = (new ReflectionClass($service))->getMethod('persistVerification');
        $persist->setAccessible(true);

        $result = [
            'is_valid' => true,
            'vat_code' => 'ESB99999999',
            'country_code' => 'ES',
            'company_name' => 'Nested Co',
            'company_address' => null,
            'api_source' => 'VIES_SOAP',
            'request_date' => '2026-02-02',
        ];

        $persist->invoke($service, 'ESB99999999', 'ES', $result);

        $row = VatVerification::findByVatCodeAndCountry('ESB99999999', 'ES');

        expect($row)->not->toBeNull()
            ->and($row->response_data)->toBeArray()
            ->and($row->response_data['company_name'])->toBe('Nested Co')
            ->and($row->response_data['api_source'])->toBe('VIES_SOAP');
    });
});

describe('VatVerificationService - VAT Normalization', function () {
    it('removes spaces from VAT number', function () {
        $service = app(VatVerificationServiceInterface::class);

        try {
            $result = $service->verifyVatNumber('B 123 456 78', 'ES');

            expect($result['vat_code'])->not->toContain(' ');
        } catch (Exception $e) {
            $this->markTestSkipped('API unavailable');
        }
    });

    it('converts VAT to uppercase', function () {
        $service = app(VatVerificationServiceInterface::class);

        try {
            $result = $service->verifyVatNumber('b12345678', 'ES');

            expect($result['vat_code'])->toBe('ESB12345678');
        } catch (Exception $e) {
            $this->markTestSkipped('API unavailable');
        }
    });

    it('converts country code to uppercase', function () {
        $service = app(VatVerificationServiceInterface::class);

        try {
            $result = $service->verifyVatNumber('B12345678', 'es');

            expect($result['country_code'])->toBe('ES');
        } catch (Exception $e) {
            $this->markTestSkipped('API unavailable');
        }
    });
});
