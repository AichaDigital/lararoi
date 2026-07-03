# Lararoi Usage Guide

This guide covers day-to-day usage: verifying, reading the result, swapping the
cache model. For the end-to-end consumer flow including tracking, retention and
output mapping see `integration.md`; for the full config reference see
`configuration.md`; for every contract signature see `contracts.md`.

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
# ============================================
# Cache Configuration
# ============================================
# Enable/disable caching (default: true)
CACHE_ENABLED=true

# Cache TTL in seconds (default: 86400 = 24 hours)
CACHE_TTL=86400

# ============================================
# General Configuration
# ============================================
# API Timeout in seconds (default: 15)
API_TIMEOUT=15

# VIES test mode (uses test service for development)
VIES_TEST_MODE=false

# Provider order (comma-separated)
PROVIDERS_ORDER=vies_soap,vies_rest,isvat

# ============================================
# Paid Providers (Optional)
# ============================================
VATLAYER_ENABLED=false
VATLAYER_KEY=

VIESAPI_ENABLED=false
VIESAPI_KEY=
VIESAPI_SECRET=
VIESAPI_IP=

# IsVAT
ISVAT_USE_LIVE=false

# ============================================
# Model Configuration (Optional)
# ============================================
# Custom model class (must implement VatVerificationModelInterface)
# VAT_VERIFICATION_MODEL=\App\Models\CustomVatVerification::class
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
    'is_valid' => true,                // bool: Is the VAT valid?
    'vat_code' => 'ESB12345678',       // string: Complete VAT code
    'country_code' => 'ES',            // string: Country code
    'company_name' => '...',           // string|null: Company name
    'company_address' => '...',        // string|null: Address
    'api_source' => 'VIES_REST',       // string: Provider used
    'cached' => false,                 // bool: From cache? (backward compatibility)
    'cache_status' => 'fresh',         // string: 'fresh', 'cached', or 'refreshed'
    'request_date' => '2025-01-01...', // string|null: Verification date
    'response_data' => [...]           // array: Complete response data
]
```

**Cache Status Values:**

- **`'fresh'`**: Newly verified from API (first time or cache disabled)
- **`'cached'`**: Returned from valid cache (memory or database)
- **`'refreshed'`**: Cache expired, re-queried and saved new data

## Advanced Usage

### Agnostic Mode (No Cache)

For maximum flexibility and minimal footprint, you can disable caching entirely:

```env
CACHE_ENABLED=false
```

With cache disabled:

```php
$result = $service->verifyVatNumber('B12345678', 'ES');

// Always returns fresh data from API
// cache_status will always be 'fresh'
// No database persistence
// No memory cache
```

**Use cases for agnostic mode:**
- Testing and development
- One-time verifications
- When you have your own caching strategy
- Minimal database footprint

### Custom Model with Relationships

You can use your own model with custom primary keys and relationships:

#### 1. Create your custom model

```php
namespace App\Models;

use Aichadigital\Lararoi\Models\VatVerification as BaseVatVerification;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomVatVerification extends BaseVatVerification
{
    // Custom primary key (e.g., UUID)
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    // Define relationship to Customer
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_uuid', 'uuid');
    }
}
```

#### 2. Configure in `config/lararoi.php`

```php
'models' => [
    'vat_verification' => [
        'class' => \App\Models\CustomVatVerification::class,
    ],
],
```

#### 3. Or via environment

```env
VAT_VERIFICATION_MODEL=\App\Models\CustomVatVerification::class
```

#### 4. Customer Model (One-to-One Relationship)

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    // Customer has one VAT verification (for validation data)
    // The VAT number itself is stored in customers.vat_number field
    public function vatVerification(): HasOne
    {
        return $this->hasOne(
            \App\Models\CustomVatVerification::class,
            'custom_vat_uuid',  // foreign key in roi_vat_verifications table
            'uuid'              // local key in customers table
        );
    }
}
```

**Migration example:**

```php
Schema::table('customers', function (Blueprint $table) {
    $table->string('vat_number')->nullable();      // Stores the VAT number
    $table->string('vat_country_code', 2)->nullable();
    $table->uuid('custom_vat_uuid')->nullable();   // FK to roi_vat_verifications

    $table->foreign('custom_vat_uuid')
        ->references('uuid')
        ->on('roi_vat_verifications')
        ->nullOnDelete();
});
```

**Usage:**

