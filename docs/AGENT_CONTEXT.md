# lararoi — Context for AI Agents

> Read this first for orientation. It summarizes the package; the **source of
> truth** is the code plus the ADRs and reference docs linked at the bottom.
> When this file and those disagree, they win — fix this file.

## Identity

**lararoi** owns the intra-community **VAT/NIF (ROI) verification domain**: the
verification itself, its TTL cache, and a multi-consumer tracking/audit log —
exposed to consumer apps (billing, accounting, …) through explicit, stable
contracts and agnostic configuration. Consumers verify *through* lararoi; they
never reimplement the domain (ADR-001, ADR-002).

| Item | Value |
|------|-------|
| Package | `aichadigital/lararoi` (Packagist, public) |
| Version | v1.0.1 |
| License | **MIT** |
| PHP | `^8.3` |
| Laravel | 12+ (`illuminate/* ^12.0 \|\| ^13.0`) |
| Testing / quality | Pest, PHPStan level 5, Laravel Pint |

## Architecture (the real one)

The five responsibilities lararoi owns (ADR-001):

1. **Verification** — `verifyVatNumber(vat, country)` over a multi-provider
   manager with **automatic fallback** and per-country **syntax validation**
   (`Support/VatFormat`), returning a canonical array.
2. **Cache** — table `roi_vat_verifications`: one current row per
   `(vat_code, country_code)`, TTL (default `86400`s), enforced by a UNIQUE key.
   It is a cache, **not** a history.
3. **Multi-consumer tracking / audit log** — table `roi_verification_queries`:
   append-only, consumer-attributed. **Inert by default** (`tracking.enabled`
   defaults to `false`).
4. **Output agnosticism** — an optional per-consumer mapper
   (`VerificationResultMapperInterface`), applied at the consumer's own boundary,
   **never** inside `verifyVatNumber()` (which always returns the canonical array).
5. **Contracts** — the documented, stable contract set (below).

Schema is **package-managed**: run `php artisan migrate`. There is no stub to publish.

### Providers (real set — no others exist)

- **Free:** `vies_soap` (VIES SOAP, official EU), `vies_rest` (VIES REST,
  unofficial), `isvat` (isvat.eu).
- **Paid:** `viesapi` (viesapi.eu), `vatlayer` (vatlayer.com).

Order is set by `providers_order` (default `['vies_soap', 'vies_rest', 'isvat']`).
Paid providers register only when enabled **and** an API key is present. There is
**no** `AbstractAPI` or `APILayer` provider (vatlayer.com happens to be operated
by APILayer, but the lararoi provider key is `vatlayer`).

## Configuration (real keys — `config/lararoi.php`)

- `cache.enabled` (env `CACHE_ENABLED`), `cache.ttl` (`CACHE_TTL`)
- `timeout` (`API_TIMEOUT`)
- `providers_order` (`PROVIDERS_ORDER`, comma-separated)
- `vies.test_mode` (`VIES_TEST_MODE`)
- `provider_config.vatlayer.{enabled,api_key}`,
  `provider_config.viesapi.{enabled,api_key,api_secret,ip}`,
  `provider_config.isvat.use_live`
- `models.vat_verification.class` (`VAT_VERIFICATION_MODEL`),
  `models.verification_query.class` (`VERIFICATION_QUERY_MODEL`)
- `tracking.enabled` (`LARAROI_TRACKING_ENABLED`, default `false`)
- `consumers.<key>.{retention_days, mapper}` — the retention allow-list (config-only)

There is **no** `LARAROI_PRIMARY_PROVIDER`, **no** `providers.primary` /
`providers.fallback`, and **no** `cache.driver`.

## Contracts, models, exceptions (real)

Namespaces: `Aichadigital\Lararoi\{Contracts, ValueObjects, Exceptions, Models,
Services, Providers}`.

Contract set — **6 interfaces + 1 value object** (full signatures in `contracts.md`):

- `VatVerificationServiceInterface` —
  `verifyVatNumber(string $vatNumber, string $countryCode, ?VerificationContext $context = null): array`.
  The VAT number is passed **without** the country prefix. Bound in the container.
- `VatProviderInterface` — implemented by each provider.
- `VatVerificationModelInterface` — the cache model (default `Models\VatVerification`
  → `roi_vat_verifications`).
- `VerificationTrackerInterface` — the tracking/audit contract (default
  `Services\VerificationTracker`).
- `VerificationQueryModelInterface` — the tracking model (default
  `Models\VerificationQuery` → `roi_verification_queries`).
- `VerificationResultMapperInterface` — resolved via
  `Services\VerificationResultMapperRegistry` (default: identity mapper).
- `VerificationContext` (value object) — `{consumer, subjectReference}`.

Exceptions: `VatVerificationException` (base; `getErrorCode()`, `getApiSource()`),
`ApiUnavailableException` (extends it; every provider failed),
`TrackingDisabledException`, `UnknownConsumerException`.

Models: **`VatVerification`**, **`VerificationQuery`**. There is **no** `RoiQuery`
(that was larabill's now-removed duplicate — see ADR-001).

## Key commands (real signatures)

- `php artisan lararoi:verify` — interactive verification
  (`--provider`, `--vat`, `--country`, `--name`)
- `php artisan roi:prune-verification-queries` — delete expired tracking rows
  (only rows whose `retention_until` has passed, UTC)
- `php artisan lararoi:dev:list-providers` — list providers and availability
- `php artisan lararoi:dev:test-provider <vat> <country> [provider] [--all] [--json]`
  — hit a real provider (development only)
- `php artisan lararoi:dev:test-from-file [--file=]` — verify VATs from a file
  (development only)
- `php artisan lararoi:dev:generate-stubs` — capture real API responses as test
  stubs (development only)

## Conventions and anti-patterns

**DO:**

- Pass the VAT **without** the country prefix:
  `verifyVatNumber('B12345678', 'ES')` — not `'ESB12345678'`.
- Resolve the service via its interface:
  `app(VatVerificationServiceInterface::class)`.
- Handle results/failures: a syntactically malformed number returns
  `is_valid = false` with `api_source = 'LOCAL_VALIDATION'` (no throw); empty
  input throws `VatVerificationException` (`getErrorCode() === 'INVALID_INPUT'`);
  every provider failing throws `ApiUnavailableException`.

**DON'T:**

- Invent config or providers — no primary/fallback split, no
  `AbstractAPI`/`APILayer`, no `cache.driver`.
- Create your own `vat_verifications` / `verification_queries` tables, or
  re-implement caching / fallback / audit — consume the contracts (see
  `integration.md`).
- Claim larabill auto-verifies via an event listener. **larabill does not consume
  lararoi yet** (adoption is pending — larabill AID-309). larabill is the
  *intended first consumer*, not a current one; there is no automatic
  verification hook.

## Pointers (source of truth)

| Path | What it is |
|------|-----------|
| `docs/adr/ADR-001-verification-domain-ownership.md` | Domain ownership decision |
| `docs/adr/ADR-002-v1-domain-owner-design.md` | v1.0 design (schema, tracking, contracts) |
| `docs/integration.md` | Full consumer flow: install → verify → track → retain → map |
| `docs/contracts.md` | Every contract signature |
| `docs/configuration.md` | Every config key and env var |
| `docs/usage.md` | Usage patterns and examples |
| `docs/development.md` | Development commands, testing against real APIs |
| `docs/project.md` | Background research on the VAT-verification landscape (VIES + providers) — **not** the package API |
| `README.md`, `CHANGELOG.md` | Overview and version history |
