<?php

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
    'providers_order' => env('PROVIDERS_ORDER', 'vies_soap,vies_rest,isvat')
        ? array_map('trim', explode(',', env('PROVIDERS_ORDER', 'vies_soap,vies_rest,isvat')))
        : ['vies_soap', 'vies_rest', 'isvat'],

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
    | Allows full customization for integration with your application.
    |
    | You can:
    | - Use the default model or specify your own custom model class
    | - Customize the primary key field name (e.g., 'id', 'uuid', 'ulid')
    | - Customize the foreign key name for relationships (e.g., 'vat_verification_id', 'custom_vat_id')
    |
    | Example for custom model with UUID:
    | 'models' => [
    |     'vat_verification' => [
    |         'class' => \App\Models\CustomVatVerification::class,
    |         'primary_key' => 'uuid',
    |         'foreign_key' => 'custom_vat_uuid',
    |     ],
    | ],
    |
    */
    'models' => [
        'vat_verification' => [
            // Model class to use (must implement VatVerificationModelInterface)
            'class' => env('VAT_VERIFICATION_MODEL', \Aichadigital\Lararoi\Models\VatVerification::class),

            // Primary key column name (for the vat_verifications table)
            'primary_key' => env('VAT_VERIFICATION_PRIMARY_KEY', 'id'),

            // Foreign key name for relationships (e.g., in customers table)
            // Format: {table}_{column} following Laravel conventions
            'foreign_key' => env('VAT_VERIFICATION_FOREIGN_KEY', 'vat_verification_id'),
        ],
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
