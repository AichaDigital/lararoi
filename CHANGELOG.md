# Changelog

All notable changes to `lararoi` will be documented in this file.

## [Unreleased]

## [v1.0.3] - 2026-07-04

### Fixed
- Upgrade path from larabill 3.x (AID-324): a new preflight migration (`2025_01_01_000000_drop_legacy_larabill_vat_verifications_table`) runs before the cache-table create. When the migrations ledger proves a pre-existing `vat_verifications` table is larabill 3.x's legacy VIES cache (its `2024_12_01_000007_create_vat_verifications_table` row), the preflight drops the table — disposable, TTL-bound cache data — together with the orphaned ledger row, letting lararoi create its canonical schema fresh; a homonymous table without that proof aborts the migration loudly with recovery steps (see larabill's packaged `UPGRADE-4.0.md`) instead of being claimed. A naive `hasTable` guard was rejected on purpose: it would let the legacy schema (UNIQUE composite index, string `company_address`) through to the rename migration, which drops the plain composite index by name and would crash halfway. Closes the "Known upgrade-path gap" documented under v1.0.2.

## [v1.0.2] - 2026-07-03

### Fixed
- The service provider now loads the package migrations via `loadMigrationsFrom()` in `boot()` (gated by `runningInConsole()`), so a consumer's plain `php artisan migrate` creates the `roi_*` tables as the README promises (AID-323). Previously they were only registered with Spatie's `hasMigrations()`, which makes them publishable but never feeds the migrator — consumers got no tables. The package's own suite masked the gap by registering the migrations path in its TestCase; that registration is removed so the whole suite now pins the provider's behavior, plus an explicit regression test (`MigrationLoadingTest`).

### Known upgrade-path gap (documented, not changed)
- Consumers coming from larabill 3.x with the legacy `vat_verifications` table already created will see `2025_01_01_000001_create_vat_verifications_table` collide (unguarded `Schema::create`). Drop the legacy larabill tables (`vat_verifications`, `roi_queries`, `user_roi_verifications`) and their migration-ledger rows before running `php artisan migrate` — or rebuild the database where acceptable.

## [v1.0.1] - 2026-07-03

### Documentation
- README refreshed for v1.0: dropped the beta banner, updated the feature list (tracking/audit log, per-consumer retention, output mapping, formal contract set), fixed the config publish tag to `lararoi-config`, and reflected the package-managed schema (`php artisan migrate`; tables `roi_vat_verifications` + `roi_verification_queries`). Linked `docs/contracts.md` and the ADRs.

### Fixed
- `VatVerificationServiceInterface::verifyVatNumber()` docblock no longer lists `@throws TrackingDisabledException`: that exception cannot surface on the inline tracking path (it is gated behind `tracking.enabled`, so only `UnknownConsumerException` can throw out of `verifyVatNumber()`). Dropped the now-unused import.
- Clarified the `VerificationResultMapperInterface::map()` docblock: the input is the full `verifyVatNumber()` result (with the seven canonical facts guaranteed), matching the implementation and examples, rather than "the seven facts" alone.

## [v1.0.0] - 2026-07-03

lararoi is now the single owner of the intra-community VAT/NIF verification domain — verification, cache, **and** multi-consumer tracking/audit — served to consumers through explicit, stable contracts and agnostic configuration (ADR-001, ADR-002). This is the first release consumers can pin to a real contract.

### Added
- **Multi-consumer tracking / audit log** (ADR-002 D3–D5): a new append-only `roi_verification_queries` table recording who verified what and when, attributed to the consumer, kept separate from the cache. Inert by default (`tracking.enabled` = `false`).
  - `VerificationContext` value object (`consumer` + opaque `subjectReference`).
  - `VerificationTrackerInterface` (+ default `VerificationTracker`): `record()` / `tryRecord()` and consumer-scoped reads `forSubject()` / `forVat()` / `between()`.
  - Optional third argument on `VatVerificationServiceInterface::verifyVatNumber($vat, $country, ?VerificationContext $ctx = null)` to record inline.
  - Consumer allow-list registry (`lararoi.consumers`): an unregistered consumer throws `UnknownConsumerException`; `record()` on a disabled tracker throws `TrackingDisabledException`.
  - Per-consumer retention (`retention_days`, UTC; `null` = keep forever) + the `roi:prune-verification-queries` command.
  - `VerificationQueryModelInterface` for a swappable tracking model.
