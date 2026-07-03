<?php

use Aichadigital\Lararoi\Models\VatVerification;
use Aichadigital\Lararoi\Services\VatProviderManager;

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for VAT verifications.
    |
    */
    'cache' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Cache
        |--------------------------------------------------------------------------
        |
        | Enable or disable caching of VAT verifications.
        |
        | - true: Cache verifications in memory (Laravel Cache) and database
        | - false: Most agnostic mode - just return verification data without caching
        |
        | Default: true
        |
        */
        'enabled' => env('CACHE_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Cache TTL (Time To Live)
        |--------------------------------------------------------------------------
        |
        | Cache time to live in seconds. When cache is enabled, verifications
        | are cached both in memory (Laravel Cache) and in database.
        |
        | When cache expires, the service will re-query the provider and
        | save the new data. The response will indicate if data was refreshed.
        |
        | Default: 24 hours (86400 seconds)
        |
        */
        'ttl' => env('CACHE_TTL', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time in seconds to wait for an API response.
    | If a provider does not respond within this time, the system
    | will automatically try the next provider in the fallback order.
    | Default: 15 seconds
    |
    */
    'timeout' => env('API_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Providers Order
    |--------------------------------------------------------------------------
    |
    | Order of providers to use. They will be tried in this order
    | until one responds correctly.
    |
    | Available FREE providers:
    | - 'vies_soap': VIES SOAP API (official) - ⭐⭐⭐⭐ Most reliable
    | - 'vies_rest': VIES REST API (unofficial but simpler) - ⭐⭐⭐
    | - 'isvat': isvat.eu (free with 100/month limit) - ⭐⭐
    |
    | Available PAID providers:
    | - 'viesapi': viesapi.eu (free test plan, then paid) - ⭐⭐⭐⭐⭐
    | - 'vatlayer': vatlayer.com (100 queries/month free, then paid) - ⭐⭐⭐⭐
    |
    | Default order: Official VIES first, then REST alternative, then free fallback
    |
    */
    'providers_order' => ($providersOrder = env('PROVIDERS_ORDER'))
        ? array_map('trim', explode(',', (string) $providersOrder))
        : VatProviderManager::DEFAULT_PROVIDER_ORDER,

    /*
    |--------------------------------------------------------------------------
    | VIES Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for VIES services from the European Commission.
    |
    */
    'vies' => [
        /*
        |--------------------------------------------------------------------------
        | Test Mode
        |--------------------------------------------------------------------------
        |
        | If enabled, uses the VIES test service.
        | Useful for development and testing.
        |
        */
        'test_mode' => env('VIES_TEST_MODE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Specific configuration for each paid provider.
    |
    | A paid provider is registered only when it is enabled AND an API key is
    | present. The 'enabled' flag controls explicit activation and defaults to
    | true, so setting just the API key is enough to activate a provider; set
    | 'enabled' => false to keep credentials in place while disabling it.
    |
    */
    'provider_config' => [
        'vatlayer' => [
            'enabled' => env('VATLAYER_ENABLED', true),
            'api_key' => env('VATLAYER_KEY'),
        ],

        'viesapi' => [
            'enabled' => env('VIESAPI_ENABLED', true),
            'api_key' => env('VIESAPI_KEY'),
            'api_secret' => env('VIESAPI_SECRET'), // Second value if provided
            'ip' => env('VIESAPI_IP'), // IP for whitelist/configuration
        ],

        'isvat' => [
            'use_live' => env('ISVAT_USE_LIVE', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the VAT verification model.
    |
    | Supply your own model via VAT_VERIFICATION_MODEL (or the 'class' key);
    | it must implement VatVerificationModelInterface.
    |
    | Example:
    | 'models' => [
    |     'vat_verification' => ['class' => \App\Models\CustomVatVerification::class],
    | ],
    |
    */
    'models' => [
        'vat_verification' => [
            // Model class to use (must implement VatVerificationModelInterface)
            'class' => env('VAT_VERIFICATION_MODEL', VatVerification::class),
        ],
    ],

];
