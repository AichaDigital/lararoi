<?php

declare(strict_types=1);

namespace Aichadigital\Lararoi\Services;

use Aichadigital\Lararoi\Contracts\VerificationQueryModelInterface;
use Aichadigital\Lararoi\Contracts\VerificationTrackerInterface;
use Aichadigital\Lararoi\Exceptions\TrackingDisabledException;
use Aichadigital\Lararoi\Exceptions\UnknownConsumerException;
use Aichadigital\Lararoi\Models\VerificationQuery;
use Aichadigital\Lararoi\ValueObjects\VerificationContext;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Default multi-consumer tracking / audit implementation (ADR-002 D3/D4/D5).
 *
 * Append-only: every record() call INSERTs a new row (this is history, not the
 * cache). cache_hit is DERIVED from the passed verification result, the snapshot
 * is minimized to the seven canonical facts, and retention is stamped in UTC
 * from the consumer's registered policy.
 */
class VerificationTracker implements VerificationTrackerInterface
{
    /**
     * The seven canonical verification facts kept in response_snapshot (D3).
     *
     * Never the cache meta (cached/cache_status) or the nested response_data /
     * raw provider payload — persisting those would be an uncontrolled
     * data-retention liability on an append-only, retained table.
     *
     * @var list<string>
     */
    private const SNAPSHOT_FACTS = [
        'is_valid',
        'vat_code',
        'country_code',
        'company_name',
        'company_address',
        'api_source',
        'request_date',
    ];

    /**
     * {@inheritDoc}
     */
    public function record(VerificationContext $context, array $verificationResult): VerificationQueryModelInterface
    {
        if (! $this->trackingEnabled()) {
            throw new TrackingDisabledException;
        }

        $this->assertRegisteredConsumer($context->consumer);

        return $this->write($context, $verificationResult);
    }

    /**
     * {@inheritDoc}
     */
    public function tryRecord(VerificationContext $context, array $verificationResult): ?VerificationQueryModelInterface
    {
        if (! $this->trackingEnabled()) {
            return null;
        }

        // A disabled tracker is a silent no-op; an unregistered consumer while
        // enabled is a programmer error and still throws loudly.
        $this->assertRegisteredConsumer($context->consumer);

        return $this->write($context, $verificationResult);
    }

