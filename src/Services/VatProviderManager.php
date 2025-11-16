<?php

namespace Aichadigital\Lararoi\Services;

use Aichadigital\Lararoi\Contracts\VatProviderInterface;
use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use Illuminate\Support\Collection;

/**
 * Manager for managing multiple VAT providers
 *
 * Similar to Larabill's provider system for payment services.
 * Allows configuring provider order and automatic fallback.
 */
class VatProviderManager
{
    protected Collection $providers;

    protected array $providerOrder;

    public function __construct(array $providerOrder = [])
    {
        $this->providers = collect();

        // Use provided order or try to get from config (if Laravel is available)
        if ($providerOrder !== []) {
            $this->providerOrder = $providerOrder;
        } else {
            // Try to get from config if Laravel helper is available
            try {
                if (function_exists('config') && function_exists('app') && app()->bound('config')) {
                    $configValue = config('lararoi.providers_order', [
                        'vies_rest',
                        'vies_soap',
                        'isvat',
                        'vatlayer',
                        'viesapi',
                    ]);
                    $this->providerOrder = is_array($configValue) ? $configValue : [
                        'vies_rest',
                        'vies_soap',
                        'isvat',
                        'vatlayer',
                        'viesapi',
                    ];
                } else {
                    $this->providerOrder = [
                        'vies_rest',
                        'vies_soap',
                        'isvat',
                        'vatlayer',
                        'viesapi',
                    ];
                }
            } catch (\Throwable $e) {
                // If config is not available, use default
                $this->providerOrder = [
                    'vies_rest',
                    'vies_soap',
                    'isvat',
                    'vatlayer',
                    'viesapi',
                ];
            }
        }
    }

    /**
     * Register a provider
     */
    public function register(string $name, VatProviderInterface $provider): self
    {
        $this->providers->put($name, $provider);

        return $this;
    }

    /**
     * Verify VAT using providers in order with fallback
     *
     * @throws ApiUnavailableException
     */
    public function verify(string $vatNumber, string $countryCode): array
    {
        $lastException = null;

        foreach ($this->providerOrder as $providerName) {
            $provider = $this->providers->get($providerName);

            if (! $provider) {
                continue;
            }

            // Check if provider is available
            if (! $provider->isAvailable()) {
                continue;
            }

            try {
                return $provider->verify($vatNumber, $countryCode);
            } catch (ApiUnavailableException $e) {
                $lastException = $e;

                // Continue with next provider
                continue;
            }
        }

        // If we reach here, all providers failed
        throw $lastException ?? new ApiUnavailableException('UNKNOWN', new \Exception('All providers failed'));
    }

    /**
     * Get a specific provider by name
     */
    public function getProvider(string $name): ?VatProviderInterface
    {
        return $this->providers->get($name);
    }

    /**
     * Get all registered providers
     */
    public function getProviders(): Collection
    {
        return $this->providers;
    }

    /**
     * Get only free providers
     */
    public function getFreeProviders(): Collection
    {
        return $this->providers->filter(fn ($provider) => $provider->isFree());
    }

    /**
     * Get only paid providers
     */
    public function getPaidProviders(): Collection
    {
        return $this->providers->filter(fn ($provider) => ! $provider->isFree());
    }

    /**
     * Set provider order
     */
    public function setProviderOrder(array $order): self
    {
        $this->providerOrder = $order;

        return $this;
    }
}
