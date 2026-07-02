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
    /**
     * Canonical default provider order — single source of truth.
     *
     * Official VIES SOAP first (most reliable), then the REST alternative,
     * then the free isvat fallback. Paid providers (vatlayer, viesapi) are
     * intentionally excluded from the default: they are only registered when
     * an API key is configured.
     *
     * @var list<string>
     */
    public const DEFAULT_PROVIDER_ORDER = ['vies_soap', 'vies_rest', 'isvat'];

    protected Collection $providers;

    /** @var list<string> */
    protected array $providerOrder;

    /**
     * @param  list<string>  $providerOrder
     */
    public function __construct(array $providerOrder = [])
    {
        $this->providers = collect();
        $this->providerOrder = $providerOrder !== []
            ? $providerOrder
            : $this->resolveConfiguredOrder();
    }

    /**
     * Resolve the provider order from config, falling back to the canonical
     * default. Safe to call outside a Laravel context (returns the default).
     *
     * @return list<string>
     */
    protected function resolveConfiguredOrder(): array
    {
        try {
            if (function_exists('config') && function_exists('app') && app()->bound('config')) {
                $configValue = config('lararoi.providers_order', self::DEFAULT_PROVIDER_ORDER);

                return is_array($configValue) && $configValue !== []
                    ? $configValue
                    : self::DEFAULT_PROVIDER_ORDER;
            }
        } catch (\Throwable) {
            // Config not available (non-Laravel context): fall through to default.
        }

        return self::DEFAULT_PROVIDER_ORDER;
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
     * Get the current provider order.
     *
     * @return list<string>
     */
    public function getProviderOrder(): array
    {
        return $this->providerOrder;
    }

    /**
     * Set provider order
     *
     * @param  list<string>  $order
     */
    public function setProviderOrder(array $order): self
    {
        $this->providerOrder = $order;

        return $this;
    }
}
