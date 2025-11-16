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

# Primary key column name
# VAT_VERIFICATION_PRIMARY_KEY=id

# Foreign key name for relationships
# VAT_VERIFICATION_FOREIGN_KEY=vat_verification_id

# ============================================
# Logging
# ============================================
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
        'primary_key' => 'uuid',
        'foreign_key' => 'custom_vat_uuid',
    ],
],
```

#### 3. Or via environment

```env
VAT_VERIFICATION_MODEL=\App\Models\CustomVatVerification::class
VAT_VERIFICATION_PRIMARY_KEY=uuid
VAT_VERIFICATION_FOREIGN_KEY=custom_vat_uuid
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
            'custom_vat_uuid',  // foreign key in vat_verifications table
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
    $table->uuid('custom_vat_uuid')->nullable();   // FK to vat_verifications

    $table->foreign('custom_vat_uuid')
        ->references('uuid')
        ->on('vat_verifications')
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

The package includes basic tests. To run them:

```bash
composer test
```

Or with PHPUnit directly:

```bash
./vendor/bin/phpunit
```

## Important Notes

1. **Cache Flexibility**:
   - Cache can be enabled/disabled via `CACHE_ENABLED`
   - Configurable TTL (default: 24 hours)
   - Three cache states: `fresh`, `cached`, `refreshed`
   - Disable cache for agnostic mode (minimal footprint)

2. **Model Customization**:
   - Use your own model class (must implement `VatVerificationModelInterface`)
   - Custom primary keys supported (UUID, ULID, etc.)
   - Custom foreign keys for relationships

3. **Fallback System**:
   - If a provider fails, automatically tries the next one
   - Configurable provider order

4. **Database Persistence**:
   - Verifications saved to database (when cache enabled)
   - Historical record for auditing
   - Can be linked to your customer/client models

5. **Logging**:
   - All verifications can be logged (configurable)
   - Levels: debug, info, warning, error

6. **Generic Variables**:
   - Certificate and API key variables are shared between packages
   - Single configuration for multiple packages
