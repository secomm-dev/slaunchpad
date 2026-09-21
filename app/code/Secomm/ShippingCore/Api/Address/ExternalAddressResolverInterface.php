<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

/**
 * DEC-FEATYA2C0W-004 (D9) / TASK-AQT7V3 — optional external disambiguation provider (e.g.
 * Secomm_VietMap, a Google-based resolver). Implemented by OPTIONAL provider modules and
 * registered into the ExternalAddressResolverPool via DI; ShippingCore never depends on any
 * provider implementation and operates correctly with a zero-provider pool.
 *
 * Contract: an external resolver turns an UNRESOLVED shipping address (AMBIGUOUS/UNMAPPED
 * context) into a canonical target unit code. It must NEVER return a provider-specific id
 * (VietMap id, Google place id, carrier id) — the only valid answer is a canonical unit_code
 * within the context's target scheme, or null ("could not disambiguate").
 *
 * Implementations own their own failure handling (timeouts, logging, quota); they return null
 * instead of throwing into the orchestration layer.
 */
interface ExternalAddressResolverInterface
{
    /**
     * TASK-Y3X6H5 (architecture v4 §23 — seam boundary, explicit):
     * - AMBIGUOUS ONLY: the resolver is a SELECTOR over a candidate set VietNamAddress already
     *   knows (pick one candidate or return null). It must NOT attempt UNMAPPED destinations
     *   (candidate set empty — that would turn it into a canonical mapping discovery engine),
     *   must NOT mint canonical units, and must NOT write authoritative mapping edges.
     * - Called at the ADDRESS ENTRY (shift-left, §5.1), never inside the carrier RATE fan-out.
     * - The result is persisted into the CanonicalResolutionSnapshot with source =
     *   EXTERNAL_RESOLVER + provenance; no provider-specific ids are ever returned.
     */
    /**
     * Whether this resolver is usable right now (configured and reachable). False = not yet
     * configured (e.g. missing API key) or service unavailable — the pool skips it silently.
     */
    public function isAvailable(): bool;

    /**
     * Disambiguate the destination described by $context.
     *
     * @return string|null canonical target unit_code within the context's target scheme,
     *                      or null when the resolver cannot disambiguate.
     */
    public function resolve(ShippingAddressResolutionContextInterface $context): ?string;
}
