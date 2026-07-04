<?php

use Aichadigital\Lararoi\Models\VatVerification;
use Aichadigital\Lararoi\Models\VerificationQuery;
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

        // Swappable tracking model (ADR-002 D3). Must implement
        // VerificationQueryModelInterface. A consumer that needs a different
        // store swaps the model here instead of renaming lararoi's tables.
        'verification_query' => [
            'class' => env('VERIFICATION_QUERY_MODEL', VerificationQuery::class),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking / Audit Log
    |--------------------------------------------------------------------------
    |
    | Multi-consumer append-only tracking of "who verified what, when"
    | (ADR-002 D3/D4). Inert by default: a row is written only when tracking
    | is enabled AND a VerificationContext is supplied (or via an explicit
    | VerificationTrackerInterface::record() call). This is the single
    | kill-switch — with it off, nothing is recorded.
    |
    */
    'tracking' => [
        'enabled' => env('LARAROI_TRACKING_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Consumers (retention allow-list)
    |--------------------------------------------------------------------------
    |
    | Explicit allow-list keyed by consumer id (ADR-002 D5). When tracking is
    | enabled, recording a verification for a consumer that is NOT listed here
    | throws UnknownConsumerException — closing the typo hole where a mistyped
    | key would silently accumulate unpolicied, never-pruned history.
    |
    | Each consumer declares `retention_days`: lararoi stamps
    | retention_until = queried_at + retention_days (UTC). A null value is a
    | conscious "keep forever" choice (never auto-pruned), not the accident of
    | an unregistered key. Legal retention is the consumer's fiscal obligation;
    | lararoi is the storage + enforcement.
    |
    | A consumer entry may also carry an optional `mapper` (ADR-002 D6): a class
    | implementing VerificationResultMapperInterface that transforms the canonical
    | result into the consumer's own shape. It is a separate, consumer-invoked
    | transform resolved via VerificationResultMapperRegistry::mapperFor() — it is
    | NEVER applied inside verifyVatNumber(), which always returns the canonical
    | array. Absent → the identity mapper (canonical shape unchanged).
    |
    | Example:
    | 'consumers' => [
    |     'larabill' => ['retention_days' => 2555, 'mapper' => \App\Lararoi\LarabillVatMapper::class], // explicit policy (7y) + own shape
    |     'openmiza' => ['retention_days' => null],  // conscious "keep forever", canonical shape
    | ],
    |
    */
    'consumers' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Upgrade Preflight (AID-324 / AID-325)
    |--------------------------------------------------------------------------
    |
    | The preflight migration only drops a pre-existing `vat_verifications`
    | table when it is DOUBLY proven to be larabill 3.x's disposable VIES
    | cache: the physical index fingerprint AND the larabill migration-ledger
    | row. When either proof is missing the migration aborts loudly.
    |
    | assume_legacy_vat_table is the operator escape hatch for that abort: an
    | explicit human decision, taken AFTER verifying the table is the legacy
    | cache and exporting it (e.g. `mysqldump <db> vat_verifications`). Never
    | leave it enabled permanently — set it for the one deploy that needs it.
    |
    */
    'upgrade' => [
        'assume_legacy_vat_table' => env('LARAROI_ASSUME_LEGACY_VAT_TABLE', false),
    ],

];
