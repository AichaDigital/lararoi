<?php

use Aichadigital\Lararoi\Models\VatVerification;
use Illuminate\Support\Carbon;

describe('VatVerification Model - Basic Properties', function () {
    it('can create a VAT verification record', function () {
        $verification = VatVerification::create([
            'vat_code' => 'B12345678',
            'country_code' => 'ES',
            'is_valid' => true,
            'company_name' => 'Test Company',
            'company_address' => 'Test Address',
            'api_source' => 'VIES_REST',
            'verified_at' => now(),
            'response_data' => ['test' => 'data'],
        ]);

        expect($verification)->toBeInstanceOf(VatVerification::class);
        expect($verification->vat_code)->toBe('B12345678');
        expect($verification->country_code)->toBe('ES');
        expect($verification->is_valid)->toBeTrue();
    });

    it('casts is_valid to boolean', function () {
        $verification = VatVerification::create([
            'vat_code' => 'B12345678',
            'country_code' => 'ES',
            'is_valid' => 1,
            'verified_at' => now(),
        ]);

        expect($verification->is_valid)->toBeBool();
        expect($verification->is_valid)->toBeTrue();
    });

    it('casts verified_at to Carbon instance', function () {
        $now = now();
        $verification = VatVerification::create([
            'vat_code' => 'B12345678',
            'country_code' => 'ES',
            'is_valid' => true,
            'verified_at' => $now,
        ]);

        expect($verification->verified_at)->toBeInstanceOf(Carbon::class);
    });

    it('casts response_data to array', function () {
        $verification = VatVerification::create([
            'vat_code' => 'B12345678',
            'country_code' => 'ES',
            'is_valid' => true,
            'verified_at' => now(),
            'response_data' => ['key' => 'value'],
        ]);

        expect($verification->response_data)->toBeArray();
        expect($verification->response_data['key'])->toBe('value');
    });
});

describe('VatVerification Model - Finder Methods', function () {
    it('finds by VAT code and country', function () {
        VatVerification::create([
            'vat_code' => 'B12345678',
            'country_code' => 'ES',
            'is_valid' => true,
            'verified_at' => now(),
        ]);

        $found = VatVerification::findByVatCodeAndCountry('B12345678', 'ES');

        expect($found)->not->toBeNull();
        expect($found->vat_code)->toBe('B12345678');
        expect($found->country_code)->toBe('ES');
    });

    it('normalizes country code to uppercase when finding', function () {
        VatVerification::create([
            'vat_code' => 'B12345678',
            'country_code' => 'ES',
            'is_valid' => true,
            'verified_at' => now(),
        ]);

        $found = VatVerification::findByVatCodeAndCountry('B12345678', 'es');

        expect($found)->not->toBeNull();
        expect($found->country_code)->toBe('ES');
    });

    it('returns null when VAT not found', function () {
        $found = VatVerification::findByVatCodeAndCountry('NOTEXIST', 'ES');

        expect($found)->toBeNull();
    });
});

describe('VatVerification Model - Expiration Logic', function () {
    it('is expired when verified_at is null', function () {
        $verification = new VatVerification([
            'vat_code' => 'B12345678',
            'country_code' => 'ES',
            'is_valid' => true,
        ]);

        expect($verification->isExpired())->toBeTrue();
    });

    it('is not expired when recently verified', function () {
        $verification = VatVerification::create([
            'vat_code' => 'B12345678',
            'country_code' => 'ES',
            'is_valid' => true,
            'verified_at' => now(),
        ]);

        expect($verification->isExpired())->toBeFalse();
    });

    it('is expired when verified_at exceeds TTL', function () {
        config(['lararoi.cache_ttl' => 3600]); // 1 hour

        $verification = VatVerification::create([
            'vat_code' => 'B12345678',
            'country_code' => 'ES',
            'is_valid' => true,
            'verified_at' => now()->subHours(2),
        ]);

        expect($verification->isExpired())->toBeTrue();
    });

    it('is not expired when within TTL', function () {
        config(['lararoi.cache_ttl' => 7200]); // 2 hours

        $verification = VatVerification::create([
            'vat_code' => 'B12345678',
            'country_code' => 'ES',
            'is_valid' => true,
            'verified_at' => now()->subMinutes(30),
        ]);

        expect($verification->isExpired())->toBeFalse();
    });
});

