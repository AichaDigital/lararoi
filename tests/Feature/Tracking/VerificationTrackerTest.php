<?php

use Aichadigital\Lararoi\Contracts\VerificationQueryModelInterface;
use Aichadigital\Lararoi\Contracts\VerificationTrackerInterface;
use Aichadigital\Lararoi\Exceptions\TrackingDisabledException;
use Aichadigital\Lararoi\Exceptions\UnknownConsumerException;
use Aichadigital\Lararoi\Models\VerificationQuery;
use Aichadigital\Lararoi\Services\VerificationTracker;
use Aichadigital\Lararoi\ValueObjects\VerificationContext;
use Illuminate\Support\Carbon;

/**
 * A full verifyVatNumber() return shape, carrying the cache meta and nested
 * response_data that the snapshot must drop.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function serviceResult(array $overrides = []): array
{
    $canonical = array_merge([
        'is_valid' => true,
        'vat_code' => 'ESB12345678',
        'country_code' => 'ES',
        'company_name' => 'ACME SL',
        'company_address' => 'Calle Uno 1',
        'api_source' => 'VIES_SOAP',
        'cache_status' => 'fresh',
        'request_date' => '2026-01-01',
    ], $overrides);

    return array_merge($canonical, [
        'cached' => $canonical['cache_status'] === 'cached',
        'response_data' => $canonical,
    ]);
}

function tracker(): VerificationTrackerInterface
{
    return app(VerificationTrackerInterface::class);
}

beforeEach(function () {
    config()->set('lararoi.tracking.enabled', true);
    config()->set('lararoi.consumers', [
        'larabill' => ['retention_days' => 10],
        'openmiza' => ['retention_days' => null],
    ]);
});

describe('VerificationTracker - gating (ADR-002 D4/D5)', function () {
    it('resolves the default tracker from the container', function () {
        expect(tracker())->toBeInstanceOf(VerificationTracker::class);
    });

    it('record() throws TrackingDisabledException when tracking is disabled', function () {
        config()->set('lararoi.tracking.enabled', false);

        expect(fn () => tracker()->record(new VerificationContext('larabill'), serviceResult()))
            ->toThrow(TrackingDisabledException::class);
    });

    it('tryRecord() returns null when tracking is disabled', function () {
        config()->set('lararoi.tracking.enabled', false);

        expect(tracker()->tryRecord(new VerificationContext('larabill'), serviceResult()))->toBeNull();
        expect(VerificationQuery::count())->toBe(0);
    });

    it('record() throws UnknownConsumerException for an unregistered consumer when enabled', function () {
        expect(fn () => tracker()->record(new VerificationContext('larabil'), serviceResult()))
            ->toThrow(UnknownConsumerException::class);
    });

    it('tryRecord() still throws for an unregistered consumer when enabled (a typo is not silence)', function () {
        expect(fn () => tracker()->tryRecord(new VerificationContext('larabil'), serviceResult()))
            ->toThrow(UnknownConsumerException::class);
    });
});

describe('VerificationTracker - append-only writes (ADR-002 D3)', function () {
    it('record() inserts a NEW row on every call for the same NIF', function () {
        $context = new VerificationContext('larabill', 'invoice-1');

        tracker()->record($context, serviceResult());
        tracker()->record($context, serviceResult());

        expect(VerificationQuery::where('vat_code', 'ESB12345678')->count())->toBe(2);
    });

    it('record() returns the created tracking row', function () {
        $row = tracker()->record(new VerificationContext('larabill', 'invoice-1'), serviceResult());

        expect($row)->toBeInstanceOf(VerificationQueryModelInterface::class)
            ->and($row->getConsumer())->toBe('larabill')
            ->and($row->getSubjectReference())->toBe('invoice-1')
            ->and($row->getVatCode())->toBe('ESB12345678')
            ->and($row->getApiSource())->toBe('VIES_SOAP');
        expect(VerificationQuery::find($row->getKey()))->not->toBeNull();
    });
});

describe('VerificationTracker - derived cache_hit (ADR-002 D3, Codex P1)', function () {
    it('records cache_hit=true from a cached result', function () {
        $row = tracker()->record(new VerificationContext('larabill'), serviceResult(['cache_status' => 'cached']));

        expect($row->isCacheHit())->toBeTrue();
    });

    it('records cache_hit=false from a fresh result', function () {
        $row = tracker()->record(new VerificationContext('larabill'), serviceResult(['cache_status' => 'fresh']));

        expect($row->isCacheHit())->toBeFalse();
    });

    it('records cache_hit=false from a refreshed result (refreshed is not a cache hit)', function () {
        $row = tracker()->record(new VerificationContext('larabill'), serviceResult(['cache_status' => 'refreshed']));

        expect($row->isCacheHit())->toBeFalse();
    });

    it('falls back to the cached boolean when cache_status is absent', function () {
        $result = serviceResult();
        unset($result['cache_status']);
        $result['cached'] = true;

        $row = tracker()->record(new VerificationContext('larabill'), $result);

        expect($row->isCacheHit())->toBeTrue();
    });
});

describe('VerificationTracker - snapshot minimization (ADR-002 D3, Codex P1/P3)', function () {
    it('stores ONLY the seven canonical facts, never cache meta or response_data', function () {
        $row = tracker()->record(new VerificationContext('larabill'), serviceResult());

        $snapshot = $row->getResponseSnapshot();

        expect(array_keys($snapshot))->toEqualCanonicalizing([
            'is_valid', 'vat_code', 'country_code',
            'company_name', 'company_address', 'api_source', 'request_date',
        ]);

        expect($snapshot)->not->toHaveKey('cached')
            ->and($snapshot)->not->toHaveKey('cache_status')
            ->and($snapshot)->not->toHaveKey('response_data');
    });

    it('carries the fact values faithfully', function () {
        $row = tracker()->record(new VerificationContext('larabill'), serviceResult());

        expect($row->getResponseSnapshot())->toBe([
            'is_valid' => true,
            'vat_code' => 'ESB12345678',
            'country_code' => 'ES',
            'company_name' => 'ACME SL',
            'company_address' => 'Calle Uno 1',
            'api_source' => 'VIES_SOAP',
            'request_date' => '2026-01-01',
        ]);
    });
});

describe('VerificationTracker - retention stamping in UTC (ADR-002 D5)', function () {
    it('stamps retention_until = queried_at + retention_days for a registered consumer', function () {
        $row = tracker()->record(new VerificationContext('larabill'), serviceResult());

        $expected = $row->getQueriedAt()->copy()->addDays(10);

        expect($row->getRetentionUntil())->not->toBeNull()
            ->and($row->getRetentionUntil()->equalTo($expected))->toBeTrue();
    });

    it('leaves retention_until null for a conscious keep-forever consumer', function () {
        $row = tracker()->record(new VerificationContext('openmiza'), serviceResult());

        expect($row->getRetentionUntil())->toBeNull();
    });

    it('stores queried_at in UTC', function () {
        $before = Carbon::now('UTC');
        $row = tracker()->record(new VerificationContext('larabill'), serviceResult());
        $after = Carbon::now('UTC');

        expect($row->getQueriedAt()->between($before->subSecond(), $after->addSecond()))->toBeTrue();
    });
});

describe('VerificationTracker - consumer-scoped reads (ADR-002 D4)', function () {
    beforeEach(function () {
        tracker()->record(new VerificationContext('larabill', 'subj-A'), serviceResult(['vat_code' => 'ESB11111111']));
        tracker()->record(new VerificationContext('openmiza', 'subj-A'), serviceResult(['vat_code' => 'ESB11111111']));
    });

    it('forSubject only returns the querying consumer rows', function () {
        $rows = collect(tracker()->forSubject('larabill', 'subj-A'));

        expect($rows)->toHaveCount(1)
            ->and($rows->every(fn ($r) => $r->getConsumer() === 'larabill'))->toBeTrue();
    });

    it('forVat only returns the querying consumer rows', function () {
        $rows = collect(tracker()->forVat('larabill', 'ESB11111111', 'ES'));

        expect($rows)->toHaveCount(1)
            ->and($rows->first()->getConsumer())->toBe('larabill');
    });

    it('between only returns the querying consumer rows', function () {
        $from = Carbon::now('UTC')->subDay();
        $to = Carbon::now('UTC')->addDay();

        $rows = collect(tracker()->between('larabill', $from, $to));

        expect($rows)->toHaveCount(1)
            ->and($rows->first()->getConsumer())->toBe('larabill');
    });

    it('never leaks consumer B history into consumer A reads', function () {
        $a = collect(tracker()->forSubject('larabill', 'subj-A'));
        $b = collect(tracker()->forSubject('openmiza', 'subj-A'));

        expect($a->pluck('consumer')->unique()->all())->toBe(['larabill'])
            ->and($b->pluck('consumer')->unique()->all())->toBe(['openmiza']);
    });
});
