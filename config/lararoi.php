<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | Cache time to live in seconds. Verifications are cached
    | both in memory (Laravel Cache) and in database.
    | Default: 24 hours (86400 seconds)
    |
    */
    'cache_ttl' => env('CACHE_TTL', 86400),

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
    | - 'aeat': AEAT Web Service (Spain only, requires certificate) - ⭐⭐⭐⭐⭐ Most reliable
    | - 'vies_soap': VIES SOAP API (official) - ⭐⭐⭐
    | - 'vies_rest': VIES REST API (unofficial but simpler) - ⭐⭐
    | - 'isvat': isvat.eu (free with 100/month limit) - ⭐⭐⭐
    |
    | Available PAID providers:
    | - 'viesapi': viesapi.eu (free test plan, then paid) - ⭐⭐⭐⭐⭐
    | - 'vatlayer': vatlayer.com (100 queries/month free, then paid) - ⭐⭐⭐⭐
    |
    | Default order: AEAT first (Spain), then official VIES, then free alternatives
    |
    */
    'providers_order' => env('PROVIDERS_ORDER', 'aeat,vies_soap,vies_rest,isvat')
        ? array_map('trim', explode(',', env('PROVIDERS_ORDER', 'aeat,vies_soap,vies_rest,isvat')))
        : ['aeat', 'vies_soap', 'vies_rest', 'isvat'],

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
    | AEAT Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AEAT Web Service (Spain only).
    | Requires digital certificate (individual or company representative).
    |
    | Option 1: PKCS#12 Certificate (.p12 or .pfx) - RECOMMENDED
    |   - p12_path: Path to .p12 file
    |   - passphrase: Certificate password (if any)
    |
    | Option 2: Separate certificate and key
    |   - cert_path: Path to certificate (.crt or .pem)
    |   - key_path: Path to private key (.key)
    |   - passphrase: Private key password (if any)
    |
    | Available endpoints:
    |   - Personal/Representative: https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP
    |   - Electronic seal: https://www10.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP
    |
    */
    'aeat' => [
        // Generic environment variables for certificates (shared between packages)
        'p12_path' => env('CERT_P12_PATH'),
        'passphrase' => env('CERT_P12_PASSWORD'),

        // Endpoint (default: personal/representative)
        'endpoint' => env('AEAT_ENDPOINT',
            'https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Specific configuration for each paid provider.
    |
    */
    'provider_config' => [
        'vatlayer' => [
            'enabled' => env('VATLAYER_ENABLED', false),
            'api_key' => env('VATLAYER_KEY'),
        ],

        'viesapi' => [
            'enabled' => env('VIESAPI_ENABLED', false),
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
    | Allows customizing the model if it needs to be extended.
    |
    */
    'models' => [
        'vat_verification' => \Aichadigital\Lararoi\Models\VatVerification::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Logging configuration for VAT verifications.
    |
    */
    'logging' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Logging
        |--------------------------------------------------------------------------
        |
        | If enabled, all VAT verifications will be logged
        | in Laravel logs. Useful for auditing and debugging.
        |
        */
        'enabled' => env('LOGGING_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Log Level
        |--------------------------------------------------------------------------
        |
        | Logging level for verifications.
        | Options: 'debug', 'info', 'warning', 'error'
        |
        */
        'level' => env('LOGGING_LEVEL', 'info'),
    ],
];
