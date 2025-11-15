<?php

use Aichadigital\Lararoi\Contracts\VatProviderInterface;
use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use Aichadigital\Lararoi\Providers\ViesRestProvider;
use Aichadigital\Lararoi\Providers\ViesSoapProvider;
use Aichadigital\Lararoi\Services\VatProviderManager;

describe('VatProviderManager - Provider Registration', function () {
    it('registers providers correctly', function () {
        $manager = new VatProviderManager(['vies_rest']);
        $manager->register('vies_rest', new ViesRestProvider);

        expect($manager->getProvider('vies_rest'))->not->toBeNull();
    });

    it('returns null for unregistered provider', function () {
        $manager = new VatProviderManager([]);

        expect($manager->getProvider('non_existent'))->toBeNull();
    });

    it('gets all registered providers', function () {
        $manager = new VatProviderManager(['vies_rest', 'vies_soap']);
        $manager->register('vies_rest', new ViesRestProvider);
        $manager->register('vies_soap', new ViesSoapProvider(false));

        $providers = $manager->getProviders();

        expect($providers)->toHaveCount(2);
        expect($providers)->toHaveKey('vies_rest');
        expect($providers)->toHaveKey('vies_soap');
    });
});

describe('VatProviderManager - Fallback Order', function () {
    it('throws exception when all providers fail', function () {
        // Create a mock provider that throws ApiUnavailableException
        $provider = Mockery::mock(VatProviderInterface::class);
        $provider->shouldReceive('isAvailable')->andReturn(true);
        $provider->shouldReceive('verify')
            ->with('99999999', 'ES')
            ->once()
            ->andThrow(new ApiUnavailableException('MOCK_PROVIDER', new \Exception('Test error')));

        $order = ['mock_provider'];
        $manager = new VatProviderManager($order);
        $manager->register('mock_provider', $provider);

        expect(fn () => $manager->verify('99999999', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });

    it('skips unavailable providers', function () {
        $manager = new VatProviderManager(['vies_rest']);

        // Create a mock unavailable provider
        $provider = Mockery::mock(VatProviderInterface::class);
        $provider->shouldReceive('isAvailable')->andReturn(false);
        $provider->shouldReceive('verify')->never();

        $manager->register('unavailable', $provider);

        // Should throw exception since no provider is available
        expect(fn () => $manager->verify('99999999', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });
});

describe('VatProviderManager - Free vs Paid Providers', function () {
    it('separates free and paid providers', function () {
        $manager = new VatProviderManager(['vies_rest']);
        $manager->register('vies_rest', new ViesRestProvider);

        $freeProviders = $manager->getFreeProviders();
        $paidProviders = $manager->getPaidProviders();

        expect($freeProviders)->toHaveKey('vies_rest');
        expect($paidProviders)->not->toHaveKey('vies_rest');
    });
});

describe('VatProviderManager - Order Manipulation', function () {
    it('can set provider order', function () {
        $manager = new VatProviderManager(['vies_rest']);
        $newOrder = ['vies_soap', 'vies_rest'];

        $result = $manager->setProviderOrder($newOrder);

        expect($result)->toBe($manager);
    });
});
