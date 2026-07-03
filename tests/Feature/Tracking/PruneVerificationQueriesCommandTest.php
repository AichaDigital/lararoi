<?php

use Aichadigital\Lararoi\Models\VerificationQuery;
use Illuminate\Support\Carbon;

function trackingRow(string $vatCode, ?Carbon $retentionUntil): VerificationQuery
{
    return VerificationQuery::create([
        'consumer' => 'larabill',
        'subject_reference' => null,
        'vat_code' => $vatCode,
        'country_code' => 'ES',
        'is_valid' => true,
        'api_source' => 'VIES_SOAP',
        'cache_hit' => false,
        'response_snapshot' => null,
        'queried_at' => Carbon::now('UTC'),
        'retention_until' => $retentionUntil,
    ]);
}

describe('roi:prune-verification-queries (ADR-002 D5)', function () {
    it('deletes only rows whose retention_until has passed', function () {
        $expired = trackingRow('ESB00000001', Carbon::now('UTC')->subDay());
        $future = trackingRow('ESB00000002', Carbon::now('UTC')->addDay());

        $this->artisan('roi:prune-verification-queries')
            ->assertSuccessful();

        expect(VerificationQuery::find($expired->id))->toBeNull()
            ->and(VerificationQuery::find($future->id))->not->toBeNull();
    });

    it('never prunes null-retention (keep-forever) rows', function () {
        $keepForever = trackingRow('ESB00000003', null);
        trackingRow('ESB00000004', Carbon::now('UTC')->subWeek());

        $this->artisan('roi:prune-verification-queries')
            ->assertSuccessful();

        expect(VerificationQuery::find($keepForever->id))->not->toBeNull()
            ->and(VerificationQuery::count())->toBe(1);
    });

    it('reports the pruned count and leaves fresh rows intact', function () {
        trackingRow('ESB00000005', Carbon::now('UTC')->subDay());
        trackingRow('ESB00000006', Carbon::now('UTC')->subDay());
        $kept = trackingRow('ESB00000007', Carbon::now('UTC')->addWeek());

        $this->artisan('roi:prune-verification-queries')
            ->expectsOutputToContain('Pruned 2 expired verification query row(s).')
            ->assertSuccessful();

        expect(VerificationQuery::count())->toBe(1)
            ->and(VerificationQuery::find($kept->id))->not->toBeNull();
    });
});
