<?php

use Aichadigital\Lararoi\Contracts\VerificationResultMapperInterface;
use Aichadigital\Lararoi\Mappers\IdentityVerificationResultMapper;
use Aichadigital\Lararoi\Services\VatProviderManager;
use Aichadigital\Lararoi\Services\VatVerificationService;
use Aichadigital\Lararoi\Services\VerificationResultMapperRegistry;

/**
 * A tiny consumer mapper: flattens the canonical facts into its own shape.
 */
class AcmeVatMapper implements VerificationResultMapperInterface
{
    public function map(array $canonical): mixed
    {
        return [
            'valid' => (bool) ($canonical['is_valid'] ?? false),
            'id' => $canonical['vat_code'] ?? null,
            'name' => $canonical['company_name'] ?? null,
        ];
    }
}

/**
 * A mapper returning a non-array shape, proving map(): mixed is honoured.
 */
class ScalarVatMapper implements VerificationResultMapperInterface
{
    public function map(array $canonical): mixed
    {
        return (string) ($canonical['vat_code'] ?? '');
    }
}

/**
 * A class that is NOT a mapper — used for the invalid-config guard.
 */
class NotAMapper {}

/**
 * A class that IS a mapper by type (so the class-string is_a() check passes) but
 * is rebound in the container to a non-mapper instance — used to exercise the
 * defensive runtime instanceof guard that fires after container resolution.
 */
class ContainerReboundMapper implements VerificationResultMapperInterface
{
    public function map(array $canonical): mixed
    {
        return $canonical;
    }
}

/**
 * The seven canonical verification facts the mapper receives (D3/D6).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function canonicalFacts(array $overrides = []): array
{
    return array_merge([
        'is_valid' => true,
        'vat_code' => 'ESB12345678',
        'country_code' => 'ES',
        'company_name' => 'ACME SL',
        'company_address' => 'Calle Uno 1',
        'api_source' => 'VIES_SOAP',
        'request_date' => '2026-01-01',
    ], $overrides);
}

function mapperRegistry(): VerificationResultMapperRegistry
{
    return app(VerificationResultMapperRegistry::class);
}

describe('VerificationResultMapperRegistry - identity default (ADR-002 D6)', function () {
    it('returns the identity mapper for a consumer with no configured mapper', function () {
        expect(mapperRegistry()->mapperFor('anyone'))
            ->toBeInstanceOf(IdentityVerificationResultMapper::class);
    });

    it('maps the canonical result unchanged through the identity default', function () {
        $canonical = canonicalFacts();

        expect(mapperRegistry()->mapperFor('anyone')->map($canonical))->toBe($canonical);
    });

    it('returns identity for a registered consumer that declares no mapper key', function () {
        config()->set('lararoi.consumers', ['larabill' => ['retention_days' => 10]]);

        expect(mapperRegistry()->mapperFor('larabill'))
            ->toBeInstanceOf(IdentityVerificationResultMapper::class);
    });
});

describe('VerificationResultMapperRegistry - config-driven mapper (ADR-002 D6)', function () {
    it('resolves the configured mapper class for a consumer', function () {
        config()->set('lararoi.consumers.acme.mapper', AcmeVatMapper::class);

        expect(mapperRegistry()->mapperFor('acme'))->toBeInstanceOf(AcmeVatMapper::class);
    });

    it('produces the consumer own shape via the configured mapper', function () {
        config()->set('lararoi.consumers.acme.mapper', AcmeVatMapper::class);

        $mapped = mapperRegistry()->mapperFor('acme')->map(canonicalFacts());

        expect($mapped)->toBe([
            'valid' => true,
            'id' => 'ESB12345678',
            'name' => 'ACME SL',
        ]);
    });

    it('honours a mapper that returns a non-array shape (map(): mixed)', function () {
        config()->set('lararoi.consumers.acme.mapper', ScalarVatMapper::class);

        expect(mapperRegistry()->mapperFor('acme')->map(canonicalFacts()))->toBe('ESB12345678');
    });
});

describe('VerificationResultMapperRegistry - invalid config throws (ADR-002 D6)', function () {
    it('throws when the configured mapper is a class that is not a mapper', function () {
        config()->set('lararoi.consumers.acme.mapper', NotAMapper::class);

        expect(fn () => mapperRegistry()->mapperFor('acme'))->toThrow(RuntimeException::class);
    });

    it('throws when the configured mapper is not a class at all', function () {
        config()->set('lararoi.consumers.acme.mapper', 'not-a-class');

        expect(fn () => mapperRegistry()->mapperFor('acme'))->toThrow(RuntimeException::class);
    });

    it('throws when the class-string is a mapper but the container resolves a non-mapper', function () {
        // is_a() validates the class-string (ContainerReboundMapper implements the
        // interface), so the first guard passes; the container binding then makes
        // app() return a non-mapper instance, tripping the defensive runtime
        // instanceof guard that keeps the narrowing honest.
        config()->set('lararoi.consumers.acme.mapper', ContainerReboundMapper::class);
        app()->bind(ContainerReboundMapper::class, fn () => new stdClass);

        expect(fn () => mapperRegistry()->mapperFor('acme'))->toThrow(RuntimeException::class);
    });
});

describe('VerificationResultMapperRegistry - programmatic registration (ADR-002 D6)', function () {
    it('returns a programmatically registered instance', function () {
        $mapper = new AcmeVatMapper;
        $registry = mapperRegistry();
        $registry->register('acme', $mapper);

        expect($registry->mapperFor('acme'))->toBe($mapper);
    });

    it('lets a registered instance take precedence over config', function () {
        config()->set('lararoi.consumers.acme.mapper', ScalarVatMapper::class);

        $mapper = new AcmeVatMapper;
        $registry = mapperRegistry();
        $registry->register('acme', $mapper);

        expect($registry->mapperFor('acme'))->toBe($mapper);
    });
});

describe('verifyVatNumber() is never auto-mapped (ADR-002 D6, Codex P1)', function () {
    it('returns the canonical array even when the consumer declares a mapper', function () {
        // A configured mapper must not change what the service returns; the
        // mapper is a separate, consumer-invoked transform.
        config()->set('lararoi.cache.enabled', false);
        config()->set('lararoi.consumers.larabill.mapper', ScalarVatMapper::class);

        // The ES '123' input fails the syntactic short-circuit and resolves
        // locally — the provider must never be called.
        $manager = Mockery::mock(VatProviderManager::class);
        $manager->shouldReceive('verify')->never();
        $service = new VatVerificationService($manager);

        $result = $service->verifyVatNumber('123', 'ES');

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys([
                'is_valid', 'vat_code', 'country_code',
                'company_name', 'company_address', 'api_source', 'request_date',
            ])
            ->and($result['vat_code'])->toBe('ES123')
            ->and($result['api_source'])->toBe('LOCAL_VALIDATION');

        // ScalarVatMapper would have produced a string; it never ran.
        expect($result)->not->toBeString();
    });
});
