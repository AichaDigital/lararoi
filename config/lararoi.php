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
    'cache_ttl' => env('LARAROI_CACHE_TTL', 86400),

    /*
    |--------------------------------------------------------------------------
    | Providers Order
    |--------------------------------------------------------------------------
    |
    | Order of providers to use. They will be tried in this order
    | until one responds correctly.
    |
    | Available FREE providers:
    | - 'vies_rest': VIES REST API (unofficial but simpler)
    | - 'vies_soap': VIES SOAP API (official)
    | - 'isvat': isvat.eu (free with 100/month limit)
    | - 'aeat': AEAT Web Service (Spain only, requires certificate)
    |
    | Available PAID providers:
    | - 'vatlayer': vatlayer.com (100 queries/month free, then paid)
    | - 'viesapi': viesapi.eu (free test plan, then paid)
    |
    | Recommended order: free first, then paid as fallback
    |
    */
    'providers_order' => env('LARAROI_PROVIDERS_ORDER', 'vies_rest,vies_soap,isvat,vatlayer,viesapi')
        ? explode(',', env('LARAROI_PROVIDERS_ORDER', 'vies_rest,vies_soap,isvat,vatlayer,viesapi'))
        : ['vies_rest', 'vies_soap', 'isvat', 'vatlayer', 'viesapi'],

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
        'test_mode' => env('LARAROI_VIES_TEST_MODE', false),
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
        'endpoint' => env('LARAROI_AEAT_ENDPOINT',
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
        'enabled' => env('LARAROI_LOGGING_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Log Level
        |--------------------------------------------------------------------------
        |
        | Logging level for verifications.
        | Options: 'debug', 'info', 'warning', 'error'
        |
        */
        'level' => env('LARAROI_LOGGING_LEVEL', 'info'),
    ],
];