describe('VatVerification Model - Getter Methods', function () {
    it('gets VAT code', function () {
        $verification = new VatVerification(['vat_code' => 'B12345678']);

        expect($verification->getVatCode())->toBe('B12345678');
    });

    it('gets country code', function () {
        $verification = new VatVerification(['country_code' => 'ES']);

        expect($verification->getCountryCode())->toBe('ES');
    });

    it('gets is_valid status', function () {
        $verification = new VatVerification(['is_valid' => true]);

        expect($verification->isValid())->toBeTrue();
    });

    it('returns false when is_valid is null', function () {
        $verification = new VatVerification;

        expect($verification->isValid())->toBeFalse();
    });

    it('gets company name', function () {
        $verification = new VatVerification(['company_name' => 'Test Company']);

        expect($verification->getCompanyName())->toBe('Test Company');
    });

    it('gets company address', function () {
        $verification = new VatVerification(['company_address' => 'Test Address']);

        expect($verification->getCompanyAddress())->toBe('Test Address');
    });

    it('gets API source', function () {
        $verification = new VatVerification(['api_source' => 'VIES_REST']);

        expect($verification->getApiSource())->toBe('VIES_REST');
    });

    it('returns UNKNOWN when api_source is null', function () {
        $verification = new VatVerification;

        expect($verification->getApiSource())->toBe('UNKNOWN');
    });

    it('gets verified_at timestamp', function () {
        $now = now();
        $verification = VatVerification::create([
            'vat_code' => 'B12345678',
            'country_code' => 'ES',
            'is_valid' => true,
            'verified_at' => $now,
        ]);

        expect($verification->getVerifiedAt())->toBeInstanceOf(Carbon::class);
    });

    it('gets response data', function () {
        $verification = new VatVerification(['response_data' => ['key' => 'value']]);

        expect($verification->getResponseData())->toBeArray();
        expect($verification->getResponseData()['key'])->toBe('value');
    });
});

describe('VatVerification Model - Scopes', function () {
    beforeEach(function () {
        VatVerification::create([
            'vat_code' => 'B12345678',
            'country_code' => 'ES',
            'is_valid' => true,
            'verified_at' => now(),
        ]);

        VatVerification::create([
            'vat_code' => 'B87654321',
            'country_code' => 'ES',
            'is_valid' => false,
            'verified_at' => now(),
        ]);

        VatVerification::create([
            'vat_code' => 'DE123456789',
            'country_code' => 'DE',
            'is_valid' => true,
            'verified_at' => now(),
        ]);
    });

    it('filters by VAT code using scope', function () {
        $results = VatVerification::byVatCode('B12345678')->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->vat_code)->toBe('B12345678');
    });

    it('filters by country using scope', function () {
        $results = VatVerification::byCountry('ES')->get();

        expect($results)->toHaveCount(2);
        expect($results->every(fn ($v) => $v->country_code === 'ES'))->toBeTrue();
    });

    it('filters by country normalizing to uppercase', function () {
        $results = VatVerification::byCountry('es')->get();

        expect($results)->toHaveCount(2);
    });

    it('filters valid verifications using scope', function () {
        $results = VatVerification::valid()->get();

        expect($results)->toHaveCount(2);
        expect($results->every(fn ($v) => $v->is_valid === true))->toBeTrue();
    });

    it('filters expired verifications using scope', function () {
        config(['lararoi.cache_ttl' => 3600]); // 1 hour

        VatVerification::create([
            'vat_code' => 'EXPIRED123',
            'country_code' => 'ES',
            'is_valid' => true,
            'verified_at' => now()->subHours(2),
        ]);

        $results = VatVerification::expired()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->vat_code)->toBe('EXPIRED123');
    });

    it('can chain scopes', function () {
        $results = VatVerification::byCountry('ES')->valid()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->vat_code)->toBe('B12345678');
    });
});

describe('VatVerification Model - Soft Deletes', function () {
    it('soft deletes verification', function () {
        $verification = VatVerification::create([
            'vat_code' => 'B12345678',
            'country_code' => 'ES',
            'is_valid' => true,
            'verified_at' => now(),
        ]);

        $verification->delete();

        expect(VatVerification::find($verification->id))->toBeNull();
        expect(VatVerification::withTrashed()->find($verification->id))->not->toBeNull();
    });

    it('can restore soft deleted verification', function () {
        $verification = VatVerification::create([
            'vat_code' => 'B12345678',
            'country_code' => 'ES',
            'is_valid' => true,
            'verified_at' => now(),
        ]);

        $verification->delete();
        $verification->restore();

        expect(VatVerification::find($verification->id))->not->toBeNull();
    });
});
