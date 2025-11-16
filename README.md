# Lararoi

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aichadigital/lararoi.svg?style=flat-square)](https://packagist.org/packages/aichadigital/lararoi)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/aichadigital/lararoi/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/aichadigital/lararoi/actions/workflows/run-tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/aichadigital/lararoi/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/aichadigital/lararoi/actions/workflows/phpstan.yml)
[![PHP Insights](https://img.shields.io/badge/PHP%20Insights-84.5%25-brightgreen?style=flat-square)](https://github.com/nunomaduro/phpinsights)
[![Code Style](https://img.shields.io/github/actions/workflow/status/aichadigital/lararoi/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/aichadigital/lararoi/actions/workflows/fix-php-code-style-issues.yml)
[![Code Coverage](https://img.shields.io/codecov/c/github/aichadigital/lararoi?style=flat-square)](https://codecov.io/gh/aichadigital/lararoi)
[![Total Downloads](https://img.shields.io/packagist/dt/aichadigital/lararoi.svg?style=flat-square)](https://packagist.org/packages/aichadigital/lararoi)

> **⚠️ BETA VERSION - Active Development**
> This package is currently in beta (v0.2.x). The API and configuration may change significantly in future versions. Use in production at your own risk. We recommend pinning to a specific version in your `composer.json`.

Agnostic package for intra-community NIF-VAT/VAT verification in Laravel.

## Description

Lararoi is an independent package that provides services to verify tax identification numbers (NIF-VAT) of intra-community operators in the European Union. It is designed to be agnostic and reusable, although its main use is as a dependency of the [Larabill](https://github.com/aichadigital/larabill) package.

## Features

- ✅ Verification via VIES (European Commission)
- ✅ Support for multiple API providers (free and paid)
- ✅ Integrated caching system (memory + database)
- ✅ Automatic fallback between providers
- ✅ Robust error handling
- ✅ Logging and auditing
- ✅ Development commands for testing with real APIs

## Installation

```bash
composer require aichadigital/lararoi
```

## Configuration

Publish the configuration:

```bash
php artisan vendor:publish --provider="Aichadigital\Lararoi\LararoiServiceProvider" --tag="config"
```

Publish the migrations:

```bash
php artisan vendor:publish --provider="Aichadigital\Lararoi\LararoiServiceProvider" --tag="migrations"
php artisan migrate
```

## Basic Usage

```php
use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;

$service = app(VatVerificationServiceInterface::class);

$result = $service->verifyVatNumber('B12345678', 'ES');

if ($result['is_valid']) {
    echo "Valid VAT: " . $result['company_name'];
}
```

## Documentation

Complete documentation is available in the [`docs/`](docs/) directory:

- **[Usage Guide](docs/usage.md)** - Basic usage, configuration, and examples
- **[Configuration](docs/configuration.md)** - Detailed configuration options and environment variables
- **[Integration Guide](docs/integration.md)** - How to integrate with other packages (e.g., Larabill)
- **[Project Information](docs/project.md)** - Complete documentation of available APIs and services
- **[Development Guide](docs/development.md)** - Development commands and testing with real APIs
- **[Contributing](docs/contributing.md)** - Guidelines for developers and contributors
- **[License](LICENSE.md)** - MIT License

## Testing & Quality

The package maintains high quality standards with:

- ✅ **Automated tests** with Pest PHP
- ✅ **Static analysis** with PHPStan (level 5)
- ✅ **Code style** with Laravel Pint
- ✅ **Code coverage** minimum of 20%
- ✅ **CI/CD** with GitHub Actions

To run tests locally:

```bash
# Run tests
composer test

# Run tests with coverage
composer test:coverage

# Static analysis with PHPStan
composer analyse

# Format code
composer format
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Abdelkarim Mateos](https://github.com/abkrim)
- [Aicha Digital](https://github.com/aichadigital)
- [All Contributors](../../contributors)
