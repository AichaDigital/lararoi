# Integrating lararoi

lararoi verifies intra-community VAT/NIF numbers against multiple providers (VIES SOAP/REST, isvat, viesapi, vatlayer) with automatic fallback, per-country syntax validation, and a TTL cache. This guide covers the surface that exists today. The v1.0 additions (a multi-consumer tracking/audit log, per-consumer retention, and output mapping) are designed in `docs/adr/ADR-002-v1-domain-owner-design.md` and land before the `v1.0.0` tag — see "Roadmap" at the end. Do **not** build your own verification or tracking layer; consume lararoi's.

## Install

```bash
composer require aichadigital/lararoi
```

The schema is package-managed (no stubs to publish). Run migrations to create the cache table:

```bash
php artisan migrate
```

Publish the config if you want to tune providers/cache:

```bash
php artisan vendor:publish --tag=lararoi-config
```

## Verify a VAT number

Resolve the service from the container and call `verifyVatNumber($vatNumber, $countryCode)` — the VAT number is passed **without** the country prefix:

```php
use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;

$result = app(VatVerificationServiceInterface::class)
    ->verifyVatNumber('B12345678', 'ES');
```

### Canonical result

`verifyVatNumber()` returns an array. These fields are the stable contract:

| Key | Type | Meaning |
|---|---|---|
| `is_valid` | `bool` | Whether the VAT number is valid |
| `vat_code` | `string` | Full code with country prefix (e.g. `ESB12345678`) |
| `country_code` | `string` | Two-letter country code |
| `company_name` | `string\|null` | Registered name, when the provider returns it |
| `company_address` | `string\|null` | Registered address, when available |
| `api_source` | `string` | Which provider answered (or `LOCAL_VALIDATION` for a syntax rejection) |
| `request_date` | `string\|null` | Provider timestamp, when available |
| `cached` | `bool` | `true` when served from cache |
| `cache_status` | `string` | `fresh`, `cached`, or `refreshed` |
| `response_data` | `array` | The underlying data snapshot |

Obviously malformed numbers for known countries are rejected locally (no provider call) with `api_source = LOCAL_VALIDATION` and `is_valid = false`.

## Configuration

`config/lararoi.php`:

- **`providers_order`** — order providers are tried (env `PROVIDERS_ORDER`, comma-separated). Defaults to VIES first.
- **`cache.enabled`** — enable the TTL cache (default `true`). Set `false` for the most agnostic, always-live mode.
- **`cache.ttl`** — cache lifetime in seconds (default `86400`). This is the single source of truth for cache expiry.
- **`timeout`** — per-provider timeout in seconds (default `15`); a slow provider is skipped for the next in the fallback order.
- **`vies.test_mode`** — use the VIES test endpoint.
- **`provider_config`** — per-provider keys: `vatlayer` (`api_key`, `enabled`), `viesapi` (`api_key`, `api_secret`, `ip`, `enabled`), `isvat` (`use_live`). A paid provider registers only when `enabled` **and** an API key is present.
- **`models.vat_verification.class`** — swap the cache model (see below).

## Swapping the cache model

Supply your own Eloquent model to persist verifications your way. It must implement `VatVerificationModelInterface`:

```php
use Aichadigital\Lararoi\Contracts\VatVerificationModelInterface;

class MyVatVerification extends Model implements VatVerificationModelInterface
{
    // findByVatCodeAndCountry(), isExpired(), getVatCode(), getCountryCode(),
    // isValid(), getCompanyName(), getCompanyAddress(), getApiSource(),
    // getVerifiedAt(), getResponseData()
}
```

```php
// config/lararoi.php
'models' => [
    'vat_verification' => ['class' => \App\Models\MyVatVerification::class],
],
```

## Roadmap (v1.0, ADR-002)

The following are **designed but not yet shipped**; do not assume they exist against the current tag:

- **Multi-consumer tracking/audit log** — an append-only record of who verified what and when, attributed to the consumer, separate from the cache.
- **Per-consumer retention** — each consumer declares its retention policy; lararoi stores and prunes.
- **Output mapping** — declare how the canonical result maps to your own shape, without forking code.

When these land, this guide expands (ADR-002 PR 5). Until then, the surface above is the whole public contract.
