# Lararoi - Package Context for AI Agents

> **Read this file first** to understand the package's purpose, architecture, and conventions.

## 🎯 Package Identity

**Lararoi** is a Laravel package for **EU VAT/NIF verification** (ROI = Registro de Operadores Intracomunitarios). It verifies tax identification numbers via VIES (European Commission) and alternative providers.

### Critical Information

| Item | Value |
|------|-------|
| **Version** | dev-main (targeting v1.0 for Dec 15, 2025) |
| **PHP** | ^8.3 |
| **Laravel** | ^11.0 \| ^12.0 |
| **License** | AGPL-3.0-or-later |
| **Status** | Beta (v0.2.x) |

### Ecosystem Context

Lararoi is part of the **AichaDigital billing ecosystem**:

```
aichadigital/
├── larabill/        # Core billing (uses lararoi for VAT verification)
├── lara100/         # Base-100 monetary calculations
├── lararoi/         # EU VAT/ROI verification (THIS PACKAGE)
├── lara-verifactu/  # Spain AEAT VeriFACTU
└── laratickets/     # Support tickets
```

**Primary staging environment**: [Larafactu](https://github.com/AichaDigital/larafactu)

## 🏗️ Architecture

### Core Purpose

1. **VAT Number Verification**: Validate EU VAT numbers via VIES
2. **Provider Fallback**: Multiple API providers with automatic fallback
3. **Caching**: Memory + database caching for performance
4. **Audit Trail**: Log all verification attempts

### Key Services

```php
// Main verification service
use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;

$service = app(VatVerificationServiceInterface::class);
$result = $service->verifyVatNumber('B12345678', 'ES');

if ($result['is_valid']) {
    echo "Company: " . $result['company_name'];
}
```

### Providers

The package supports multiple verification providers:

1. **VIES SOAP** (Official EU Commission) - Free, primary
2. **AbstractAPI** - Paid, backup
3. **APILayer** - Paid, backup

Automatic fallback when primary fails.

## 📁 Package Structure

```
lararoi/
├── config/lararoi.php          # Package configuration
├── database/migrations/        # ROI queries table
├── docs/
│   ├── AGENT_CONTEXT.md        # This file
│   ├── configuration.md        # Config guide
│   ├── development.md          # Dev guide
│   ├── integration.md          # Integration guide
│   ├── project.md              # API documentation
│   └── usage.md                # Usage examples
├── src/
│   ├── Contracts/              # Interfaces
│   ├── DTOs/                   # Data Transfer Objects
│   ├── Enums/                  # Status enums
│   ├── Events/                 # Domain events
│   ├── Exceptions/             # Custom exceptions
│   ├── Models/                 # RoiQuery model
│   ├── Providers/              # API providers
│   │   ├── ViesProvider.php    # VIES SOAP client
│   │   ├── AbstractApiProvider.php
│   │   └── ApiLayerProvider.php
│   └── Services/               # Business logic
└── tests/                      # Pest tests
```

## ⚙️ Configuration

### Environment Variables

```env
# Primary provider
LARAROI_PRIMARY_PROVIDER=vies

# AbstractAPI (backup)
LARAROI_ABSTRACTAPI_KEY=your_key

# APILayer (backup)
LARAROI_APILAYER_KEY=your_key

# Caching
LARAROI_CACHE_TTL=86400  # 24 hours
LARAROI_CACHE_DRIVER=database  # or memory
```

### Config File

```php
// config/lararoi.php
return [
    'providers' => [
        'primary' => 'vies',
        'fallback' => ['abstractapi', 'apilayer'],
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 86400,
        'driver' => 'database',
    ],
];
```

## 🔧 Key Models

### RoiQuery

Stores verification attempts and results:

```php
use Aichadigital\Lararoi\Models\RoiQuery;

// Recent queries
$queries = RoiQuery::where('country_code', 'ES')
    ->where('is_valid', true)
    ->latest()
    ->get();
```

## 🧪 Testing

```bash
# Run all tests
composer test

# Run specific tests
composer test -- --filter=ViesProvider

# Test with real APIs (requires keys)
php artisan lararoi:test-providers
```

## ⚠️ Important Conventions

### VAT Number Format

Always pass VAT numbers **without country prefix**:

```php
// ✅ Correct
$service->verifyVatNumber('B12345678', 'ES');

// ❌ Wrong
$service->verifyVatNumber('ESB12345678', 'ES');
```

### Caching Strategy

- Valid results: cached for 24 hours (configurable)
- Invalid results: cached for 1 hour
- Errors: not cached (retry allowed)

### Error Handling

```php
try {
    $result = $service->verifyVatNumber($vat, $country);
} catch (VatVerificationException $e) {
    // Provider error - may retry
    Log::warning('VAT verification failed', ['error' => $e->getMessage()]);
}
```

## 🚫 Anti-Patterns

**DON'T**:
- ❌ Include country prefix in VAT number
- ❌ Skip caching in production
- ❌ Ignore provider errors
- ❌ Call VIES too frequently (rate limits)

**DO**:
- ✅ Use the service interface (not providers directly)
- ✅ Handle verification failures gracefully
- ✅ Cache results appropriately
- ✅ Log verification attempts for audit

## 📚 Key Documentation

| File | Purpose |
|------|---------|
| `docs/project.md` | API documentation (VIES, providers) |
| `docs/configuration.md` | Configuration guide |
| `docs/usage.md` | Usage examples |
| `docs/integration.md` | Integration with other packages |
| `CHANGELOG.md` | Version history |

## 🎯 Integration with Larabill

When used with Larabill, verification happens automatically:

```php
// In Larabill's UserTaxProfile
$profile = UserTaxProfile::create([
    'tax_code' => 'B12345678',
    'country' => 'ES',
]);

// Lararoi verifies automatically via event listener
```

---

**Remember**: This package handles EU compliance verification. Cache results appropriately and handle provider failures gracefully. Target: v1.0 stable by December 15, 2025.

