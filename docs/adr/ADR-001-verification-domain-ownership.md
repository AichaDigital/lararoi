# ADR-001: lararoi owns the intra-community VAT/NIF verification domain (verification + multi-consumer tracking + agnostic contracts)

> **Status**: Accepted
> **Date**: 2026-07-03
> **Author**: Abdelkarim Mateos
> **Relates**: larabill AID-309 (consumer that must adopt lararoi), lararoi AID-263 (canonical output shape + syntax validation), lararoi AID-301 (package-managed schema). This is lararoi's first ADR and sets the domain contract every consumer depends on.

## Context — the "jaula de grillos"

lararoi exists to be *the* agnostic package for intra-community NIF-VAT verification in the umbrella, consumed by several apps: **larabill** (billing) today, an **accounting app (e.g. Openmiza)** next, and others later. In practice the responsibility is tangled and it keeps causing duplication and coupling problems. The concrete state (verified 2026-07-02/03):

- **lararoi provides the verification service and a cache, but its documented "audit trail" is not built.**
  - It has: `VatVerificationServiceInterface::verifyVatNumber(vat, country): array`, a `VatProviderManager` with 5 providers (VIES SOAP/REST, isvat, viesapi, vatlayer) + automatic fallback, per-country syntax validation (`VatFormat`, AID-263), a canonical output shape (AID-263), and a TTL cache in the `vat_verifications` table.
  - `vat_verifications` is a **single-row-per-NIF cache** (`findByVatCodeAndCountry` → first; one current row per `vat_code + country_code`, TTL-expiring). It has **no per-query history, no consumer attribution, no retention** — yet `docs/AGENT_CONTEXT.md` and `docs/project.md` promise "Audit Trail: log all verification attempts" and "loggear consultas para auditoría". **The audit/tracking capability is documented but unimplemented.**
  - The verification **model is already swappable**: `VatVerificationModelInterface` (bound to lararoi's `VatVerification` by default) lets a consumer supply its own model. This is real, partial agnosticism — of the *model*, not yet of the *output shape* or the *tracking*.

- **larabill (a consumer) never activated the dependency and duplicated the whole domain.** `composer.json` declared `aichadigital/lararoi: ^0.5` but larabill imported nothing from `Lararoi`. Instead it carried its own `VatVerification` (cache), `VatVerificationService` (multi-provider), `VatApiIntegrationService`, plus a per-user ROI layer (`UserRoiVerification`) and a legal-retention query log (`RoiQuery`). That duplicate is **dead code** today — larabill has no live verification call; its reverse-charge decision is an **input flag `is_roi_taxed`** the app fills in. Its `vat_verifications` table name **collides** with lararoi's.

- **Every future consumer wants the same two things:** verify a NIF, and **store/track** the verification for its own audit/fiscal needs — each possibly with a **different output shape/model** than lararoi's canonical one.

Net effect: duplicated verification code, a dead-but-declared dependency, a table-name collision, and no shared, agnostic way for consumers to track verifications. This ADR fixes ownership and the consumer contract so the tangle stops recurring.

## Decision

**lararoi is the single owner of the intra-community VAT/NIF verification domain — the verification itself AND its persistence / tracking / audit — served to N consumers through explicit contracts and agnostic configuration. Consumers (larabill, accounting, …) NEVER reimplement the domain; they consume it and adapt it to their own model by declaring the "how" in config/contracts, not by copying code.**

lararoi must own and offer, and consumers rely on, these five responsibilities:

1. **Verification** *(exists)* — `verifyVatNumber(vat, country)` over the multi-provider manager with fallback + per-country syntax validation, returning the canonical AID-263 shape. This is the stable public contract.

2. **Cache** *(exists)* — TTL-based, one current row per `vat_code+country`, enable/disable-able. An efficiency concern, distinct from tracking (see #3).

3. **Verification tracking / audit log — multi-consumer** *(TO BUILD — the core gap)* — an **append-only record of verification queries**, attributed to the **consumer** that asked (which consumer, optionally which subject/entity of that consumer, when, result, provider, cache-hit-or-live), with a **retention policy the consumer declares**. This is exactly what larabill duplicated as `RoiQuery`/`UserRoiVerification`; it must instead be **offered once by lararoi** to all consumers. Distinction that matters: the **cache** answers "what is the current status of this NIF"; the **tracking log** answers "prove that consumer X checked this NIF on date Y" — a fiscal/audit need, not a cache.

4. **Output & persistence agnosticism** *(partly exists — to complete)* — the model is already swappable (`VatVerificationModelInterface`). Complete the agnostic surface so a consumer whose domain uses a **different shape** (different field names, extra fields, its own storage) **declares the mapping via config/contract** instead of re-implementing — the same "declare the how in config" principle larabill already uses for its configurable user model / user-id. The consumer adapts to lararoi through a declared adapter, not by forking the code.

5. **Consumer-facing contracts** *(partly exists — to formalize)* — a documented, stable set of contracts covering: how a consumer calls verification (#1), how it reads the canonical result, how it records/reads tracking (#3), and how it declares its agnostic mapping and retention policy (#4). `VatVerificationServiceInterface`, `VatVerificationModelInterface`, `VatProviderInterface` are the seed; tracking + retention + output-mapping contracts are missing.

## Boundary between lararoi and its consumers

- **lararoi owns:** the verification, the providers/fallback, the cache, the **tracking/audit log** (the store of record for "who verified what, when"), and the **contracts + config** that make it agnostic. lararoi owns the `vat_verifications` schema and any tracking table; **consumers do not create their own verification/tracking tables.**
- **The consumer owns:** *when* to verify (its own trigger), and *how* the result maps into its own domain. Crucially, **not every consumer verifies inline** — larabill today receives `is_roi_taxed` as an input and performs no live verification. So the architecture must NOT assume consumers verify at a fixed point; each consumer chooses to (a) call lararoi to verify at some trigger, and/or (b) simply read tracking. lararoi offers the capability; the consumer decides the trigger. **Tracking records real verification results only; consumer business assertions such as `is_roi_taxed` remain outside lararoi's verification log** — a consumer that never performs a verification simply has no tracking rows; recording an asserted-but-unverified status is a consumer-side business audit concern, a different domain (see ADR-002, R1).
- **Legal retention is the consumer's policy, lararoi's storage.** How many years a query must be retained is a fiscal obligation of the *consumer* (the issuer). So the consumer **declares its retention policy via config**, and lararoi's tracking log stores/enforces it. lararoi provides a sensible default; the consumer overrides. (Neither package captures the official VIES `requestIdentifier` today — if a legally-binding consultation proof is ever required, that is a provider-level enhancement in lararoi, offered to all consumers, not a per-consumer reinvention.)

## Current state vs. target (gap analysis)

| Capability | lararoi today | Target |
|---|---|---|
| Verify NIF (providers + fallback + syntax) | ✅ `VatVerificationServiceInterface` / `VatProviderManager` / `VatFormat` | ✅ keep; it is the stable contract |
| Canonical output shape | ✅ (AID-263) | ✅ keep; document as the consumer contract |
| Cache (current status per NIF) | ✅ `vat_verifications`, TTL | ✅ keep |
| Swappable model | ✅ `VatVerificationModelInterface` | ✅ keep; extend for output mapping |
| **Multi-consumer tracking / audit log** | ❌ only the single-row cache; no history, no consumer attribution, no retention | **BUILD:** append-only query log with consumer attribution + consumer-declared retention |
| **Output/persistence agnosticism (shape mapping)** | ⚠️ model-swap only | **COMPLETE:** config/contract for a consumer to declare its own shape/mapping |
| **Formal consumer contracts (verify + track + adapt + retain)** | ⚠️ partial (verify + model) | **FORMALIZE:** documented contract set + integration guide |
| Ownership clarity (no consumer duplicates the domain) | ❌ larabill duplicated it | **ENFORCE:** this ADR; consumers consume, never reimplement |
| Table-name collision (`vat_verifications`) | ❌ larabill also creates it | **RESOLVE:** lararoi owns the schema; consumers stop creating it |

## Actions for lararoi (what "doing it right" means here)

These are the deliverables that unblock the consumers. Each becomes its own lararoi issue/PR (this ADR is the umbrella decision, not the implementation plan):

1. **Design + build the multi-consumer tracking/audit log.** Decide the shape: consumer id (string key), optional subject reference (opaque to lararoi), NIF+country, result snapshot, provider, cache-hit flag, queried-at, retention-until (derived from the consumer's declared policy). Append-only. Documented as the store of record. This absorbs larabill's `RoiQuery` concept and generalizes it.
2. **Define the retention contract.** A per-consumer config where the consumer declares its retention policy; lararoi applies it to the tracking log. Ship a default.
3. **Complete the output/persistence agnosticism.** Extend beyond model-swap: a documented way for a consumer to declare how the canonical result maps to its own shape/storage (config-driven adapter/contract), so no consumer copies code to "translate" lararoi's output.
4. **Formalize + document the consumer contract set** (`docs/integration.md` update + this ADR): verify → read canonical result → record/read tracking → declare mapping + retention. One integration guide every consumer follows.
5. **Own the schema; resolve the collision.** lararoi's `vat_verifications` (+ the new tracking table) are the only ones; the integration guide tells consumers to NOT create their own — they get the tables from lararoi (package-managed, AID-301).
6. **Version + stabilize.** These are 0.x → 1.0 shaping changes; cut a tag once the tracking + agnosticism contracts are in, so consumers can pin a real contract (this is the "activate the dependency" precondition larabill was missing).

## Consequences

**Positive**
- One place owns verification + tracking + the agnostic contract. Consumers stop duplicating and stop colliding.
- larabill and the accounting app share the same audit/tracking capability instead of each inventing one.
- "Declare the how in config" makes new consumers cheap and keeps lararoi from hard-coding any consumer's shape.

**Costs / open questions (to resolve in the per-issue designs, not here)**
- The tracking log's exact schema and the subject-reference model (how a consumer points at its own entity without lararoi knowing that entity) need design.
- The retention contract's shape (per-consumer config key vs. a contract object) needs design.
- Migration/ownership of the `vat_verifications` table name where a consumer already created one (dev-main only; no production to preserve, per umbrella posture).

## Consequences for consumers (informative — not this ADR's scope to execute)

- **larabill (AID-309).** Retire the duplicated domain (models/services/tables) — that part was correct — but **keep and activate** the `lararoi` dependency and consume it, rather than deleting it (the closed PR #81 did the opposite and was discarded). Because larabill has **no live verification point today** (`is_roi_taxed` is an input), larabill must first DECIDE its stance: either (a) introduce a verification trigger that calls lararoi and records tracking, or (b) keep receiving `is_roi_taxed` from the app and only consume lararoi's tracking/read side. That is a larabill design decision, taken **after** lararoi ships the contracts above. larabill's own fiscal retention of invoices stays separate (`LegallyRetainable` / ADR-008).
- **Accounting app (e.g. Openmiza).** A second consumer of the same contracts: verify + track + declare its own shape/retention. Its existence is the reason the tracking must live in lararoi, not in any single consumer.

## Non-goals

- This ADR does not design the tracking schema, the retention contract, or the mapping mechanism in detail — each is a follow-up issue in lararoi.
- This ADR does not change any consumer. Consumer adoption (larabill AID-309, accounting) happens after lararoi ships the contracts and cuts a tag.
- No provider-level `requestIdentifier`/legal-proof work is committed here; it is noted as a future domain-level enhancement if a binding proof is ever required.
