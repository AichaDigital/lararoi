# Lararoi

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aichadigital/lararoi.svg?style=flat-square)](https://packagist.org/packages/aichadigital/lararoi)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/aichadigital/lararoi/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/aichadigital/lararoi/actions/workflows/run-tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/aichadigital/lararoi/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/aichadigital/lararoi/actions/workflows/phpstan.yml)
[![Code Style](https://img.shields.io/github/actions/workflow/status/aichadigital/lararoi/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/aichadigital/lararoi/actions/workflows/fix-php-code-style-issues.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/aichadigital/lararoi.svg?style=flat-square)](https://packagist.org/packages/aichadigital/lararoi)

Lararoi is the Laravel package that **owns the intra-community VAT/NIF verification domain**: the verification itself, its cache, and a multi-consumer tracking/audit log — served to consuming apps through explicit, stable contracts and agnostic configuration. Consumers (billing, accounting, …) consume it; they never reimplement the domain.

The verification model is provider-agnostic (VIES SOAP/REST, isvat, viesapi, vatlayer) with automatic fallback and per-country syntax validation. See the [architecture decisions](docs/adr/) (ADR-001, ADR-002) for the domain-ownership rationale.

## Features

- ✅ VAT/NIF verification via VIES (European Commission) + multiple free/paid providers, with automatic fallback
- ✅ Per-country syntax validation (short-circuits obviously invalid numbers without hitting a provider)
- ✅ TTL cache (memory + database), one current row per NIF, enforced by a UNIQUE key
- ✅ **Multi-consumer tracking / audit log** — append-only, consumer-attributed, inert by default
- ✅ **Per-consumer retention** (declared in config, UTC) with a prune command
- ✅ **Per-consumer output mapping** — adapt the canonical result to your own shape without forking code
- ✅ **Formal, documented contract set** — swap the model, the tracking model, or the mapper via config/contracts
- ✅ Robust, typed error handling; development commands for testing against real APIs

## Requirements

- PHP 8.3+
- Laravel 12+

## Installation

```bash
composer require aichadigital/lararoi
```

The database schema is **package-managed** — just run migrations, no stub to publish:

```bash
php artisan migrate
```

This creates the cache table `roi_vat_verifications` and the tracking table `roi_verification_queries`. Optionally publish the config to tune providers, cache, tracking and retention:

```bash
php artisan vendor:publish --tag="lararoi-config"
```

## Basic Usage

```php
use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;

$result = app(VatVerificationServiceInterface::class)
    ->verifyVatNumber('B12345678', 'ES');

if ($result['is_valid']) {
    echo "Valid VAT: {$result['company_name']}";
}
```

Tracking is **off by default**. A consumer that wants an audit trail enables `lararoi.tracking.enabled`, registers itself in the `lararoi.consumers` allow-list (with a retention policy), and passes a `VerificationContext` — see the [Integration Guide](docs/integration.md).

## Documentation

- **[Integration Guide](docs/integration.md)** — install → verify → track → retain → map (the full consumer flow)
- **[Contracts](docs/contracts.md)** — the public contract set (interfaces, value object, exceptions)
- **[Configuration](docs/configuration.md)** — config keys and environment variables
- **[Usage Guide](docs/usage.md)** — usage patterns and examples
- **[Architecture Decisions](docs/adr/)** — ADR-001 (domain ownership), ADR-002 (v1.0 design)
- **[Development Guide](docs/development.md)** — development commands and testing with real APIs
- **[Contributing](docs/contributing.md)** — guidelines for contributors

## Testing & Quality

- Pest PHP test suite, PHPStan level 5, Laravel Pint, GitHub Actions CI.

```bash
composer test        # run the suite
composer quality     # pint + phpstan + tests
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Abdelkarim Mateos](https://github.com/abkrim)
- [Aicha Digital](https://github.com/aichadigital)
- [All Contributors](../../contributors)
