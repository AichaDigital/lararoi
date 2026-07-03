# lararoi contract set (v1.0)

This is the documented, stable public contract surface of lararoi (ADR-002 D7). A
consumer (larabill, an accounting app, …) pins these; everything else in the
package is an implementation detail that may change without a major version.

The surface is **six interfaces + one value object** (the seven items D7 lists as
the contract set), plus **two supporting exceptions** that are part of the failure
semantics. Signatures below are copied from the code — treat this file as the
canonical reference.

For task-oriented usage see `integration.md` (the full consumer guide) and
`usage.md`; for configuration keys see `configuration.md`.

- **Namespace:** `Aichadigital\Lararoi\Contracts` (interfaces),
  `Aichadigital\Lararoi\ValueObjects` (value object),
  `Aichadigital\Lararoi\Exceptions` (exceptions).

## 1. `VatVerificationServiceInterface`

The entry point: verify a VAT/NIF number and get back the stable canonical
result. The optional third argument opts into passive tracking (contract 4/7).

```php
use Aichadigital\Lararoi\ValueObjects\VerificationContext;

interface VatVerificationServiceInterface
{
    /**
     * @return array{
     *     is_valid: bool, vat_code: string, country_code: string,
     *     company_name: string|null, company_address: string|null,
     *     api_source: string, cached: bool, request_date: string|null,
     *     response_data?: array
     * }
     *
     * @throws \Aichadigital\Lararoi\Exceptions\VatVerificationException
     * @throws \Aichadigital\Lararoi\Exceptions\TrackingDisabledException
     * @throws \Aichadigital\Lararoi\Exceptions\UnknownConsumerException
     */
    public function verifyVatNumber(
        string $vatNumber,
        string $countryCode,
        ?VerificationContext $context = null,
    ): array;
}
```

- **`$vatNumber`** is passed **without** the country prefix (e.g. `B12345678`, not
  `ESB12345678`).
- **The concrete return** carries three more keys than the `@return` shape above —
  `cache_status` (`fresh` / `cached` / `refreshed`) and a nested `response_data`
  echo. The seven fields `is_valid, vat_code, country_code, company_name,
  company_address, api_source, request_date` are the **canonical facts** used
  everywhere else (tracking snapshot, mapper input).
- **Bound in the container** — resolve via
  `app(VatVerificationServiceInterface::class)`.

## 2. `VatProviderInterface`

Implemented by every verification provider (VIES SOAP/REST, isvat, viesapi,
vatlayer). Implement it to add a provider; consumers rarely touch it directly.

```php
interface VatProviderInterface
{
    /**
     * @return array{
     *     valid: bool, name: string|null, address: string|null,
     *     request_date: string|null, vat_number: string,
     *     country_code: string, api_source: string
     * }
     *
     * @throws \Aichadigital\Lararoi\Exceptions\ApiUnavailableException
     */
    public function verify(string $vatNumber, string $countryCode): array;

    public function getName(): string;

    public function isAvailable(): bool;

    public function isFree(): bool;
}
```

- **Provider vocabulary differs from the canonical one:** a provider returns
  `valid` / `name` / `address` / `vat_number`; the service normalizes those into
  the canonical `is_valid` / `company_name` / `company_address` / `vat_code`.

## 3. `VatVerificationModelInterface`

The **cache** model contract — "current status per NIF", one row per
`(vat_code, country_code)`, TTL-expiring. Swap the model via
`lararoi.models.vat_verification.class` (e.g. for a UUID primary key or added
relationships) without changing lararoi.

```php
use Illuminate\Support\Carbon;

interface VatVerificationModelInterface
{
    public static function findByVatCodeAndCountry(string $vatCode, string $countryCode): ?self;

    public function isExpired(): bool;

    public function getVatCode(): string;

    public function getCountryCode(): string;

    public function isValid(): bool;

    public function getCompanyName(): ?string;

    public function getCompanyAddress(): ?string;

    public function getApiSource(): string;

    public function getVerifiedAt(): ?Carbon;

    /** @return array<string, mixed>|null */
    public function getResponseData(): ?array;
}
```

- **Default model:** `Aichadigital\Lararoi\Models\VatVerification` (table
  `roi_vat_verifications`).

## 4. `VerificationTrackerInterface`

The **multi-consumer tracking / audit** contract (ADR-002 D4): record a real
verification result and read a consumer's history back. Records real verification
results only (R1) — it is not a channel for asserting an unverified status. All
reads are **consumer-scoped**; no read returns another consumer's history.

```php
use Aichadigital\Lararoi\ValueObjects\VerificationContext;
use Carbon\CarbonInterface;

interface VerificationTrackerInterface
{
    /**
     * @param  array<string, mixed>  $verificationResult  the verifyVatNumber() return
     *
     * @throws \Aichadigital\Lararoi\Exceptions\TrackingDisabledException  when tracking is disabled
     * @throws \Aichadigital\Lararoi\Exceptions\UnknownConsumerException   when the consumer is not registered
     */
    public function record(VerificationContext $context, array $verificationResult): VerificationQueryModelInterface;

    /**
     * Best-effort variant: returns null instead of throwing when tracking is disabled.
     * Still throws UnknownConsumerException for an unregistered consumer while enabled.
     *
     * @param  array<string, mixed>  $verificationResult
     */
    public function tryRecord(VerificationContext $context, array $verificationResult): ?VerificationQueryModelInterface;

    /** @return iterable<VerificationQueryModelInterface> */
    public function forSubject(string $consumer, string $subjectReference): iterable;

    /** @return iterable<VerificationQueryModelInterface> */
    public function forVat(string $consumer, string $vatCode, string $countryCode): iterable;

    /** @return iterable<VerificationQueryModelInterface> */
    public function between(string $consumer, CarbonInterface $from, CarbonInterface $to): iterable;
}
```

