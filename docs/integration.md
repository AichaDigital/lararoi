# Integrating lararoi

lararoi owns intra-community VAT/NIF verification **and** its persistence,
tracking and audit. It verifies numbers against multiple providers (VIES
SOAP/REST, isvat, viesapi, vatlayer) with automatic fallback and per-country
syntax validation, keeps a TTL cache of current status per NIF, and — when you
opt in — an append-only, per-consumer audit log of who verified what and when.

You are a **consumer**: you inject lararoi's service and read its contracts. Do
**not** build your own verification or tracking layer, and do **not** create your
own verification/tracking tables — you get them from lararoi (see the last
section).

This guide follows the full flow: **install → migrate → configure → verify →
read the canonical result → record & read tracking → declare retention → declare
an output mapping**. For the exact method signatures see `contracts.md`; for
every config key see `configuration.md`; for more usage examples see `usage.md`.

## 1. Install

```bash
composer require aichadigital/lararoi
```

The service provider auto-registers (Laravel package discovery). No `v1.0.0` is
tagged yet — you are installing the development line that carries the v1.0
contract surface described here.

## 2. Publish & migrate (package-managed schema)

The schema is **package-managed** — there are no migration stubs to publish. Just
run migrations:

```bash
php artisan migrate
```

This creates the two tables lararoi owns:

- **`roi_vat_verifications`** — the cache: one current row per
  `(vat_code, country_code)`, TTL-expiring.
- **`roi_verification_queries`** — the append-only tracking / audit log (created
  always, written only when you opt in — step 6).

Publishing the config is optional, only if you want to tune providers, cache,
tracking or retention:

```bash
php artisan vendor:publish --tag=lararoi-config
```

## 3. Configure

Everything works out of the box with the free VIES providers and the cache on.
The keys you are most likely to touch (full list in `configuration.md`):

- **`providers_order`** — order providers are tried (env `PROVIDERS_ORDER`,
  comma-separated). Defaults to VIES first.
- **`cache.enabled`** / **`cache.ttl`** — the TTL cache (default on, `86400`s).
- **`tracking.enabled`** — the tracking kill-switch (default **`false`** — see
  step 6).
- **`consumers`** — the retention allow-list (step 6/7).
- **`models.vat_verification.class`** / **`models.verification_query.class`** —
  swap the cache or tracking model.

## 4. Verify a VAT number

Resolve the service from the container and call
`verifyVatNumber($vatNumber, $countryCode)` — the VAT number is passed
**without** the country prefix:

```php
use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;

$result = app(VatVerificationServiceInterface::class)
    ->verifyVatNumber('B12345678', 'ES');
```

Or inject `VatVerificationServiceInterface` into your own class' constructor —
Laravel resolves the bound singleton.

## 5. Read the canonical result

`verifyVatNumber()` returns an array. These fields are the **stable canonical
contract** — the same seven facts feed tracking snapshots and output mappers:

| Key | Type | Meaning |
|---|---|---|
| `is_valid` | `bool` | Whether the VAT number is valid |
| `vat_code` | `string` | Full code with country prefix (e.g. `ESB12345678`) |
| `country_code` | `string` | Two-letter country code |
| `company_name` | `string\|null` | Registered name, when the provider returns it |
| `company_address` | `string\|null` | Registered address, when available |
| `api_source` | `string` | Which provider answered (or `LOCAL_VALIDATION` for a syntax rejection) |
| `request_date` | `string\|null` | Provider timestamp, when available |
| `cached` | `bool` | `true` when served from cache (backward-compatible flag) |
| `cache_status` | `string` | `fresh`, `cached`, or `refreshed` |
| `response_data` | `array` | Underlying data snapshot |

Obviously malformed numbers for known countries are rejected **locally** (no
provider call) with `api_source = LOCAL_VALIDATION` and `is_valid = false`. Empty
input throws `VatVerificationException`; if every provider fails, the call throws
`ApiUnavailableException`.

## 6. Record & read tracking

The tracking log (`roi_verification_queries`) is the store of record for "who
verified what, when". It is **inert by default**: a row is written only when
**both** gates are open —

1. `lararoi.tracking.enabled === true` (the single kill-switch, default
   `false`), **and**
2. a `VerificationContext` is supplied.

The plain 2-argument `verifyVatNumber($vat, $country)` therefore writes nothing,
ever. Tracking records **real verification results only** — a consumer that never
verifies has no tracking rows, and business assertions (larabill's `is_roi_taxed`,
say) stay outside lararoi.

### Register the consumer first (mandatory allow-list)

When tracking is on, `lararoi.consumers` is a mandatory allow-list. An
unregistered `consumer` throws `UnknownConsumerException` — this closes the typo
hole (`larabil` vs `larabill`) so a mistyped key fails loudly instead of silently
accumulating unpolicied history.

```php
// config/lararoi.php
'tracking' => [
    'enabled' => true,
],
'consumers' => [
    'larabill' => ['retention_days' => 2555],  // register before recording
],
```

### Path A — inline (passive) attribution

Pass a `VerificationContext` as the third argument. When tracking is enabled, one
append-only row is recorded after the result is resolved (capturing `cache_hit`);
when tracking is disabled or no context is given, nothing is written silently.

```php
use Aichadigital\Lararoi\Contracts\VatVerificationServiceInterface;
use Aichadigital\Lararoi\ValueObjects\VerificationContext;

$result = app(VatVerificationServiceInterface::class)->verifyVatNumber(
    'B12345678',
    'ES',
    new VerificationContext(consumer: 'larabill', subjectReference: (string) $company->id),
);
```

