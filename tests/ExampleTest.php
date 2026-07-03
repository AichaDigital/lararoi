<?php

use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;
use Aichadigital\Lararoi\Services\VatProviderManager;
use Aichadigital\Lararoi\Services\VatVerificationService;

test('service provider is correctly registered', function () {
    expect(app()->bound(VatVerificationServiceInterface::class))->toBeTrue();
    expect(app()->bound(VatProviderManager::class))->toBeTrue();
});

test('can resolve VAT verification service', function () {
    $service = app(VatVerificationServiceInterface::class);

    expect($service)->toBeInstanceOf(VatVerificationService::class);
});

test('can resolve VAT provider manager', function () {
    $manager = app(VatProviderManager::class);

    expect($manager)->toBeInstanceOf(VatProviderManager::class);
    expect($manager->getProviders())->not->toBeEmpty();
});

test('vat verification table exists under the roi_ prefix', function () {
    expect(Schema::hasTable('roi_vat_verifications'))->toBeTrue();
    expect(Schema::hasTable('vat_verifications'))->toBeFalse();
});
