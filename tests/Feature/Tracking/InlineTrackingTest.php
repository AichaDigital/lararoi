<?php

use Aichadigital\Lararoi\Exceptions\UnknownConsumerException;
use Aichadigital\Lararoi\Models\VatVerification;
use Aichadigital\Lararoi\Models\VerificationQuery;
use Aichadigital\Lararoi\Services\VatProviderManager;
use Aichadigital\Lararoi\Services\VatVerificationService;
use Aichadigital\Lararoi\Services\VerificationTracker;
use Aichadigital\Lararoi\ValueObjects\VerificationContext;

/**
 * Build a service whose provider returns a valid VIES result, with cache
 * disabled so verifyVatNumber() flows straight through the passive path.
 *
 * $this->config is captured at construction, so cache.enabled must be set first.
 */
function serviceWithValidProvider(): VatVerificationService
{
    config()->set('lararoi.cache.enabled', false);

    $manager = Mockery::mock(VatProviderManager::class);
    $manager->shouldReceive('verify')
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

    return new VatVerificationService($manager, new VerificationTracker);
}

describe('Inline passive tracking path (ADR-002 D4)', function () {
    beforeEach(function () {
        config()->set('lararoi.consumers', ['larabill' => ['retention_days' => 10]]);
    });

    it('writes one tracking row when context is supplied and tracking is enabled', function () {
        config()->set('lararoi.tracking.enabled', true);

        $service = serviceWithValidProvider();
        $service->verifyVatNumber('B12345678', 'ES', new VerificationContext('larabill', 'invoice-1'));

        expect(VerificationQuery::count())->toBe(1);

        $row = VerificationQuery::first();
        expect($row->getConsumer())->toBe('larabill')
            ->and($row->getSubjectReference())->toBe('invoice-1')
            ->and($row->getVatCode())->toBe('ESB12345678')
            ->and($row->isValid())->toBeTrue()
            ->and($row->isCacheHit())->toBeFalse()
            ->and($row->getApiSource())->toBe('VIES_SOAP');
    });

    it('writes nothing when tracking is disabled, even with a context', function () {
        config()->set('lararoi.tracking.enabled', false);

        $service = serviceWithValidProvider();
        $service->verifyVatNumber('B12345678', 'ES', new VerificationContext('larabill', 'invoice-1'));

        expect(VerificationQuery::count())->toBe(0);
    });

    it('writes nothing when no context is supplied, even with tracking enabled', function () {
        config()->set('lararoi.tracking.enabled', true);

        $service = serviceWithValidProvider();
        $service->verifyVatNumber('B12345678', 'ES');

        expect(VerificationQuery::count())->toBe(0);
    });

    it('throws UnknownConsumerException when the consumer is unregistered and tracking is enabled', function () {
        config()->set('lararoi.tracking.enabled', true);
        config()->set('lararoi.consumers', []);

        $service = serviceWithValidProvider();

        expect(fn () => $service->verifyVatNumber('B12345678', 'ES', new VerificationContext('larabill', 'invoice-1')))
            ->toThrow(UnknownConsumerException::class);

        expect(VerificationQuery::count())->toBe(0);
    });

    it('records the local-validation short-circuit without calling any provider', function () {
        config()->set('lararoi.tracking.enabled', true);
        config()->set('lararoi.cache.enabled', false);

        $manager = Mockery::mock(VatProviderManager::class);
        $manager->shouldReceive('verify')->never();
        $service = new VatVerificationService($manager, new VerificationTracker);

        $service->verifyVatNumber('123', 'ES', new VerificationContext('larabill', 'invoice-2'));

        $row = VerificationQuery::first();
        expect(VerificationQuery::count())->toBe(1)
            ->and($row->isValid())->toBeFalse()
            ->and($row->getApiSource())->toBe('LOCAL_VALIDATION')
            ->and($row->isCacheHit())->toBeFalse();
    });

    it('does not write a cache row on the passive path with cache disabled', function () {
        config()->set('lararoi.tracking.enabled', true);

        $service = serviceWithValidProvider();
        $service->verifyVatNumber('B12345678', 'ES', new VerificationContext('larabill', 'invoice-1'));

        // Tracking is history; the cache table stays untouched when cache is off.
        expect(VatVerification::count())->toBe(0)
            ->and(VerificationQuery::count())->toBe(1);
    });
});

describe('Inline tracking resolves the tracker lazily from the container (ADR-002 D4)', function () {
    it('records via a container-resolved tracker when none is injected', function () {
        config()->set('lararoi.tracking.enabled', true);
        config()->set('lararoi.consumers', ['larabill' => ['retention_days' => 10]]);
        config()->set('lararoi.cache.enabled', false);

        $manager = Mockery::mock(VatProviderManager::class);
        $manager->shouldReceive('verify')
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

        // No tracker injected: the passive path must lazily resolve
        // VerificationTrackerInterface from the container (resolveTracker()).
        $service = new VatVerificationService($manager);
        $service->verifyVatNumber('B12345678', 'ES', new VerificationContext('larabill', 'invoice-container'));

        expect(VerificationQuery::count())->toBe(1)
            ->and(VerificationQuery::first()->getConsumer())->toBe('larabill');
    });
});
