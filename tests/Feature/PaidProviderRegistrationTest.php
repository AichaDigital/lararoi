<?php

use Aichadigital\Lararoi\Services\VatProviderManager;

/**
 * Resolve a fresh VatProviderManager from the container after overriding
 * the paid-provider configuration.
 *
 * @param  array<string, mixed>  $providerConfig
 */
function resolveManagerWithProviderConfig(array $providerConfig): VatProviderManager
{
    config()->set('lararoi.provider_config', $providerConfig);
    app()->forgetInstance(VatProviderManager::class);

    return app(VatProviderManager::class);
}

describe('Paid provider registration (enabled && api_key, enabled defaults true)', function () {
    it('registers a paid provider when api_key is set and enabled is absent (defaults true)', function () {
        $manager = resolveManagerWithProviderConfig([
            'vatlayer' => ['api_key' => 'test-key'],
        ]);

        expect($manager->getProvider('vatlayer'))->not->toBeNull();
    });

    it('does not register a paid provider when enabled is false even with api_key', function () {
        $manager = resolveManagerWithProviderConfig([
            'vatlayer' => ['api_key' => 'test-key', 'enabled' => false],
        ]);

        expect($manager->getProvider('vatlayer'))->toBeNull();
    });

    it('does not register a paid provider when api_key is missing even if enabled', function () {
        $manager = resolveManagerWithProviderConfig([
            'viesapi' => ['enabled' => true],
        ]);

        expect($manager->getProvider('viesapi'))->toBeNull();
    });

    it('registers viesapi when api_key is set and enabled is true', function () {
        $manager = resolveManagerWithProviderConfig([
            'viesapi' => ['api_key' => 'k', 'enabled' => true],
        ]);

        expect($manager->getProvider('viesapi'))->not->toBeNull();
    });

    it('defaults the published enabled flag to true for paid providers', function () {
        expect(config('lararoi.provider_config.vatlayer.enabled'))->toBeTrue()
            ->and(config('lararoi.provider_config.viesapi.enabled'))->toBeTrue();
    });
});