- **Per-consumer output mapping** (ADR-002 D6): `VerificationResultMapperInterface` + `VerificationResultMapperRegistry` (config- or code-registered, identity default). Never applied inside `verifyVatNumber()` — the service's canonical `array` return stays stable.
- Formal, documented contract set (`docs/contracts.md`) and a full integration guide (`docs/integration.md`).

### Changed
- **Schema ownership** (ADR-002 D1/D2): the cache table is renamed `vat_verifications` → `roi_vat_verifications` via a new migration, with a UNIQUE `(vat_code, country_code)` index and an atomic upsert in `persistVerification()`. lararoi owns its tables under a `roi_` prefix; consumers must not create their own.
- Cache TTL now reads a single source, `lararoi.cache.ttl` (ADR-002 D8); the model no longer silently ignored a consumer-configured TTL.
- Migration is package-managed (auto-loaded on `php artisan migrate`), 0 stubs; the test suite discovers migrations via `RefreshDatabase` + `afterResolving('migrator')` (AID-301).

### Removed
- Dead config keys: `models.vat_verification.primary_key`, `models.vat_verification.foreign_key`, `logging.enabled`, `logging.level`. The audit trail is the tracking log, not Laravel logs.
- `SoftDeletes` on the cache model (incompatible with the UNIQUE cache key).

### Breaking
- `VatVerificationServiceInterface::verifyVatNumber()` gains an optional third parameter — a change for any alternate implementer / mock (callers are unaffected).
- Consumers obtain the schema by running `php artisan migrate` (package-managed), not by publishing a stub; the cache table is now `roi_vat_verifications`.
- The removed config keys above.

### Documentation
- Replaced the historical extraction analysis in `docs/integration.md` with an accurate guide; swept `configuration.md` / `usage.md` of the removed keys and the old table name; added `docs/contracts.md`.

## [v0.2.0] - 2025-11-16

### Added
- **Configurable caching system**: Enable/disable cache via `CACHE_ENABLED`
- **Cache status tracking**: Response includes `cache_status` field ('fresh', 'cached', 'refreshed')
- **Custom model support**: Full support for custom models with custom primary keys (UUID, ULID, etc.)
- **Custom foreign keys**: Configure foreign key names for relationships
- **Agnostic mode**: Disable cache for minimal database footprint
- Comprehensive test coverage for VerifyVatCommand (0% → 48.2%)
- Additional cache behavior tests

### Changed
- **Cache configuration restructured**: Moved from `cache_ttl` to `cache.enabled` and `cache.ttl`
- **Model configuration enhanced**: Support for `class`, `primary_key`, and `foreign_key`
- Updated all documentation (configuration.md, usage.md) with new features
- Environment variables updated in `.env.example`

### Fixed
- PHPStan nullsafe operator error in IsvatProvider
- PHP Insights code quality issues (empty() usage, parentheses)
- Test failures in VerifyVatCommandTest

### Documentation
- Added "Advanced Usage" section with agnostic mode examples
- Added complete guide for custom models with relationships
- Updated environment variables documentation
- Added cache behavior documentation

## [v0.1.0] - 2025-01-XX

### Initial Release
- VAT number verification via multiple providers
- Support for VIES (REST/SOAP), isvat.eu, vatlayer, viesapi
- Dual-layer caching (memory + database)
- Automatic provider fallback
- Robust error handling
- Development commands for testing
