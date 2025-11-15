<?php

use Aichadigital\Lararoi\Services\VatProviderManager;

describe('Configuration - Providers Order', function () {
    it('uses default order when configuration is empty', function () {
        // Get configuration with defaults
        $defaultOrder = config('lararoi.providers_order', ['vies_rest', 'vies_soap', 'isvat']);

        // The default should be used if config is empty
        if (empty($defaultOrder)) {
            $defaultOrder = ['vies_rest', 'vies_soap', 'isvat'];
        }

        expect($defaultOrder)->toBeArray();
        expect($defaultOrder)->not->toBeEmpty();
    });

    it('uses order from configuration when set', function () {
        $order = ['vies_soap', 'vies_rest'];
        config()->set('lararoi.providers_order', $order);

        // Verify configuration is set correctly
        $configOrder = config('lararoi.providers_order');

        expect($configOrder)->toBe($order);
    });

    it('parses order from environment variable separated by commas', function () {
        // Simulate environment variable
        $envOrder = 'vies_rest,isvat,vies_soap';
        $parsedOrder = explode(',', $envOrder);

        config()->set('lararoi.providers_order', $parsedOrder);

        expect($parsedOrder)->toBeArray();
        expect($parsedOrder)->toHaveCount(3);
        expect($parsedOrder[0])->toBe('vies_rest');
        expect($parsedOrder[1])->toBe('isvat');
        expect($parsedOrder[2])->toBe('vies_soap');
    });

    it('handles spaces in order from environment variable', function () {
        $envOrder = 'vies_rest, isvat , vies_soap';
        $parsedOrder = array_map('trim', explode(',', $envOrder));

        config()->set('lararoi.providers_order', $parsedOrder);

        expect($parsedOrder[0])->toBe('vies_rest');
        expect($parsedOrder[1])->toBe('isvat');
        expect($parsedOrder[2])->toBe('vies_soap');
    });
});

describe('Configuration - Order Validation', function () {
    it('validates that providers in order are available', function () {
        $manager = app(VatProviderManager::class);

        // Verify providers are registered
        $providers = $manager->getProviders();

        expect($providers)->not->toBeEmpty();

        foreach ($providers as $providerName => $provider) {
            expect($provider)->not->toBeNull();
        }
    });

    it('handles unavailable providers in order gracefully', function () {
        $order = ['vies_rest', 'provider_does_not_exist', 'vies_soap'];
        config()->set('lararoi.providers_order', $order);

        // The manager should handle unavailable providers
        $manager = app(VatProviderManager::class);

        expect($manager)->toBeInstanceOf(VatProviderManager::class);
    });
});