    /**
     * {@inheritDoc}
     */
    public function forSubject(string $consumer, string $subjectReference): iterable
    {
        return $this->toInterfaceList(
            $this->newQuery()
                ->where('consumer', $consumer)
                ->where('subject_reference', $subjectReference)
                ->orderByDesc('queried_at')
                ->get()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function forVat(string $consumer, string $vatCode, string $countryCode): iterable
    {
        return $this->toInterfaceList(
            $this->newQuery()
                ->where('consumer', $consumer)
                ->where('vat_code', $vatCode)
                ->where('country_code', $countryCode)
                ->orderByDesc('queried_at')
                ->get()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function between(string $consumer, CarbonInterface $from, CarbonInterface $to): iterable
    {
        return $this->toInterfaceList(
            $this->newQuery()
                ->where('consumer', $consumer)
                ->whereBetween('queried_at', [$from, $to])
                ->orderBy('queried_at')
                ->get()
        );
    }

    /**
     * Physically delete rows whose retention_until has passed (ADR-002 D5).
     *
     * Compares in UTC and never touches null-retention ("keep forever") rows.
     * Not part of the consumer contract (VerificationTrackerInterface) — it is
     * the operation invoked by the roi:prune-verification-queries command.
     *
     * @return int the number of deleted rows
     */
    public function prune(?CarbonInterface $now = null): int
    {
        $now ??= Carbon::now('UTC');

        return $this->newQuery()
            ->whereNotNull('retention_until')
            ->where('retention_until', '<', $now)
            ->delete();
    }

    /**
     * Write a single append-only row for a real verification result.
     *
     * @param  array<string, mixed>  $verificationResult
     */
    protected function write(VerificationContext $context, array $verificationResult): VerificationQueryModelInterface
    {
        // Timestamps are UTC regardless of the app/DB timezone (D5): a UTC Carbon
        // is stored as its UTC wall-clock and the prune compares UTC-to-UTC, so
        // pruning is never off-by-a-day around timezone/DST boundaries.
        $queriedAt = Carbon::now('UTC');

        $model = $this->resolveModelInstance();

        $model->forceFill([
            'consumer' => $context->consumer,
            'subject_reference' => $context->subjectReference,
            'vat_code' => (string) ($verificationResult['vat_code'] ?? ''),
            'country_code' => (string) ($verificationResult['country_code'] ?? ''),
            'is_valid' => (bool) ($verificationResult['is_valid'] ?? false),
            'api_source' => (string) ($verificationResult['api_source'] ?? 'UNKNOWN'),
            'cache_hit' => $this->deriveCacheHit($verificationResult),
            'response_snapshot' => $this->minimizeSnapshot($verificationResult),
            'queried_at' => $queriedAt,
            'retention_until' => $this->retentionUntil($context->consumer, $queriedAt),
        ]);

        $model->save();

        return $model;
    }

    /**
     * Derive whether the result was served from cache vs a live provider (D3).
     *
     * Never a caller-supplied parameter: a cached result must never be logged as
     * live (Codex P1). Prefer the richer cache_status, fall back to the boolean.
     *
     * @param  array<string, mixed>  $verificationResult
     */
    protected function deriveCacheHit(array $verificationResult): bool
    {
        if (array_key_exists('cache_status', $verificationResult)) {
            return $verificationResult['cache_status'] === 'cached';
        }

        return (bool) ($verificationResult['cached'] ?? false);
    }

    /**
     * Reduce a service result to the seven canonical verification facts (D3).
     *
     * @param  array<string, mixed>  $verificationResult
     * @return array<string, mixed>
     */
    protected function minimizeSnapshot(array $verificationResult): array
    {
        $snapshot = [];

        foreach (self::SNAPSHOT_FACTS as $fact) {
            $snapshot[$fact] = $verificationResult[$fact] ?? null;
        }

        $snapshot['is_valid'] = (bool) ($verificationResult['is_valid'] ?? false);

        return $snapshot;
    }

    /**
     * Compute the UTC retention deadline from the consumer's policy (D5).
     *
     * `retention_days` null (a conscious registered choice) → null → never pruned.
     */
    protected function retentionUntil(string $consumer, CarbonInterface $queriedAt): ?CarbonInterface
    {
        $consumers = config('lararoi.consumers', []);
        $retentionDays = is_array($consumers) ? ($consumers[$consumer]['retention_days'] ?? null) : null;

        if ($retentionDays === null) {
            return null;
        }

        return $queriedAt->copy()->addDays((int) $retentionDays);
    }

    /**
     * Whether tracking is enabled (the single kill-switch, D3).
     */
    protected function trackingEnabled(): bool
    {
        return (bool) config('lararoi.tracking.enabled', false);
    }

    /**
     * Guard the consumer allow-list (D5).
     *
     * @throws UnknownConsumerException when the consumer is not a registered key
     */
    protected function assertRegisteredConsumer(string $consumer): void
    {
        $consumers = config('lararoi.consumers', []);

        if (! is_array($consumers) || ! array_key_exists($consumer, $consumers)) {
            throw new UnknownConsumerException($consumer);
        }
    }

    /**
     * Build a fresh query for the configured tracking model.
     *
     * @return Builder<Model>
     */
    protected function newQuery(): Builder
    {
        return $this->resolveModelInstance()->newQuery();
    }

    /**
     * Narrow a collection of rows to the tracking contract.
     *
     * Every row from the configured tracking model implements
     * VerificationQueryModelInterface (resolveModelInstance guards it), but the
     * Eloquent Builder is generic over Model. The instanceof pass does the real
     * narrowing so the read contract's element type holds without a @var/suppression.
     *
     * @param  iterable<Model>  $rows
     * @return list<VerificationQueryModelInterface>
     */
    protected function toInterfaceList(iterable $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            if ($row instanceof VerificationQueryModelInterface) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * Resolve the swappable tracking model instance.
     *
     * The interface does not guarantee Eloquent, but every shipped/swapped model
     * is an Eloquent Model (the write path needs forceFill/save/newQuery). The
     * runtime instanceof guard keeps the type honest without a @var annotation
     * (PHPStan level 5 rejects those) — the same pattern PR2 used for the cache
     * model in VatVerificationService::persistVerification().
     *
     * @return Model&VerificationQueryModelInterface
     */
    protected function resolveModelInstance(): Model
    {
        $modelConfig = config('lararoi.models.verification_query', VerificationQuery::class);

        $modelClass = is_array($modelConfig)
            ? ($modelConfig['class'] ?? VerificationQuery::class)
            : $modelConfig;

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            throw new RuntimeException('The lararoi verification_query model is not configured to a valid class.');
        }

        $model = app($modelClass);

        if (! $model instanceof Model || ! $model instanceof VerificationQueryModelInterface) {
            throw new RuntimeException(
                'The lararoi verification_query model must be an Eloquent model implementing VerificationQueryModelInterface.'
            );
        }

        return $model;
    }
}
