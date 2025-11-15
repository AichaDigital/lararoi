<?php

use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;
use Aichadigital\Lararoi\Services\VatProviderManager;

test('service provider is correctly registered', function () {
    expect(app()->bound(VatVerificationServiceInterface::class))->toBeTrue();
    expect(app()->bound(VatProviderManager::class))->toBeTrue();
});

test('can resolve VAT verification service', function () {
    $service = app(VatVerificationServiceInterface::class);

    expect($service)->toBeInstanceOf(\Aichadigital\Lararoi\Services\VatVerificationService::class);
});

test('can resolve VAT provider manager', function () {
    $manager = app(VatProviderManager::class);

    expect($manager)->toBeInstanceOf(VatProviderManager::class);
    expect($manager->getProviders())->not->toBeEmpty();
});

test('vat verification table exists', function () {
    expect(\Schema::hasTable('vat_verifications'))->toBeTrue();
});
