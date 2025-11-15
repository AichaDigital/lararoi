# Lararoi Usage Guide

## Installation

```bash
composer require aichadigital/lararoi
```

## Configuration

### Publish configuration

```bash
php artisan vendor:publish --provider="Aichadigital\Lararoi\LararoiServiceProvider" --tag="lararoi-config"
```

### Publish migrations

```bash
php artisan vendor:publish --provider="Aichadigital\Lararoi\LararoiServiceProvider" --tag="lararoi-migrations"
php artisan migrate
```

### Environment variables

Add to `.env` file:

```env
# Cache TTL in seconds (default: 86400 = 24 hours)
CACHE_TTL=86400

# API Timeout in seconds (default: 15)
# If a provider doesn't respond within this time, it will try the next one
API_TIMEOUT=15

# VIES test mode (uses test service for development)
VIES_TEST_MODE=false

# Provider order (comma-separated, default: aeat,vies_soap,vies_rest,isvat)
# Recommended: AEAT first (most reliable), then official VIES, then free alternatives
PROVIDERS_ORDER=aeat,vies_soap,vies_rest,isvat

# Paid providers (optional - generic shared variables)
VATLAYER_KEY=your_api_key_here
VIESAPI_KEY=your_api_key_here
VIESAPI_SECRET=your_secret_here  # Optional, second value if provided
VIESAPI_IP=188.34.128.203        # Optional, IP for whitelist

# Certificate for AEAT (Spain only)
# Generic variables shared between packages
CERT_P12_PATH=/path/to/certificate.p12
CERT_P12_PASSWORD=your_password
AEAT_ENDPOINT=https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP

# Logging
LOGGING_ENABLED=true
LOGGING_LEVEL=info  # Options: debug, info, warning, error
```

#### Generic Shared Variables

The package uses **generic shared variables** between multiple packages:

**Certificates:**
- `CERT_P12_PATH` - Path to .p12 certificate
- `CERT_P12_PASSWORD` - Certificate password

**API Keys:**
- `VATLAYER_KEY` - API key for vatlayer.com
- `VIESAPI_KEY` - API key for viesapi.eu
- `VIESAPI_SECRET` - Secret for viesapi.eu (optional, second value)
- `VIESAPI_IP` - IP for viesapi.eu whitelist (optional)

This allows:
- ✅ Using the same credentials in multiple packages
- ✅ Single configuration in `.env` for all packages
- ✅ Reusing certificates and API keys

**Certificate format:**
- Must be a PKCS#12 file (`.p12` or `.pfx`)
- Can be individual or company representative certificate
- Both are valid for AEAT Web Service

## Basic Usage

### Verify a VAT number

```php
use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;

$service = app(VatVerificationServiceInterface::class);

$result = $service->verifyVatNumber('B12345678', 'ES');

if ($result['is_valid']) {
    echo "Valid VAT: " . $result['company_name'];
    echo "Address: " . $result['company_address'];
    echo "Source: " . $result['api_source'];
    echo "From cache: " . ($result['cached'] ? 'Yes' : 'No');
} else {
    echo "Invalid VAT";
}
```

### Response structure

```php
[
    'is_valid' => true,              // bool: Is the VAT valid?
    'vat_code' => 'ESB12345678',     // string: Complete VAT code
    'country_code' => 'ES',          // string: Country code
    'company_name' => '...',         // string|null: Company name
    'company_address' => '...',      // string|null: Address
    'api_source' => 'VIES_REST',     // string: Provider used
    'cached' => false,               // bool: From cache?
    'request_date' => '2025-01-01...', // string|null: Verification date
    'response_data' => [...]         // array: Complete response data
]
```

## Error Handling

```php
use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;
use Aichadigital\Lararoi\Exceptions\VatVerificationException;
use Aichadigital\Lararoi\Exceptions\InvalidVatFormatException;
use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;

try {
    $result = $service->verifyVatNumber('B12345678', 'ES');
} catch (InvalidVatFormatException $e) {
    // Invalid format
    echo "Error: " . $e->getMessage();
} catch (ApiUnavailableException $e) {
    // API unavailable
    echo "Error: " . $e->getMessage();
    echo "Code: " . $e->getErrorCode();
    echo "Source: " . $e->getApiSource();
} catch (VatVerificationException $e) {
    // Other error
    echo "Error: " . $e->getMessage();
}
```

## Usage from Larabill

The package is designed to be used primarily from Larabill. Larabill can inject the service using the interface:

```php
use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;

class RoiVerificationService
{
    public function __construct(
        private VatVerificationServiceInterface $vatService
    ) {}
    
    public function verifyRoi(string $vatNumber, string $countryCode)
    {
        return $this->vatService->verifyVatNumber($vatNumber, $countryCode);
    }
}
```

## Provider Configuration

### Provider Order

You can configure the provider order in `.env`:

```env
PROVIDERS_ORDER=aeat,vies_soap,vies_rest,isvat
```

Or directly in `config/lararoi.php`:

```php
'providers_order' => [
    'aeat',         // ⭐⭐⭐⭐⭐ AEAT (Spain only, most reliable, requires certificate)
    'vies_soap',    // ⭐⭐⭐ VIES SOAP (official EU service)
    'vies_rest',    // ⭐⭐ VIES REST (unofficial but functional)
    'isvat',        // ⭐⭐⭐ isvat.eu (free, 100/month limit)
    'vatlayer',     // ⭐⭐⭐⭐ vatlayer (paid, requires API key)
    'viesapi',      // ⭐⭐⭐⭐⭐ viesapi.eu (paid, requires API key)
],
```

**Recommended order**: AEAT first for Spain (most reliable), then official VIES SOAP, then free alternatives.

### Available Providers

#### Free (no additional configuration)
- **vies_rest**: VIES REST API (unofficial but functional)
- **vies_soap**: VIES SOAP API (official)
- **isvat**: isvat.eu (free with 100 queries/month limit)

#### Paid (require API key)
- **vatlayer**: vatlayer.com (100 queries/month free, then paid)
- **viesapi**: viesapi.eu (free test plan, then paid)

#### Special (Spain only)
- **aeat**: AEAT Web Service (official, free, requires digital certificate)
  - Uses generic variables: `CERT_P12_PATH` and `CERT_P12_PASSWORD`
  - Works with individual or company representative certificate

## Querying the Model

If you need to query the model directly:

```php
use Aichadigital\Lararoi\Contracts\VatVerificationModelInterface;

$model = app(VatVerificationModelInterface::class);

// Find by VAT code and country
$verification = $model::findByVatCodeAndCountry('ESB12345678', 'ES');

if ($verification && !$verification->isExpired()) {
    echo "Valid VAT: " . $verification->company_name;
}

// Available scopes
$validVerifications = $model::valid()->get();
$expiredVerifications = $model::expired()->get();
$spanishVerifications = $model::byCountry('ES')->get();
```

## Testing

The package includes basic tests. To run them:

```bash
composer test
```

Or with PHPUnit directly:

```bash
./vendor/bin/phpunit
```

## Important Notes

1. **Cache**: Verifications are automatically cached for 24 hours (configurable)
2. **Fallback**: If a provider fails, it automatically tries the next one in the configured order
3. **Persistence**: Verifications are saved to database to avoid repeated calls
4. **Logging**: All verifications are logged (if enabled)
5. **Certificates**: Certificate variables are generic and shared between packages
6. **AEAT**: Only works for Spanish NIFs and requires configured digital certificate