```php
$customer = Customer::find($uuid);

// Verify and store VAT
$result = app(VatVerificationServiceInterface::class)
    ->verifyVatNumber($customer->vat_number, $customer->vat_country_code);

if ($result['is_valid']) {
    $vatVerification = CustomVatVerification::findByVatCodeAndCountry(
        $result['vat_code'],
        $result['country_code']
    );

    // Link to customer
    $customer->custom_vat_uuid = $vatVerification->uuid;
    $customer->save();
}

// Access verification data
$customer->load('vatVerification');
echo $customer->vatVerification->company_name;
echo $customer->vatVerification->company_address;
```

## Error Handling

A **syntactically malformed** VAT number for a known country does **not** throw —
it returns the canonical result with `is_valid = false` and
`api_source = 'LOCAL_VALIDATION'`. Exceptions are reserved for real failures:

- **`VatVerificationException`** — base exception; e.g. empty VAT number/country
  code (`getErrorCode() === 'INVALID_INPUT'`). Carries `getErrorCode()` and
  `getApiSource()`.
- **`ApiUnavailableException`** — extends `VatVerificationException`; thrown when
  every provider in the fallback order fails.

```php
use Aichadigital\Lararoi\Exceptions\VatVerificationException;
use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;

try {
    $result = $service->verifyVatNumber('B12345678', 'ES');
} catch (ApiUnavailableException $e) {
    // Every provider failed (catch the specific subclass first)
    echo "Error: " . $e->getMessage();
    echo "Code: " . $e->getErrorCode();
    echo "Source: " . $e->getApiSource();
} catch (VatVerificationException $e) {
    // Other verification error (e.g. INVALID_INPUT)
    echo "Error: " . $e->getMessage();
}
```

When you opt into tracking (see `integration.md`), the tracking path adds two
more: `TrackingDisabledException` (explicit `record()` while tracking is off) and
`UnknownConsumerException` (unregistered consumer while tracking is on). See
`contracts.md` for their signatures.

## Usage from a consumer

lararoi is designed to be consumed by other packages/apps (larabill is the
intended first consumer, though it does not depend on lararoi yet). A consumer
injects the service through its interface and never re-implements verification:

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

To also attribute and audit the verification (per-consumer tracking + retention),
see the full flow in `integration.md`.

## Provider Configuration

### Provider Order

You can configure the provider order in `.env`:

```env
```

Or directly in `config/lararoi.php`:

```php
'providers_order' => [
    'vies_soap',    // ⭐⭐⭐ VIES SOAP (official EU service)
    'vies_rest',    // ⭐⭐ VIES REST (unofficial but functional)
    'isvat',        // ⭐⭐⭐ isvat.eu (free, 100/month limit)
    'vatlayer',     // ⭐⭐⭐⭐ vatlayer (paid, requires API key)
    'viesapi',      // ⭐⭐⭐⭐⭐ viesapi.eu (paid, requires API key)
],
```


### Available Providers

#### Free (no additional configuration)
- **vies_rest**: VIES REST API (unofficial but functional)
- **vies_soap**: VIES SOAP API (official)
- **isvat**: isvat.eu (free with 100 queries/month limit)

#### Paid (require API key)
- **vatlayer**: vatlayer.com (100 queries/month free, then paid)
- **viesapi**: viesapi.eu (free test plan, then paid)

#### Special (Spain only)
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

The package's test suite runs on Pest. To run it:

```bash
composer test
```

## Important Notes

1. **Cache Flexibility**:
   - Cache can be enabled/disabled via `CACHE_ENABLED`
   - Configurable TTL (default: 24 hours)
   - Three cache states: `fresh`, `cached`, `refreshed`
   - Disable cache for agnostic mode (minimal footprint)

2. **Model Customization**:
   - Use your own model class (must implement `VatVerificationModelInterface`)
   - Your model can use any primary key type (UUID, ULID, etc.)

3. **Fallback System**:
   - If a provider fails, automatically tries the next one
   - Configurable provider order

4. **Database Persistence (cache, not audit)**:
   - When the cache is enabled, the latest verification is saved to
     `roi_vat_verifications` — one **current** row per NIF (`vat_code` +
     `country_code`), overwritten on refresh. This is a cache, not a history.
   - For an append-only **audit history** ("who verified what, when"), use the
     tracking log (`roi_verification_queries`) — inert by default, opt-in per
     consumer. See `integration.md`.
   - The cache row can be linked to your customer/client models.

5. **Logging**:
   - Provider errors and cache activity are written to the Laravel log

6. **Generic Variables**:
   - Certificate and API key variables are shared between packages
   - Single configuration for multiple packages
