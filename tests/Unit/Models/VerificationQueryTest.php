<?php

use Aichadigital\Lararoi\Models\VerificationQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

describe('VerificationQuery Model - Schema (ADR-002 D3)', function () {
    it('uses the roi_ prefixed append-only table', function () {
        expect((new VerificationQuery)->getTable())->toBe('roi_verification_queries');
        expect(Schema::hasTable('roi_verification_queries'))->toBeTrue();
    });

    it('has every tracked column and no soft-delete column', function () {
        $columns = [
            'id', 'consumer', 'subject_reference', 'vat_code', 'country_code',
            'is_valid', 'api_source', 'cache_hit', 'response_snapshot',
            'queried_at', 'retention_until', 'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            expect(Schema::hasColumn('roi_verification_queries', $column))->toBeTrue();
        }

        expect(Schema::hasColumn('roi_verification_queries', 'deleted_at'))->toBeFalse();
    });
});

describe('VerificationQuery Model - Casts & getters', function () {
    it('casts booleans, json and datetimes', function () {
        $row = VerificationQuery::create([
            'consumer' => 'larabill',
            'subject_reference' => 'ref-1',
            'vat_code' => 'ESB12345678',
            'country_code' => 'ES',
            'is_valid' => 1,
            'api_source' => 'VIES_SOAP',
            'cache_hit' => 0,
            'response_snapshot' => ['is_valid' => true, 'vat_code' => 'ESB12345678'],
            'queried_at' => Carbon::now('UTC'),
            'retention_until' => Carbon::now('UTC')->addDays(10),
        ]);

        $fresh = $row->fresh();

        expect($fresh->is_valid)->toBeTrue()
            ->and($fresh->cache_hit)->toBeFalse()
            ->and($fresh->response_snapshot)->toBeArray()
            ->and($fresh->queried_at)->toBeInstanceOf(Carbon::class)
            ->and($fresh->retention_until)->toBeInstanceOf(Carbon::class);
    });

    it('exposes a model-agnostic getter surface', function () {
        $row = VerificationQuery::create([
            'consumer' => 'larabill',
            'subject_reference' => 'ref-1',
            'vat_code' => 'ESB12345678',
            'country_code' => 'ES',
            'is_valid' => true,
            'api_source' => 'VIES_SOAP',
            'cache_hit' => true,
            'response_snapshot' => ['api_source' => 'VIES_SOAP'],
            'queried_at' => Carbon::now('UTC'),
            'retention_until' => null,
        ]);

        expect($row->getConsumer())->toBe('larabill')
            ->and($row->getSubjectReference())->toBe('ref-1')
            ->and($row->getVatCode())->toBe('ESB12345678')
            ->and($row->getCountryCode())->toBe('ES')
            ->and($row->isValid())->toBeTrue()
            ->and($row->getApiSource())->toBe('VIES_SOAP')
            ->and($row->isCacheHit())->toBeTrue()
            ->and($row->getResponseSnapshot())->toBe(['api_source' => 'VIES_SOAP'])
            ->and($row->getQueriedAt())->toBeInstanceOf(Carbon::class)
            ->and($row->getRetentionUntil())->toBeNull();
    });
});

describe('VerificationQuery Model - Append-only (ADR-002 D3)', function () {
    it('keeps a new row for each verification of the same NIF (the opposite of the cache)', function () {
        $attributes = [
            'consumer' => 'larabill',
            'subject_reference' => 'ref-1',
            'vat_code' => 'ESB12345678',
            'country_code' => 'ES',
            'is_valid' => true,
            'api_source' => 'VIES_SOAP',
            'cache_hit' => false,
            'response_snapshot' => null,
            'queried_at' => Carbon::now('UTC'),
            'retention_until' => null,
        ];

        VerificationQuery::create($attributes);
        VerificationQuery::create($attributes);

        expect(VerificationQuery::where('vat_code', 'ESB12345678')->count())->toBe(2);
    });
});
