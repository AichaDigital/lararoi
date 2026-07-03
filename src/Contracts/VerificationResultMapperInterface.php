<?php

namespace Aichadigital\Lararoi\Contracts;

/**
 * Per-consumer output mapper contract (ADR-002 D6).
 *
 * lararoi's service always returns the stable canonical shape; config never
 * rewrites it. A consumer whose domain uses a different shape declares an
 * explicit mapper and applies it AT ITS OWN BOUNDARY:
 *
 *     $mapper = $registry->mapperFor('larabill');
 *     $mine   = $mapper->map($lararoi->verifyVatNumber('B12345678', 'ES'));
 *
 * The mapper is NEVER applied inside verifyVatNumber() (Codex P1): that method
 * keeps returning the canonical array. map() returns mixed because the
 * consumer's shape is unknown to lararoi and must not leak into the service's
 * typed array contract.
 */
interface VerificationResultMapperInterface
{
    /**
     * Transform the canonical verification result into the consumer's own shape.
     *
     * The input is the seven canonical verification facts — the same set the
     * tracker snapshots (D3): is_valid, vat_code, country_code, company_name,
     * company_address, api_source, request_date.
     *
     * @param  array<string, mixed>  $canonical  the seven canonical verification facts
     * @return mixed the consumer's own shape (unknown to lararoi)
     */
    public function map(array $canonical): mixed;
}
