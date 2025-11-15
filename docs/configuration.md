# Lararoi Configuration

Complete configuration guide for the Lararoi package.

## Environment Variables

### General Configuration

```env
# Cache time to live in seconds (default: 86400 = 24 hours)
LARAROI_CACHE_TTL=86400

# VIES test mode (uses European Commission test service)
LARAROI_VIES_TEST_MODE=false

# Provider order (comma-separated)
# Default: vies_rest,vies_soap,isvat,vatlayer,viesapi
LARAROI_PROVIDERS_ORDER=vies_rest,vies_soap,isvat,vatlayer,viesapi

# Logging
LARAROI_LOGGING_ENABLED=true
LARAROI_LOGGING_LEVEL=info
```

### Paid Providers

```env
# vatlayer.com (100 queries/month free, then paid)
VATLAYER_KEY=your_api_key_here

# viesapi.eu (free test plan, then paid)
VIESAPI_KEY=your_api_key_here
VIESAPI_SECRET=your_secret_here  # Second value if provided
VIESAPI_IP=188.34.128.203      # IP for whitelist/configuration

# isvat.eu (free, 100/month limit)
LARAROI_ISVAT_USE_LIVE=false  # true for real-time queries
```

### Certificate for AEAT (Spain Only)

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
- Both types are valid for AEAT Web Service
- The certificate must be in PKCS#12 format (.p12 or .pfx)

**AEAT endpoint (optional):**
```env
# Default: personal/representative
LARAROI_AEAT_ENDPOINT=https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP

# For electronic seal (if you have seal certificate)
# LARAROI_AEAT_ENDPOINT=https://www10.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP
```

## Provider Order

The package attempts to verify VAT using providers in the specified order. If one fails, it automatically tries the next.

### Recommended Order

**For general use (free first):**
```
vies_rest → vies_soap → isvat → vatlayer → viesapi
```

**Spain only (with AEAT certificate):**
```
aeat → vies_rest → vies_soap → isvat
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
LARAROI_ISVAT_USE_LIVE=false
```

### vatlayer.com (Paid)

Requires API key. Free plan: 100 queries/month.

1. Sign up at https://vatlayer.com/
2. Get API key
3. Configure in `.env`:
   ```env
   VATLAYER_KEY=your_api_key
   ```

### viesapi.eu (Paid)

Requires API key. Free test plan available.

1. Sign up at https://viesapi.eu/
2. Get API key and secret (if provided)
3. Configure in `.env`:
   ```env
   VIESAPI_KEY=your_api_key
   VIESAPI_SECRET=your_secret  # Optional, if provided
   VIESAPI_IP=188.34.128.203 # Optional, IP for whitelist
   ```

### AEAT (Spain Only)

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
# Lararoi - General Configuration
LARAROI_CACHE_TTL=86400
LARAROI_VIES_TEST_MODE=false
LARAROI_PROVIDERS_ORDER=vies_rest,vies_soap,isvat,vatlayer,viesapi
LARAROI_LOGGING_ENABLED=true
LARAROI_LOGGING_LEVEL=info

# Paid Providers (generic shared variables)
VATLAYER_KEY=your_vatlayer_api_key
VIESAPI_KEY=your_viesapi_api_key
VIESAPI_SECRET=your_viesapi_secret  # Optional
VIESAPI_IP=188.34.128.203         # Optional
LARAROI_ISVAT_USE_LIVE=false

# Generic Certificate (shared between packages)
CERT_P12_PATH=/path/to/certificate.p12
CERT_P12_PASSWORD=your_password
```

## Troubleshooting

### Provider not available

If a provider shows "Not available":
- **Paid providers**: Verify you have the API key configured
- **AEAT**: Verify the certificate exists and the path is correct
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
