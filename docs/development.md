# Development Commands - Lararoi

Exclusive commands for developers that allow testing real VAT verification APIs.

**⚠️ Note:** These commands are in `.gitignore` and are not uploaded to the repository. They are only available in `local` environment for development.

## Running Commands in the Package

Since this is a package without a complete Laravel installation, you need to use the development script to run commands:

```bash
# List available providers
php dev/run-command.php lararoi:dev:list-providers

# Test a specific provider
php dev/run-command.php lararoi:dev:test-provider B12345678 ES vies_rest

# Test from file
php dev/run-command.php lararoi:dev:test-from-file
```

## Available Commands

### 1. `lararoi:dev:list-providers`

Lists all available providers and their status.

```bash
php dev/run-command.php lararoi:dev:list-providers
```

**Output:**
- List of free and paid providers
- Availability status of each one
- Configured fallback order

---

### 2. `lararoi:dev:test-provider`

Tests a specific provider with a real API.

```bash
# Test a specific provider
php dev/run-command.php lararoi:dev:test-provider B12345678 ES vies_rest

# With JSON output
php dev/run-command.php lararoi:dev:test-provider B12345678 ES vies_soap --json

# Test ALL available providers
php dev/run-command.php lararoi:dev:test-provider B12345678 ES --all
```

**Parameters:**
- `vat`: VAT number without country prefix
- `country`: Country code (2 letters)

**Options:**
- `--json`: Show response in JSON format
- `--all`: Test all available providers

**Example:**
```bash
php dev/run-command.php lararoi:dev:test-provider 28308119 MT vies_rest
```

---

### 3. `lararoi:dev:test-from-file`

Tests multiple VAT numbers from a file.

```bash
# Use default file (tests/stubs/vat_numbers.txt)
php dev/run-command.php lararoi:dev:test-from-file

# Specify custom file
php dev/run-command.php lararoi:dev:test-from-file --file=path/file.txt

# With JSON output
php dev/run-command.php lararoi:dev:test-from-file --json
```

**File format:**
```
MT28308119 Company ROI
DE320889633 Individual ROI

# Comments with #
BE0665711592  # Not verified if error.
```

**Features:**
- Uses the complete service (with cache and fallback)
- Shows statistics at the end
- Supports comments (lines starting with `#`)

---

## Provider Reliability Level

According to project documentation:

| Provider | Reliability | Type | Documentation |
|----------|-------------|------|---------------|
| **VIES SOAP** | ⭐⭐⭐ | Official | ✅ Officially documented |
| **VIES REST** | ⭐⭐ | Unofficial | ⚠️ Not documented, may change |
| **viesapi.eu** | ⭐⭐⭐⭐⭐ | Third-party | ✅ Well documented, with support |
| **vatlayer** | ⭐⭐⭐⭐ | Third-party | ✅ Well documented |
| **isvat.eu** | ⭐⭐⭐ | Third-party | ⚠️ Free, no support |

### Testing Recommendations

1. **For basic tests:** Use VIES REST or SOAP (free)
2. **For production tests:** Use viesapi.eu or vatlayer (more reliable)

---

## Usage Examples

### Test a specific VAT with all providers

```bash
php dev/run-command.php lararoi:dev:test-provider 28308119 MT --all
```

### Verify VAT numbers from file

```bash
php dev/run-command.php lararoi:dev:test-from-file
```

### Test only free providers

```bash
# First list providers
php dev/run-command.php lararoi:dev:list-providers

# Then test a free one
php dev/run-command.php lararoi:dev:test-provider 320889633 DE vies_rest
php dev/run-command.php lararoi:dev:test-provider 320889633 DE isvat
```

---

## Important Notes

1. **Development only:** These commands only work in `local` environments
2. **Real APIs:** Commands query real APIs, not mocks
3. **Rate limiting:** Be careful with free API limits
4. **Cache:** The `test-from-file` command uses the complete service, so it may use cache

---

## Troubleshooting

### Provider not available

If a provider shows "Not available":
- **Paid providers:** Verify you have the API key configured in `.env`
- **VIES:** Should always be available (verify your internet connection)

### Timeout errors

Some providers can be slow:
- VIES can have high latency during peak hours
- Use `--verbose` to see more error details

### View complete responses

Use `--json` to see the complete API response:

```bash
php dev/run-command.php lararoi:dev:test-provider 28308119 MT vies_rest --json
```