An enabled-but-unregistered consumer still throws `UnknownConsumerException` out
of `verifyVatNumber()` on this path.

### Path B — explicit record

For a verification you performed via lararoi at one point and are recording from
another point in your flow, call the tracker directly with the
`verifyVatNumber()` return. The tracker derives `cache_hit` from the result and
minimizes the snapshot to the seven canonical facts — you never pass `cache_hit`
or hand-build the payload.

```php
use Aichadigital\Lararoi\Contracts\VerificationTrackerInterface;

$tracker = app(VerificationTrackerInterface::class);
$context = new VerificationContext(consumer: 'larabill', subjectReference: (string) $company->id);

// Strict: throws TrackingDisabledException when tracking is off, always returns the row.
$row = $tracker->record($context, $result);

// Best-effort: returns null (no throw) when tracking is off; still throws
// UnknownConsumerException for an unregistered consumer while enabled.
$row = $tracker->tryRecord($context, $result);
```

`record()` never no-ops silently — that is exactly why `tryRecord()` exists.

### `subject_reference` must be a non-PII surrogate

`subjectReference` is opaque to lararoi, but "opaque" is not minimization. Pass a
**stable surrogate id** — your own entity primary key or a hash — **never raw
PII** (email, invoice number, a VAT-like value). lararoi treats it as opaque and
cannot enforce its content; the guardrail is this contract plus the registered
consumer's retention bound.

### Read the log back (consumer-scoped)

Every read requires the `consumer` as its first argument — no read returns
another consumer's history:

```php
$tracker->forSubject('larabill', (string) $company->id);   // one subject's history
$tracker->forVat('larabill', 'ESB12345678', 'ES');         // one NIF's history
$tracker->between('larabill', $from, $to);                 // a queried_at window
```

Each returns an iterable of `VerificationQueryModelInterface` rows — read them
via the getters (`getVatCode()`, `isValid()`, `isCacheHit()`,
`getResponseSnapshot()`, `getQueriedAt()`, …; see `contracts.md`).

## 7. Declare retention

Retention is **per consumer**, declared as `retention_days` on the consumer's
registry entry:

- **A number** → lararoi stamps `retention_until = queried_at + retention_days`
  (in **UTC**).
- **`null`** → a conscious registered "keep forever": `retention_until` is null
  and the row is never auto-pruned. `null` must be a deliberate registered
  choice, not the accident of an unregistered key.

```php
'consumers' => [
    'larabill' => ['retention_days' => 2555],  // ~7 years, then prunable
    'archivist' => ['retention_days' => null],  // conscious keep-forever
],
```

Prune expired rows with the command (schedule it as you see fit); it deletes only
rows whose `retention_until` is set **and** has passed (UTC), never touching
null-retention rows:

```bash
php artisan roi:prune-verification-queries
```

Legal retention is your fiscal obligation and policy; lararoi is the storage and
the enforcement. Registering the consumer with an explicit `retention_days`
(`null` included) is a **precondition** of tracking.

## 8. Declare an output mapping (optional)

The service always returns the canonical shape (step 5); config never rewrites
it. If your domain uses a different shape, declare a mapper and apply it **at your
own boundary** — the mapper is a consumer-invoked transform, **never** applied
inside `verifyVatNumber()`.

Write a mapper:

```php
use Aichadigital\Lararoi\Contracts\VerificationResultMapperInterface;

class LarabillVatMapper implements VerificationResultMapperInterface
{
    public function map(array $canonical): mixed
    {
        return [
            'valid' => $canonical['is_valid'],
            'name'  => $canonical['company_name'],
            // … your own shape …
        ];
    }
}
```

Declare it on the consumer entry:

```php
'consumers' => [
    'larabill' => ['retention_days' => 2555, 'mapper' => \App\Lararoi\LarabillVatMapper::class],
],
```

Resolve and apply it yourself:

```php
use Aichadigital\Lararoi\Services\VerificationResultMapperRegistry;

$canonical = app(VatVerificationServiceInterface::class)->verifyVatNumber('B12345678', 'ES');
$mine = app(VerificationResultMapperRegistry::class)->mapperFor('larabill')->map($canonical);
```

The mapper's input is the canonical seven-fact set (the same definition the
tracker snapshots). A consumer with no declared `mapper` gets the identity mapper
(canonical array unchanged). You can also register a mapper instance at runtime
via `$registry->register('larabill', $mapper)`, which takes precedence over the
config key.

## Do not create your own verification/tracking tables

lararoi owns its schema. The `roi_` prefix exists precisely so no consumer
collides by picking a natural generic name (`vat_verifications`,
`verification_queries`, …). Concretely:

- **Do not** create a `vat_verifications` / `verification_queries` (or similarly
  named) table in your app for VAT verification or its audit — you already have
  `roi_vat_verifications` and `roi_verification_queries` from lararoi.
- **Do not** re-implement caching, provider fallback, or an attribution/audit log
  — consume the service, the tracker, and the models.
- If you genuinely need a different **store**, swap the **model** (contracts 3 and
  5 in `contracts.md`) via `lararoi.models.*.class`; you do not rename lararoi's
  tables (they are fixed, not config-overridable).

That is the whole point of lararoi being the domain owner: one place verifies,
caches, tracks and retains; consumers stop duplicating and stop colliding.
