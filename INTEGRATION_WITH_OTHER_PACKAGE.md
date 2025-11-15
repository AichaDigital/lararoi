## Analysis: what to remove, what to keep and how to couple

Main package that will use this package: `aichadigital/larabill` located at `/Users/abkrim/development/packages/aichadigital/larabill`

### 1. Code to remove/move to ROI/VAT package

#### Services (move completely)
- `src/Services/VatVerificationService.php` → Move to ROI package
- `src/Services/VatApiIntegrationService.php` → Move to ROI package

#### Model (decisions)
- `src/Models/VatVerification.php` → Options:
  - Option A: Move to ROI package (recommended)
  - Option B: Keep in Larabill as "proxy" that uses ROI package

#### Migration
- `database/migrations/2024_12_01_000007_create_vat_verifications_table.php` → Move to ROI package

#### Configuration (partial)
- `config/larabill.php` → `vat_apis` section → Move to ROI package

#### Related tests
- `tests/Unit/Services/VatVerificationService*.php` → Move to ROI package
- `tests/Unit/Services/VatApiIntegrationServiceTest.php` → Move to ROI package
- `tests/Integration/VatVerificationIntegrationTest.php` → Move to ROI package
- `tests/Unit/Models/VatVerificationTest.php` → Move to ROI package

---

### 2. Code to keep in Larabill

#### Services (specific business logic)
- `src/Services/RoiVerificationService.php` → Keep (uses VAT but is Larabill ROI logic)
- `src/Services/BillingService.php` → Keep (uses ROI, not VAT directly)
- `src/Services/CacheService.php` → Keep (ROI specific methods)

#### Relationships (adapt)
- `src/Models/UserTaxProfile::vatVerification()` → Adapt to use ROI package
- `src/Models/Invoice::$vat_verification` (JSON field) → Keep (storage only)

#### Configuration (keep)
- `config/larabill.php` → `models.vat_verification` section → Change to ROI package binding
- `config/larabill.php` → Rest of configuration

---

### 3. Coupling architecture

#### Interface that ROI package must implement

```php
// In ROI package (public contract)
interface VatVerificationServiceInterface
{
    /**
     * Verify a VAT number
     * 
     * @return array{
     *     is_valid: bool,
     *     vat_code: string,
     *     country_code: string,
     *     company_name: string|null,
     *     company_address: string|null,
     *     api_source: string,
     *     cached: bool,
     *     response_data?: array
     * }
     */
    public function verifyVatNumber(string $vatNumber, string $countryCode): array;
}
```

#### Model that ROI package must expose

```php
// In ROI package
interface VatVerificationModelInterface
{
    public static function findByVatCodeAndCountry(string $vatCode, string $countryCode): ?self;
    public function isExpired(): bool;
    // ... other necessary methods
}
```

#### ROI package Service Provider

```php
// In ROI package
class RoiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Service binding
        $this->app->singleton(
            VatVerificationServiceInterface::class,
            VatVerificationService::class
        );
        
        // Model binding (optional, if Larabill needs it)
        $this->app->bind(
            VatVerificationModelInterface::class,
            VatVerification::class
        );
    }
}
```

#### Adaptation in Larabill

```php
// src/Services/RoiVerificationService.php (adapted)
class RoiVerificationService
{
    private VatVerificationServiceInterface $vatVerificationService; // ← ROI package interface
    
    public function __construct(
        ?CacheService $cacheService = null,
        ?VatVerificationServiceInterface $vatVerificationService = null // ← Interface injection
    ) {
        $this->cacheService = $cacheService ?? app(CacheService::class);
        $this->vatVerificationService = $vatVerificationService 
            ?? app(VatVerificationServiceInterface::class); // ← Resolve from container
    }
    
    // ... rest of code same, only dependency changes
}
```

#### Relationship in UserTaxProfile (adapted)

```php
// src/Models/UserTaxProfile.php
public function vatVerification(): HasOne
{
    // Option 1: If model is in ROI package
    $vatVerificationModel = app(VatVerificationModelInterface::class);
    return $this->hasOne(get_class($vatVerificationModel), 'vat_code', 'tax_code');
    
    // Option 2: If you keep a proxy in Larabill
    return $this->hasOne(VatVerification::class, 'vat_code', 'tax_code');
}
```

---

### 4. Recommended migration plan

#### Phase 1: Preparation (without breaking anything)
1. Create `VatVerificationServiceInterface` interface in Larabill (temporary)
2. Make `VatVerificationService` implement the interface
3. Update `RoiVerificationService` to use the interface
4. Tests pass

#### Phase 2: Extraction
1. Create ROI package with the interface
2. Move `VatVerificationService`, `VatApiIntegrationService`, `VatVerification` model
3. ROI package implements the interface
4. Larabill depends on ROI package

#### Phase 3: Cleanup
1. Remove duplicate code from Larabill
2. Update configuration
3. Update tests

---

### 5. Recommended ROI package structure

```
roi-vat-verification/
├── src/
│   ├── Contracts/
│   │   └── VatVerificationServiceInterface.php
│   ├── Services/
│   │   ├── VatVerificationService.php
│   │   └── VatApiIntegrationService.php
│   ├── Models/
│   │   └── VatVerification.php
│   └── RoiServiceProvider.php
├── database/
│   └── migrations/
│       └── create_vat_verifications_table.php
└── config/
    └── roi.php (vat_apis config)
```

---

### 6. Dependencies in composer.json

```json
// In Larabill
{
    "require": {
        "aichadigital/roi-vat-verification": "^1.0"
    }
}
```

---

### Summary

- Remove: `VatVerificationService`, `VatApiIntegrationService`, `vat_verifications` migration, related tests, `vat_apis` config
- Keep: `RoiVerificationService`, `BillingService`, adapted relationships, general config
- Coupling: `VatVerificationServiceInterface` interface that ROI package implements, dependency injection, binding in Service Provider

Do you want me to detail any specific part or prepare the interface/contract files?