- **The input to `record()` / `tryRecord()` is the full `verifyVatNumber()` return**
  — the tracker derives `cache_hit` from its `cache_status` and minimizes the
  stored snapshot to the seven canonical facts. You never hand-build the payload
  or pass `cache_hit` yourself (a cached result must never be logged as live).
- **Bound in the container** — resolve via
  `app(VerificationTrackerInterface::class)`. Default impl:
  `Aichadigital\Lararoi\Services\VerificationTracker`.
- **Pruning is not on the contract.** The default implementation adds
  `prune(?CarbonInterface $now = null): int`, invoked by the
  `roi:prune-verification-queries` command — not part of
  `VerificationTrackerInterface`.

## 5. `VerificationQueryModelInterface`

The **tracking** model contract — a stable, model-agnostic read surface over the
append-only rows returned by the tracker. Swap the model via
`lararoi.models.verification_query.class`.

```php
use Illuminate\Support\Carbon;

interface VerificationQueryModelInterface
{
    public function getConsumer(): string;

    public function getSubjectReference(): ?string;

    public function getVatCode(): string;

    public function getCountryCode(): string;

    public function isValid(): bool;

    public function getApiSource(): string;

    public function isCacheHit(): bool;

    /** @return array<string, mixed>|null */
    public function getResponseSnapshot(): ?array;

    public function getQueriedAt(): ?Carbon;

    public function getRetentionUntil(): ?Carbon;
}
```

- **Default model:** `Aichadigital\Lararoi\Models\VerificationQuery` (table
  `roi_verification_queries`, append-only, no soft-deletes).

## 6. `VerificationResultMapperInterface`

The **per-consumer output mapper** (ADR-002 D6). A consumer whose domain uses a
different shape declares a mapper and applies it **at its own boundary**. The
mapper is never applied inside `verifyVatNumber()`, which always returns the
canonical array.

```php
interface VerificationResultMapperInterface
{
    /**
     * Transform the canonical verification result into the consumer's own shape.
     *
     * @param  array<string, mixed>  $canonical  the canonical verification facts
     *                                            (is_valid, vat_code, country_code,
     *                                            company_name, company_address,
     *                                            api_source, request_date)
     * @return mixed  the consumer's own shape (unknown to lararoi)
     */
    public function map(array $canonical): mixed;
}
```

- **`map()` returns `mixed` on purpose** — the consumer's shape is unknown to
  lararoi and must not leak into the service's typed `array` contract.
- **Resolution is via a registry, not this interface directly.**
  `Aichadigital\Lararoi\Services\VerificationResultMapperRegistry` resolves the
  mapper for a consumer:

  ```php
  public function mapperFor(string $consumer): VerificationResultMapperInterface;
  public function register(string $consumer, VerificationResultMapperInterface $mapper): void;
  ```

  Resolution order per consumer: a programmatically `register()`ed instance →
  the `lararoi.consumers.<consumer>.mapper` class string → the identity mapper
  (`IdentityVerificationResultMapper`, returns the canonical array unchanged).
  A `mapper` key set to a non-mapper class throws.

## 7. `VerificationContext` (value object)

Attribution for a tracked verification (ADR-002 D4). Supplied as the optional
third argument to `verifyVatNumber()` and as the input to the tracker's
`record()` / `tryRecord()`.

```php
namespace Aichadigital\Lararoi\ValueObjects;

final class VerificationContext
{
    public function __construct(
        public readonly string $consumer,
        public readonly ?string $subjectReference = null,
    ) {}
}
```

- **`consumer`** — the consumer key. When tracking is enabled it **must** be a
  registered key in `lararoi.consumers`, otherwise recording throws
  `UnknownConsumerException`.
- **`subjectReference`** — an opaque, consumer-owned entity reference lararoi
  never interprets. Pass a **stable surrogate id** (your own entity PK or a
  hash), **never raw PII** (email, invoice number, a VAT-like value). lararoi
  cannot enforce content on an opaque field.

## Supporting exceptions

Both extend `RuntimeException` and are thrown by the tracking path.

### `TrackingDisabledException`

Thrown by `VerificationTrackerInterface::record()` when
`lararoi.tracking.enabled` is `false`. An explicit `record()` never no-ops
silently — use `tryRecord()` for a best-effort null-on-disabled instead.

```php
class TrackingDisabledException extends \RuntimeException
{
    public function __construct(string $message = '...');
}
```

### `UnknownConsumerException`

Thrown when a verification is recorded (via `record()`, `tryRecord()`, or the
inline `verifyVatNumber($ctx)` path) for a consumer that is **not** registered in
`lararoi.consumers` while tracking is enabled. Closes the typo hole so a mistyped
key fails loudly instead of silently accumulating unpolicied history.

```php
class UnknownConsumerException extends \RuntimeException
{
    public function __construct(string $consumer);

    /** The unregistered consumer key that triggered the exception. */
    public function getConsumer(): string;
}
```
