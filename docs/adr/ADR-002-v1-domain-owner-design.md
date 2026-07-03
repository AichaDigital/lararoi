# ADR-002: lararoi v1.0 domain-owner design — tracking, retention, agnostic contracts, schema ownership

> **Status**: Proposed
> **Date**: 2026-07-03
> **Author**: Abdelkarim Mateos
> **Implements**: ADR-001 (lararoi owns the intra-community VAT/NIF verification domain). ADR-001 is the umbrella *decision*; this ADR is the *design* that turns its six actions into concrete contracts, schema and a surgical PR sequence toward `v1.0.0`.
> **Relates**: lararoi AID-310 (append-only history — superseded/reframed by this ADR), larabill AID-309 (the consumer that adopts lararoi after the tag), lararoi AID-263 + AID-301 (both Done: canonical shape, syntax validation, package-managed schema).

## Context

ADR-001 decided that lararoi is the single owner of verification **and** its persistence / tracking / audit, served to N consumers (larabill, an accounting app, …) via explicit contracts and agnostic configuration. Verified state of the code (2026-07-03):

- Public contract covers only `verifyVatNumber(string, string): array` (`src/Contracts/VatVerificationServiceInterface.php:34`).
- The canonical output shape and per-country syntax validation shipped (AID-263, PR #23); schema is package-managed (AID-301, PR #25). **No blocker remains** (the umbrella note "AID-301 blocked by AID-263" is stale — both are Done).
- The tracking/audit log the ADR marks as the core gap does not exist. `vat_verifications` is a single-row-per-NIF TTL **cache**, not a history.
- Latent defects found while scoping: the cache TTL is read two different ways — `VatVerification::isExpired()` reads `config('lararoi.cache_ttl')` which **does not exist** in `config/lararoi.php` (the config defines `cache.ttl`), so the model always falls back to the 86400 default and ignores a consumer's TTL. Config also carries dead keys never read by any code (`models.vat_verification.primary_key`, `models.vat_verification.foreign_key`, `logging.enabled`, `logging.level`).
- `docs/integration.md` is not an integration guide — it is the historical larabill-extraction analysis ("code to remove/move to ROI package"). Misleading; must be replaced.

## Scope of this ADR

Design-level decisions for `v1.0.0`, each landing as its own surgical PR. This ADR does **not** change any consumer; consumer adoption (larabill AID-309, accounting) happens after the tag.

## Decisions

### D1 — Schema ownership & explicit naming (resolves the `vat_verifications` collision)

lararoi owns its tables and names them with an unambiguous `roi_` prefix so no consumer collides by picking a natural generic name:

- Cache table: `vat_verifications` → **`roi_vat_verifications`**.
- Tracking table (new, D3): **`roi_verification_queries`**.
- **Table names are fixed, not config-overridable.** Package-managed schema (AID-301) and a runtime table-name override pull in opposite directions: if a consumer changed `cache.table` after the migration ran, the model would point at a table that does not exist. The `roi_` prefix already gives collision-immunity, so the override adds drift risk with no benefit. A consumer that truly needs a different store swaps the *model* (D3/D6 model-swap contracts), it does not rename lararoi's tables. **No `cache.table` / `tracking.table` config keys.**
- **Cache uniqueness is enforced by schema, not just convention (P1 review):** `roi_vat_verifications` gets a **UNIQUE index on `(vat_code, country_code)`** (today's migration has only a plain index). Combined with an atomic upsert in `persistVerification()` (D2), this makes the "one current row per NIF" invariant race-safe; two concurrent verifications cannot create duplicate cache rows.
- **The cache model drops `SoftDeletes` (Codex P1).** A UNIQUE index on `(vat_code, country_code)` is incompatible with soft-delete: a soft-deleted row keeps occupying the unique slot, and an upsert can silently update an invisible row that `findByVatCodeAndCountry()` (which excludes soft-deleted) never returns. A cache is overwrite-in-place, not a paranoid retain-on-delete store, so `SoftDeletes` and the `deleted_at` column are removed from `VatVerification` and the migration. (Audit history lives in the append-only tracking log, D3 — not in soft-deleted cache rows.)
- **Drop the unused `checked_at` and `expires_at` columns (P2 review):** the current migration creates them but the model never exposes them (absent from `fillable`/`casts`, `src/Models/VatVerification.php:23`). Expiry is derived from `verified_at` + `cache.ttl` (D8); `updated_at` already records last touch. Remove both columns rather than leave dead schema.
- **Rename via a NEW migration, never by editing the historical one (Codex P1).** `LararoiServiceProvider::configurePackage()` registers the fixed name `hasMigrations(['2025_01_01_000001_create_vat_verifications_table'])` (`src/LararoiServiceProvider.php:35`). Rewriting that already-registered migration to create `roi_vat_verifications` would **not re-run** on any DB that already migrated → the model would point at a table that does not exist ("table not found"). PR 2 ships a **new** `..._rename_vat_verifications_to_roi.php` migration (rename table + swap the plain index for the UNIQUE one + drop `deleted_at`/`checked_at`/`expires_at`), added to `hasMigrations()`. The package test suite (RefreshDatabase, fresh each run) is unaffected either way; the new migration is for any already-migrated consumer/dev DB.

Rationale: we are pre-1.0 and the generic name already collided (larabill created its own `vat_verifications`). No production data to preserve (dev-main only). One-time churn (migration + model `$table` + tests + docs) buys visible ownership and structural collision-immunity. Chosen over the lower-churn "keep the name, forbid consumers from recreating it" precisely because the generic name is the collision.

### D2 — The cache stays a cache (supersede AID-310)

`roi_vat_verifications` remains "current status per NIF": one current row per `(vat_code, country_code)`, TTL-expiring, `findByVatCodeAndCountry` → latest. It is **not** turned append-only. AID-310's instinct (we lack history) is right; its mechanism (make the cache append-only) conflates two concerns. History lives in the separate tracking log (D3). AID-310 is reframed as the D3 issue.

The "one current row" invariant is enforced structurally (D1: UNIQUE index on `(vat_code, country_code)`). `persistVerification()` (`src/Services/VatVerificationService.php:177`) is rewritten from its current find-or-new-then-save (a read-then-write race) to a single **atomic upsert** keyed on `(vat_code, country_code)`, so concurrent verifications of the same NIF converge on one row instead of racing to insert duplicates.

### D3 — Multi-consumer tracking / audit log (the core gap)

A new **append-only** table `roi_verification_queries`, the store of record for "who verified what, when". **Inert by default** — the table is always created (package-managed schema), but a row is written only when **both** gates are open: `lararoi.tracking.enabled === true` (default **false**) **and** a `VerificationContext` is supplied (D4). The canonical 2-argument call therefore writes nothing. `tracking.enabled` is the single kill-switch and gates the explicit `record()` path too.

Schema:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | auto-increment; no FK to `users` (not user-scoped) |
| `consumer` | string, indexed | consumer key (e.g. `larabill`, `openmiza`) |
| `subject_reference` | string, nullable, indexed | opaque consumer entity ref; lararoi never interprets it |
| `vat_code` | string(50), indexed | |
| `country_code` | string(2), indexed | |
| `is_valid` | boolean | result snapshot |
| `api_source` | string(50) | provider that answered |
| `cache_hit` | boolean | served from cache vs. live provider |
| `response_snapshot` | json, nullable | **canonical public shape only** — never the raw provider payload (see minimization note) |
| `queried_at` | timestamp, indexed | |
| `retention_until` | timestamp, nullable, indexed | derived from the consumer's declared policy (D5); null → never auto-pruned |
| `created_at`/`updated_at` | timestamps | |

Composite indexes match the actual read predicates (Codex P2): `(consumer, subject_reference)` for `forSubject`, `(consumer, vat_code, country_code)` for `forVat`, `(consumer, queried_at)` for `between`, and `(retention_until)` for the prune scan. No plain single-column index that a consumer-scoped read would ignore. Append-only: rows are never updated; expiry is a physical prune (D5), so no soft-deletes.

Model is swappable via a new `VerificationQueryModelInterface` (same pattern as `VatVerificationModelInterface`).

**Snapshot minimization + a precise "canonical verification facts" definition (Codex P1/P3):** the current service return (`VatVerificationService::formatResponse()`, `src/Services/VatVerificationService.php:249`) is **not** a clean snapshot source — it carries cache-status meta (`cached`, `cache_status`) and a **nested `response_data`** copy of itself. `response_snapshot` stores **only the canonical verification facts** — `is_valid`, `vat_code`, `country_code`, `company_name`, `company_address`, `api_source`, `request_date` — and **never** `cached`/`cache_status` (cache meta, not a fact of the verification), the nested `response_data` (self-reference), or the raw provider payload. The same seven-field set is what "canonical" means for the mapper (D6), so tracker and mapper see one consistent definition, not the fatter service return. Rationale: the table is append-only with `retention_until` defaulting to a registered value, so persisting cache meta or raw provider blobs (potential PII, provider quirks) would be an uncontrolled data-retention liability — the `lara-privacy` crossover we keep out of scope. Company name/address are inherent to the result and retained by design; D5 governs how long.

**`subject_reference` non-PII guidance (Codex P2):** lararoi treats it as opaque, but "opaque" is not minimization. The integration guide (D7) must require consumers to pass a **stable surrogate id** (their own entity PK / a hash), never raw PII (email, invoice number, VAT-like value). lararoi cannot enforce content on an opaque field; the guardrail is documented contract + the fact that a registered consumer with a real `retention_days` bounds how long it lives.

### D4 — Attribution: optional context on the service + explicit tracker

The canonical contract `verifyVatNumber(string $vatNumber, string $countryCode): array` stays. v1.0 adds an **optional** third argument:

```php
public function verifyVatNumber(
    string $vatNumber,
    string $countryCode,
    ?VerificationContext $context = null,
): array;
```

- `VerificationContext` is a value object: `consumer` (required string, must be a registered key — D5) + `subjectReference` (nullable, opaque string).
- **Two write paths with different failure semantics (P1 review):**
  - **Inline/passive** — when `$context` is present **and** `tracking.enabled`, `verifyVatNumber()` writes one row after resolving the result (recording `cache_hit`). With `tracking.enabled=false` or no context it silently writes nothing — this is the *passive* path where "no tracking" is an acceptable default, not a lost explicit intent.
  - **Explicit** — `VerificationTrackerInterface::record()` is for the consumer that deliberately records a verification it performed via lararoi out-of-band (a `verifyVatNumber()` result it is persisting from another point in its flow). Its input is **the full service result array** (the `verifyVatNumber()` return, which carries `cache_status`), not a hand-built payload: `record()` derives `cache_hit` from that result's `cache_status`/`cached` and extracts the seven canonical facts for `response_snapshot` — the consumer never supplies `cache_hit` manually (Codex P1: a manual bool defaulting false lets a cached result be logged as live). An explicit `record()` **must never no-op silently**: it returns the created `VerificationQueryModelInterface` row, and throws `TrackingDisabledException` when `tracking.enabled=false`. Best-effort callers use `tryRecord(): ?VerificationQueryModelInterface` (returns null when disabled). Silence is opt-in, never the default for an explicit call. **`record()` records a real verification result only — it is not a channel for asserting an unverified status (see the P1-4 boundary note under Open decisions).**
- **All reads are consumer-scoped (P1 review).** In a multi-consumer table no read may return another consumer's history, so `consumer` is a required first argument on every read: `forSubject(string $consumer, string $subjectReference)`, `forVat(string $consumer, string $vatCode, string $countryCode)`, `between(string $consumer, CarbonInterface $from, CarbonInterface $to)`. There is no cross-consumer read in the public contract.

Tracker interface sketch:

```php
interface VerificationTrackerInterface
{
    /**
     * @param array $verificationResult the verifyVatNumber() return; cache_hit is
     *                                   derived from its cache_status, snapshot from its facts
     * @throws TrackingDisabledException when tracking is disabled
     * @throws UnknownConsumerException  when $context->consumer is not registered
     */
    public function record(VerificationContext $context, array $verificationResult): VerificationQueryModelInterface;

    /** Best-effort variant: returns null instead of throwing when disabled. */
    public function tryRecord(VerificationContext $context, array $verificationResult): ?VerificationQueryModelInterface;

    /** @return iterable<VerificationQueryModelInterface> — consumer-scoped */
    public function forSubject(string $consumer, string $subjectReference): iterable;
    public function forVat(string $consumer, string $vatCode, string $countryCode): iterable;
    public function between(string $consumer, CarbonInterface $from, CarbonInterface $to): iterable;
}
```

This keeps attribution owned by the package (not re-implemented per consumer) without forcing every consumer to verify at the same point, and makes explicit-record failures and cross-consumer isolation contractual rather than incidental.

### D5 — Retention: per-consumer, neutral by default

- **A consumer must be registered to be tracked (P2 review).** `lararoi.consumers` is an explicit allow-list keyed by consumer id. When `tracking.enabled`, a `record()`/`verifyVatNumber($ctx)` whose `consumer` is **not** a registered key throws `UnknownConsumerException`. This closes the typo hole — `larabil` instead of `larabill` fails loudly instead of silently accumulating unpolicied, never-pruned history — and forces every real consumer to exist in config before it can write.
- Each registered consumer declares `retention_days`. `null` is allowed but must be a **conscious registered choice** ("keep forever"), not the accident of an unregistered key. lararoi stamps `retention_until = queried_at + retention_days`; `null` → `retention_until` null → never auto-pruned.
- **Timestamps are UTC (Codex P2).** `queried_at`, `retention_until` and the prune's `now` comparison are stored and compared in UTC, independent of the app/DB session timezone, so pruning is not off-by-a-day around timezone or DST boundaries. Fiscal retention is day-or-coarser granularity, so UTC-day semantics are sufficient and unambiguous.
- Command `roi:prune-verification-queries` deletes only rows with `retention_until < now` (UTC). Never touches null-retention rows.
- Legal retention is the consumer's fiscal obligation and policy; lararoi is the storage + enforcement. The integration guide (D7) documents that registering the consumer (with an explicit `retention_days`, `null` included) is a precondition of tracking. Cross with `lara-privacy` is noted but **not** coupled.

### D6 — Output agnosticism: explicit mapper, never a global mutation

The service always returns the stable canonical shape (`VatVerificationService::formatResponse()`, `src/Services/VatVerificationService.php:247`) — config never rewrites it. A consumer whose domain uses a different shape declares an **explicit adapter**:

- `VerificationResultMapperInterface { public function map(array $canonical): mixed; }`, resolved **per consumer** via a registry (`mapperFor(string $consumer): VerificationResultMapperInterface`), default = identity mapper returning the canonical array.
- **The mapper is never applied inside `verifyVatNumber()` (Codex P1).** That method's return type stays `array` (the canonical seven-field result, D3); it does not become `mixed`. The mapper is a **separate, consumer-invoked transform**: the consumer resolves `mapperFor($consumer)->map($canonicalArray)` at its own boundary when it wants its own shape. This is the defined callable surface — there is no hidden hook that rewrites the service return. `map(): mixed` is intentional (the consumer's shape is unknown to lararoi) and cannot leak into the service's typed `array` contract.
- Input to `map()` is the same canonical seven-field set the tracker snapshots (D3) — one "canonical" definition across service, tracker and mapper.

### D7 — Formal contract set + real integration guide

Public contract surface at v1.0 (documented as the consumer contract):

1. `VatVerificationServiceInterface` (verify; canonical result) — existing, extended with optional context.
2. `VatProviderInterface` — existing.
3. `VatVerificationModelInterface` — existing (cache model swap).
4. `VerificationTrackerInterface` — new (record / read tracking).
5. `VerificationQueryModelInterface` — new (tracking model swap).
6. `VerificationResultMapperInterface` — new (per-consumer output mapping).
7. `VerificationContext` — new value object.

Supporting exceptions (part of the contract's failure semantics): `TrackingDisabledException` (explicit `record()` while tracking is disabled — D4) and `UnknownConsumerException` (unregistered consumer while tracking is enabled — D5).

`docs/integration.md` is **replaced** (not edited) with a real guide: install → config → verify → read canonical result → record/read tracking → declare mapping → declare retention → do **not** create your own verification/tracking tables (you get them from lararoi).

### D8 — Config hygiene (surgical, no new domain)

- Fix the TTL split: single source `lararoi.cache.ttl`; `VatVerification` reads it, drop the non-existent `cache_ttl` key and the service's dual-read fallback.
- Remove dead config keys never read by any code: `models.vat_verification.primary_key`, `models.vat_verification.foreign_key`, `logging.enabled`, `logging.level`. (The real audit trail is the tracking log, not Laravel logs; if lightweight diagnostic logging is wanted later it returns as a wired, honestly-named key.)

### D9 — Versioning

Once D1–D8 land, cut **`v1.0.0`** with the documented public surface + CHANGELOG. This is the "activate the dependency" precondition larabill (AID-309) was missing — consumers pin a real, stable contract.

### Config surface (v1.0, illustrative)

Reflects D1 (fixed table names), D3 (tracking gate + swappable model), D5 (consumer registry + retention) and D8 (single-source TTL, dead keys removed). Table names are **not** config keys (D1):

```php
'cache' => [
    'enabled' => true,
    'ttl' => 86400,                       // D8: single source; model + service both read this
],
'tracking' => [
    'enabled' => false,                   // D3: inert by default (kill-switch; still needs context)
],
'models' => [
    'vat_verification' => ['class' => VatVerification::class],
    'verification_query' => ['class' => VerificationQuery::class],  // D3: swappable tracking model
    // D8: primary_key / foreign_key keys removed (dead)
],
'consumers' => [
    // D5: allow-list. Unregistered consumer + tracking enabled → UnknownConsumerException.
    // 'larabill' => ['retention_days' => 2555],   // explicit policy (7y example)
    // 'openmiza' => ['retention_days' => null],    // conscious "keep forever"
],
// D8: 'logging' block removed (dead); table names are fixed (D1), not config
```

## Surgical PR sequence

Each PR is scoped to lararoi, English, with tests; each maps to one Linear issue.

- **Step 0 — Merge ADR-001 (PR #26).** Flip `Proposed` → `Accepted` in the doc, squash-merge. (Decision, recorded; the merge itself awaits explicit go-ahead.)
- **PR 1 — Config hygiene (D8).** TTL single-source + dead-key removal + replace `docs/integration.md` with an accurate guide of the *current* surface (verify + canonical + cache + model-swap). No schema change. Tests green.
- **PR 2 — Schema ownership (D1).** A **new** `..._rename_vat_verifications_to_roi.php` migration (added to `hasMigrations()`, never edit the historical one): rename table → `roi_vat_verifications`, swap the plain index for a UNIQUE `(vat_code, country_code)`, drop `deleted_at`/`checked_at`/`expires_at`; remove `SoftDeletes` from `VatVerification` + `$table`; rewrite `persistVerification()` as an atomic upsert; tests, docs.
- **PR 3 — Tracking + retention (D2–D5, supersedes AID-310).** `roi_verification_queries` migration (consumer-scoped indexes); `VerificationContext`; `VerificationTrackerInterface` + default impl (derives `cache_hit` + minimizes snapshot to the seven canonical facts); `VerificationQueryModelInterface` + model; optional context on `verifyVatNumber`; consumer registry allow-list + `UnknownConsumerException` + `TrackingDisabledException`; `consumers.<key>.retention_days` (UTC); `roi:prune-verification-queries` command. **Tests must run against a real driver (MySQL/MariaDB), not only SQLite (Codex P2 / umbrella SQLite-masking lesson):** retention boundary (`retention_until` exactly at `now`), null-retention never pruned, consumer isolation on every read, upsert + UNIQUE behavior on the production driver, explicit `record()` throwing when disabled.
- **PR 4 — Output agnosticism (D6).** `VerificationResultMapperInterface` + per-consumer registry + identity default. **Ships inside v1.0** (it is a public contract in D7, so it must land before the D9 tag — no post-1.0 deferral, which would contradict the contract surface).
- **PR 5 — Contract set + full integration guide (D7).** Document the 7 contracts; expand `docs/integration.md` to the full consumer guide.
- **PR 6 — Cut `v1.0.0` (D9).** CHANGELOG, version, tag.

## Assumptions pending confirmation

These defaults are encoded above; flip any and the affected section/PR adjusts:

1. **Mapper (D6/PR 4) ships inside v1.0** — resolved (was flagged as a scope contradiction in review). It is a public contract in D7, so it lands before the D9 tag. To flip to "defer post-1.0" you must also drop it from the D7 v1.0 contract list; the two cannot disagree.
2. **`logging.enabled`/`logging.level` are removed** (confirmed dead config), not redocumented.
3. **This design lives as `docs/adr/ADR-002`** (package convention) rather than `docs/superpowers/specs/`.

## Decision — record() records real verifications only (Codex P1-4, resolved R1)

ADR-001 said a consumer may "simply read/record tracking" without live verification, because larabill's reverse-charge decision is an `is_roi_taxed` **input**, not a verification. But `record()` (D4) requires a real `verifyVatNumber()` result — a consumer that never verifies would have to **fabricate** audit data, and the log would stop meaning "who verified what, when".

**Resolved (R1):** `record()` records a real verification result only. A consumer that never verifies has **no tracking rows**; `is_roi_taxed` stays a larabill business input, outside lararoi's verification domain. "Read tracking" stays valid (query the log); "record tracking" means recording an actual verification, never asserting an unverified status.

The alternative (R2 — an `assertion` entry type for "treated NIF X as ROI-taxed on date Y" without a verification) was **rejected**: that is a consumer's *business* audit log, a different domain, and folding it into lararoi's verification store re-imports the coupling this ADR exists to remove. It is revisited only if an explicit fiscal requirement to record treatment-decisions-without-verification ever appears — and even then it belongs to the consumer, not here.

Because ADR-001 is the umbrella doc and gets cited, this boundary is **amended into ADR-001 itself** (not left for ADR-002 to supersede) before PR #26 merges, with the line: *"Tracking records real verification results only; consumer business assertions such as `is_roi_taxed` remain outside lararoi's verification log."*

## Consequences

**Positive**
- One place owns verification + cache + tracking + the agnostic contracts. Consumers stop duplicating and stop colliding.
- larabill and the accounting app share one audit/tracking capability; each declares its own retention and (optionally) its own output shape without forking code.
- A meaningful `v1.0.0`: the tag pins a real contract, unblocking AID-309.

**Costs / risks**
- Table rename (D1) touches migration, model, tests, docs — one-time, dev-main only.
- **Adding the optional context arg to `VatVerificationServiceInterface::verifyVatNumber` is a deliberate BC break for v1.0, not merely caller-compatible (Codex P1).** Callers are unaffected, but any alternate implementer or test mock with the two-arg signature fatals until updated. This is acceptable — it is exactly what a major version is for — but it is called out as an intentional break, and the package's own mocks/tests are updated in the same PR.
- `retention_days: null` can retain indefinitely; mitigated structurally — a consumer must be a registered allow-list key with an explicit `retention_days` (D5), so `null` is a conscious choice and a typo'd/unregistered consumer throws instead of accumulating unpolicied history.
- Append-only tracking grows unbounded for `null`-retention consumers; the prune command only touches rows with a set `retention_until`. Operators of keep-forever consumers own that growth.

## Non-goals

- No consumer changes here (larabill AID-309, accounting adopt after the tag).
- No provider-level `requestIdentifier`/legal-proof capture (future domain-level enhancement if a binding proof is ever required).
- No coupling to `lara-privacy`; the retention/anonymization crossover is noted, not wired.
