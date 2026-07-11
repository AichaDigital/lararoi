<?php

use Aichadigital\Lararoi\Support\VatFormat;

describe('VatFormat - per-country syntax', function () {
    it('accepts a well-formed Spanish VAT', function () {
        expect(VatFormat::isValid('ES', 'B12345678'))->toBeTrue();
    });

    it('rejects a malformed Spanish VAT', function () {
        expect(VatFormat::isValid('ES', '123'))->toBeFalse()
            ->and(VatFormat::isValid('ES', 'ABCDEFGHI'))->toBeFalse();
    });

    it('accepts a well-formed German VAT', function () {
        expect(VatFormat::isValid('DE', '123456789'))->toBeTrue();
    });

    it('rejects a malformed German VAT', function () {
        expect(VatFormat::isValid('DE', '12345'))->toBeFalse();
    });

    it('accepts a well-formed French VAT', function () {
        expect(VatFormat::isValid('FR', 'XX123456789'))->toBeTrue();
    });

    it('accepts a well-formed Italian VAT', function () {
        expect(VatFormat::isValid('IT', '12345678901'))->toBeTrue();
    });
});

describe('VatFormat - unknown countries are permissive', function () {
    it('accepts any value for a non-EU two-letter code', function () {
        expect(VatFormat::isValid('ZZ', 'whatever'))->toBeTrue();
    });

    it('accepts any value for a non two-letter country token', function () {
        expect(VatFormat::isValid('ESP', 'B12345678'))->toBeTrue();
    });

    it('reports whether a country is covered', function () {
        expect(VatFormat::isKnownCountry('ES'))->toBeTrue()
            ->and(VatFormat::isKnownCountry('DE'))->toBeTrue()
            ->and(VatFormat::isKnownCountry('ZZ'))->toBeFalse()
            ->and(VatFormat::isKnownCountry('ESP'))->toBeFalse();
    });
});

describe('VatFormat - masking', function () {
    it('masks a VAT number keeping only the first and last two characters', function () {
        expect(VatFormat::mask('B12345678'))->toBe('B1*****78');
    });

    it('fully masks short values so nothing useful leaks', function () {
        expect(VatFormat::mask('AB12'))->toBe('****')
            ->and(VatFormat::mask('AB'))->toBe('**')
            ->and(VatFormat::mask(''))->toBe('');
    });

    it('preserves the length of the masked value', function () {
        expect(strlen(VatFormat::mask('ESB12345678')))->toBe(strlen('ESB12345678'));
    });
});
