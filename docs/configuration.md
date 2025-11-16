# Lararoi Configuration

Complete configuration guide for the Lararoi package.

## Environment Variables

### Cache Configuration

The package provides a flexible caching system that can be fully customized or disabled:

```env
# Enable/disable caching (default: true)
# - true: Cache verifications in memory (Laravel Cache) and database
# - false: Most agnostic mode - just return verification data without caching
CACHE_ENABLED=true

# Cache time to live in seconds (default: 86400 = 24 hours)
# When cache expires, the service will re-query the provider and save new data
CACHE_TTL=86400
```

**Cache Behavior:**

- **Cache Enabled (`CACHE_ENABLED=true`)**:
  - First verification returns `cache_status: 'fresh'`
  - Subsequent calls return `cache_status: 'cached'`
  - After TTL expiration, returns `cache_status: 'refreshed'`

- **Cache Disabled (`CACHE_ENABLED=false`)**:
  - Always queries the API directly
  - No database persistence
  - Always returns `cache_status: 'fresh'`
  - **Most agnostic mode** - minimal footprint

### Model Configuration

You can use your own custom model with custom primary keys:

```env
# Custom model class (must implement VatVerificationModelInterface)
# Default: \Aichadigital\Lararoi\Models\VatVerification::class
# VAT_VERIFICATION_MODEL=\App\Models\CustomVatVerification::class

# Primary key column name for vat_verifications table
# Default: id
# VAT_VERIFICATION_PRIMARY_KEY=uuid

# Foreign key name for relationships (e.g., in customers table)
# Default: vat_verification_id
# VAT_VERIFICATION_FOREIGN_KEY=custom_vat_id
```

**Example: Custom Model with UUID**

```php
// config/lararoi.php or .env
'models' => [
    'vat_verification' => [
        'class' => \App\Models\CustomVatVerification::class,
        'primary_key' => 'uuid',
        'foreign_key' => 'custom_vat_uuid',
    ],
],
```

### General Configuration

```env
# API timeout in seconds (default: 15)
API_TIMEOUT=15

# VIES test mode (uses European Commission test service)
VIES_TEST_MODE=false

# Provider order (comma-separated)
# Default: vies_soap,vies_rest,isvat
PROVIDERS_ORDER=vies_soap,vies_rest,isvat

# Logging
LOGGING_ENABLED=true
LOGGING_LEVEL=info
```

### Paid Providers

```env
# vatlayer.com (100 queries/month free, then paid)
VATLAYER_ENABLED=false
VATLAYER_KEY=your_api_key_here

# viesapi.eu (free test plan, then paid)
VIESAPI_ENABLED=false
VIESAPI_KEY=your_api_key_here
VIESAPI_SECRET=your_secret_here  # Second value if provided
VIESAPI_IP=188.34.128.203      # IP for whitelist/configuration

# isvat.eu (free, 100/month limit)
ISVAT_USE_LIVE=false  # true for real-time queries
```


**Generic variables shared between packages:**

```env
# Path to PKCS#12 certificate (.p12 or .pfx)
CERT_P12_PATH=/path/to/certificate.p12

# Certificate password
CERT_P12_PASSWORD=your_password
```

**Important notes:**
- These variables are **generic** and can be used by other packages (digital signature, PDFs, etc.)
- The certificate can be **individual** or **company representative**
- The certificate must be in PKCS#12 format (.p12 or .pfx)

```env
# Default: personal/representative

# For electronic seal (if you have seal certificate)
```

## Provider Order

The package attempts to verify VAT using providers in the specified order. If one fails, it automatically tries the next.

### Recommended Order

**For general use (free first):**

```
vies_rest → vies_soap → isvat → vatlayer → viesapi
```

**High availability (with paid providers):**

```
vies_rest → vies_soap → viesapi → vatlayer → isvat
```

## Configuration by Provider

### VIES (Free)

No configuration required. Automatically available.

- **VIES REST**: Unofficial but functional
- **VIES SOAP**: Official from European Commission

### isvat.eu (Free)

No API key required, but has a limit of 100 queries/month.

```env
# Use real-time queries (without 14-day cache)
ISVAT_USE_LIVE=false
```

### vatlayer.com (Paid)

Requires API key. Free plan: 100 queries/month.

1. Sign up at https://vatlayer.com/
2. Get API key
3. Configure in `.env`:
   ```env
   VATLAYER_ENABLED=true
   VATLAYER_KEY=your_api_key
   ```

### viesapi.eu (Paid)

Requires API key. Free test plan available.

1. Sign up at https://viesapi.eu/
2. Get API key and secret (if provided)
3. Configure in `.env`:
   ```env
   VIESAPI_ENABLED=true
   VIESAPI_KEY=your_api_key
   VIESAPI_SECRET=your_secret  # Optional, if provided
   VIESAPI_IP=188.34.128.203 # Optional, IP for whitelist
   ```


Requires digital certificate. Free but needs configuration.

**Requirements:**

- Digital certificate for individual or company representative
- Certificate in PKCS#12 format (.p12 or .pfx)
- Generic variables configured: `CERT_P12_PATH` and `CERT_P12_PASSWORD`

**Configuration:**

```env
CERT_P12_PATH=/path/to/certificate.p12
CERT_P12_PASSWORD=your_password
```

The provider is automatically registered if the certificate is detected.

## Verify Configuration

You can verify which providers are available using the command:

```bash
php artisan lararoi:dev:list-providers
```

This command shows:

- List of all providers
- Availability status of each one
- Configured fallback order

## Complete `.env` Example

```env
# ============================================
# VAT Verification Configuration
# ============================================

# Cache Configuration
CACHE_ENABLED=true
CACHE_TTL=86400

# API Timeout (in seconds)
API_TIMEOUT=15

# Providers Order (comma-separated)
PROVIDERS_ORDER=vies_soap,vies_rest,isvat

# ============================================
# VIES Configuration
# ============================================
VIES_TEST_MODE=false

# ============================================
# Paid Providers (Optional)
# ============================================

# Vatlayer
VATLAYER_ENABLED=false
VATLAYER_KEY=

# ViesAPI
VIESAPI_ENABLED=false
VIESAPI_KEY=
VIESAPI_SECRET=
VIESAPI_IP=

# IsVAT
ISVAT_USE_LIVE=false

# ============================================
# Model Configuration (Optional)
# ============================================
# VAT_VERIFICATION_MODEL=\App\Models\CustomVatVerification::class
# VAT_VERIFICATION_PRIMARY_KEY=id
# VAT_VERIFICATION_FOREIGN_KEY=vat_verification_id

# ============================================
# Logging Configuration
# ============================================
LOGGING_ENABLED=true
LOGGING_LEVEL=info

# ============================================
# Generic Certificate (shared between packages)
# ============================================
CERT_P12_PATH=./.certificates/certificate.p12
CERT_P12_PASSWORD=
```

## Troubleshooting

### Provider not available

If a provider shows "Not available":

- **Paid providers**: Verify you have the API key configured
- **VIES**: Should always be available (verify internet connection)

### Certificate not working

- Verify the file exists at the specified path
- Verify the password is correct
- Verify the certificate is in .p12 or .pfx format
- Verify the certificate has not expired

### Provider order not respected

- Verify `LARAROI_PROVIDERS_ORDER` is correctly configured
- Names must match exactly: `vies_rest`, `vies_soap`, etc.
- Separate by commas without spaces: `vies_rest,vies_soap` (not `vies_rest, vies_soap`)
